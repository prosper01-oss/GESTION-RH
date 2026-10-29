import { useEffect, useMemo, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import { DeleteButton, EditButton } from '../../components/IconButton';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import SearchInput from '../../components/SearchInput';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, montant, peremptionLabels } from '../../services/pharma';

const emptyForm = { designation: '', unite: '', presentation: 1, seuil: 1, qte_max: '', statut: 1 };

const alerteStyles = {
    rupture: 'bg-red-50 text-red-700 ring-red-600/20',
    seuil: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    null: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
};

const peremptionStyles = {
    ok: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    proche: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    urgent: 'bg-orange-50 text-orange-700 ring-orange-600/20',
    perime: 'bg-red-50 text-red-700 ring-red-600/20',
    null: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

export default function Articles() {
    const [articles, setArticles] = useState([]);
    const [taux, setTaux] = useState(null);
    const [unites, setUnites] = useState([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [message, setMessage] = useState({ type: 'error', text: '' });
    const [form, setForm] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [toDelete, setToDelete] = useState(null);
    const [lots, setLots] = useState(null);

    function load() {
        setLoading(true);
        Promise.all([api.get('/pharma/articles'), api.get('/pharma/articles/unites')])
            .then(([liste, referentiel]) => {
                setArticles(liste.data.data);
                setTaux(liste.data.taux);
                setUnites(referentiel.data.data);
            })
            .catch((error) => setMessage({ type: 'error', text: errorMessage(error) }))
            .finally(() => setLoading(false));
    }

    useEffect(load, []);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q ? articles.filter((a) => [a.reference, a.designation, a.presentation, a.statut].some((value) => String(value ?? '').toLowerCase().includes(q))) : articles;
    }, [articles, search]);

    function openForm(article = null) {
        setEditingId(article?.id ?? null);
        setForm(
            article
                ? { designation: article.designation, unite: article.unite_id, presentation: article.presentation_raw, seuil: article.seuil, qte_max: article.qte_max ?? '', statut: article.statut === 'Actif' ? 1 : 0 }
                : { ...emptyForm },
        );
        setErrors({});
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        const payload = { ...form, qte_max: form.qte_max === '' ? null : form.qte_max };

        try {
            const { data } = editingId ? await api.put(`/pharma/articles/${editingId}`, payload) : await api.post('/pharma/articles', payload);
            setMessage({ type: 'success', text: data.message });
            setForm(null);
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setErrors({ _general: errorMessage(error) });
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleDelete() {
        try {
            const { data } = await api.delete(`/pharma/articles/${toDelete.id}`);
            setMessage({ type: 'success', text: data.message });
            load();
        } catch (error) {
            setMessage({ type: 'error', text: errorMessage(error) });
        } finally {
            setToDelete(null);
        }
    }

    async function showLots(article) {
        try {
            const { data } = await api.get(`/pharma/articles/${article.id}/stock`);
            setLots({ titre: `${data.article.designation} (marge ${data.taux} %)`, data: data.data });
        } catch (error) {
            setMessage({ type: 'error', text: errorMessage(error) });
        }
    }

    const update = (name) => (event) => setForm({ ...form, [name]: event.target.value === '' ? '' : Number(event.target.value) });

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Articles et stock</h1>
            <Alert type={message.type}>{message.text}</Alert>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-slate-600">
                    Taux de marge appliqué : <strong>{taux ?? '—'} %</strong>
                </p>
                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput value={search} onChange={setSearch} placeholder="Rechercher un article…" />
                    <Button onClick={() => openForm()}>Nouvel article</Button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Référence', 'Désignation', 'Présentation', 'Stock', 'Lots dispo.', 'Seuil', 'CMUP', 'Valeur stock', 'Statut'].map((label) => (
                                <th key={label} className="px-4 py-3 font-medium">
                                    {label}
                                </th>
                            ))}
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan="10" className="px-4 py-6 text-center text-slate-500">
                                    {loading ? 'Chargement…' : articles.length === 0 ? 'Aucun article.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((article) => (
                            <tr key={article.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-mono text-xs font-semibold">{article.reference}</td>
                                <td className="px-4 py-3 font-medium">{article.designation}</td>
                                <td className="px-4 py-3">{article.presentation}</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${alerteStyles[article.alerte]}`}>
                                        {article.stock_boites} · {article.stock_unites} u.
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <button
                                        type="button"
                                        title="Voir les lots"
                                        aria-label="Voir les lots"
                                        disabled={article.nb_lots === 0}
                                        onClick={() => showLots(article)}
                                        className={`${pill} ${article.nb_lots > 0 ? 'bg-sky-50 text-sky-700 ring-sky-600/20 hover:opacity-80' : 'cursor-default bg-slate-100 text-slate-400 ring-slate-500/20'}`}
                                    >
                                        {article.nb_lots}
                                    </button>
                                </td>
                                <td className="px-4 py-3">{article.seuil}</td>
                                <td className="px-4 py-3 font-mono text-xs">{article.cmup ? montant(article.cmup, 2) : '—'}</td>
                                <td className="px-4 py-3 font-mono text-xs">{article.valeur_stock ? montant(article.valeur_stock, 2) : '—'}</td>
                                <td className="px-4 py-3">{article.statut}</td>
                                <td className="space-x-1 px-4 py-2 text-right whitespace-nowrap">
                                    <EditButton onClick={() => openForm(article)} label={`Modifier ${article.designation}`} />
                                    {article.peut_supprimer && <DeleteButton onClick={() => setToDelete(article)} label={`Supprimer ${article.designation}`} />}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal
                open={form !== null}
                title="Article"
                subtitle="Nouvel article ou mise à jour de la présentation, du seuil et du statut."
                onClose={() => setForm(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setForm(null)}>
                            Annuler
                        </Button>
                        <Button type="submit" form="form-article" disabled={submitting}>
                            {submitting ? '…' : 'Enregistrer'}
                        </Button>
                    </>
                }
            >
                {form && (
                    <form id="form-article" onSubmit={handleSubmit} className="flex flex-col gap-4">
                        <Alert>{errors._general}</Alert>
                        <Field label="Désignation" name="designation" required maxLength={100} value={form.designation} onChange={(event) => setForm({ ...form, designation: event.target.value })} error={errors.designation} />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Unité de vente" name="unite" as="select" required value={form.unite} onChange={update('unite')} error={errors.unite}>
                                <option value="">— Choisir —</option>
                                {unites.map((unite) => (
                                    <option key={unite.id} value={unite.id}>
                                        {unite.libelle}
                                    </option>
                                ))}
                            </Field>
                            <div>
                                <Field label="Présentation (unités / présentation)" name="presentation" type="number" min="1" required value={form.presentation} onChange={update('presentation')} error={errors.presentation} />
                                <p className="mt-1 text-xs text-slate-500">Ex. boîte de 10 → 10</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Seuil d'alerte (en unités)" name="seuil" type="number" min="1" required value={form.seuil} onChange={update('seuil')} error={errors.seuil} />
                            <Field label="Statut commercialisé" name="statut" as="select" value={form.statut} onChange={update('statut')} error={errors.statut}>
                                <option value={1}>Actif</option>
                                <option value={0}>Inactif</option>
                            </Field>
                        </div>
                        <div>
                            <Field label="Stock cible / réappro (facultatif)" name="qte_max" type="number" min="1" placeholder="Ex. : 60 (quantité visée lors d'une commande)" value={form.qte_max} onChange={update('qte_max')} error={errors.qte_max} />
                            <p className="mt-1 text-xs text-slate-500">Si vide, la suggestion utilise 2 × seuil.</p>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal open={lots !== null} size="lg" title="Détail du stock par lot" subtitle="Répartition de la disponibilité par lot, avec prix et péremption." onClose={() => setLots(null)}>
                {lots && (
                    <>
                        <h3 className="mb-3 text-sm font-semibold">{lots.titre}</h3>
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="text-left text-slate-600">
                                <tr>
                                    {['Lot', 'Restant', 'PU Vente', 'Péremption', 'Entrée'].map((label) => (
                                        <th key={label} className="py-2 pr-4 font-medium">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lots.data.length === 0 && (
                                    <tr>
                                        <td colSpan="5" className="py-4 text-center text-slate-500">
                                            Aucun lot disponible.
                                        </td>
                                    </tr>
                                )}
                                {lots.data.map((lot, index) => (
                                    <tr key={index}>
                                        <td className="py-2 pr-4 font-mono text-xs">{lot.lot}</td>
                                        <td className="py-2 pr-4">
                                            {lot.stock_restant_boites} · {lot.stock_restant_unites} u.
                                        </td>
                                        <td className="py-2 pr-4">{montant(lot.prix_unitaire, 2)}</td>
                                        <td className="py-2 pr-4">
                                            <span className={`${pill} ${peremptionStyles[lot.peremption_statut]}`} title={peremptionLabels[lot.peremption_statut]}>
                                                {lot.date_peremption ? formatDate(lot.date_peremption) : '—'}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 text-xs text-slate-500">{formatDate(lot.date_entree)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                )}
            </Modal>

            <ConfirmDialog open={toDelete !== null} message="Confirmer la suppression de cet article ?" confirmLabel="Supprimer" onConfirm={handleDelete} onCancel={() => setToDelete(null)} />
        </div>
    );
}
