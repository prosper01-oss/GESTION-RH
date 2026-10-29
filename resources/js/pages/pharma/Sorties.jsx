import { useCallback, useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import ArticleSelect from '../../components/ArticleSelect';
import Button from '../../components/Button';
import { UndoButton } from '../../components/IconButton';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, formatDateHeure, montant, motifSortieLabels } from '../../services/pharma';

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
const motifStyle = (motif) => (['perte', 'casse', 'perime'].includes(motif) ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20');

export default function Sorties() {
    const [sorties, setSorties] = useState([]);
    const [articles, setArticles] = useState([]);
    const [active, setActive] = useState(null);
    const [lignes, setLignes] = useState([]);
    const [lots, setLots] = useState([]);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({ type: 'error', text: '' });
    const [formIndex, setFormIndex] = useState(null);
    const [formLigne, setFormLigne] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [ligneToDelete, setLigneToDelete] = useState(null);

    const fail = (error) => setMessage({ type: 'error', text: errorMessage(error) });

    const loadSorties = useCallback(() => {
        setLoading(true);
        return api
            .get('/pharma/sorties')
            .then(({ data }) => setSorties(data.data))
            .catch(fail)
            .finally(() => setLoading(false));
    }, []);

    const loadLignes = useCallback((sortie) => api.get(`/pharma/sorties/${sortie.id}/lignes`).then(({ data }) => setLignes(data.data)).catch(fail), []);

    useEffect(() => {
        loadSorties();
    }, [loadSorties]);

    async function chooseSortie(sortie) {
        setActive(sortie);
        await loadLignes(sortie);
    }

    async function submitIndex(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const { data } = await api.post('/pharma/sorties', formIndex);
            setMessage({ type: 'success', text: data.message });
            setFormIndex(null);
            await loadSorties();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                fail(error);
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function openLigne() {
        if (articles.length === 0) {
            try {
                setArticles((await api.get('/pharma/stock/articles')).data.data);
            } catch (error) {
                fail(error);

                return;
            }
        }
        setFormLigne({ article_id: '', entree_detail_id: '', qte_sortie: 1 });
        setLots([]);
        setErrors({});
    }

    async function chooseArticle(article) {
        setFormLigne({ ...formLigne, article_id: article.id, entree_detail_id: '' });
        setLots([]);
        try {
            setLots((await api.get('/pharma/sortie-lots', { params: { article_id: article.id } })).data.data);
        } catch (error) {
            fail(error);
        }
    }

    const lotChoisi = lots.find((lot) => lot.id === Number(formLigne?.entree_detail_id));
    const quantiteTropGrande = lotChoisi ? Number(formLigne.qte_sortie) > lotChoisi.restant : false;

    async function submitLigne(event) {
        event.preventDefault();
        if (quantiteTropGrande) {
            return;
        }
        setSubmitting(true);
        setErrors({});
        try {
            const { data } = await api.post(`/pharma/sorties/${active.id}/lignes`, { entree_detail_id: formLigne.entree_detail_id, qte_sortie: formLigne.qte_sortie });
            setMessage({ type: 'success', text: data.message });
            setFormLigne(null);
            await Promise.all([loadLignes(active), loadSorties()]);
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422 || !error.response.data.errors) {
                setErrors({ _general: errorMessage(error) });
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function deleteLigne() {
        try {
            const { data } = await api.delete(`/pharma/sortie-lignes/${ligneToDelete.id}`);
            setMessage({ type: 'success', text: data.message });
            await Promise.all([loadLignes(active), loadSorties()]);
        } catch (error) {
            fail(error);
        } finally {
            setLigneToDelete(null);
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Sorties de stock (ajustements, pertes)</h1>
            <Alert type={message.type}>{message.text}</Alert>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-7">
                <div className="rounded-lg border border-slate-200 bg-white xl:col-span-2">
                    <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Sorties</h2>
                        <Button className="!px-3 !py-1.5 text-xs" onClick={() => { setFormIndex({ ref_sortie: '', motif: 'ajustement' }); setErrors({}); }}>
                            + Nouvelle
                        </Button>
                    </div>
                    <ul className="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                        {sorties.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">{loading ? 'Chargement…' : 'Aucune sortie.'}</li>}
                        {sorties.map((sortie) => (
                            <li key={sortie.id}>
                                <button type="button" onClick={() => chooseSortie(sortie)} className={`w-full px-4 py-3 text-left hover:bg-slate-50 ${active?.id === sortie.id ? 'bg-sky-50' : ''}`}>
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono text-xs font-semibold">{sortie.ref_sortie}</span>
                                        <span className={`${pill} ${motifStyle(sortie.motif)}`}>{motifSortieLabels[sortie.motif] ?? sortie.motif}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{sortie.nb_article} ligne(s) · Perte {montant(sortie.montant_perte, 2)}</p>
                                    <p className="mt-0.5 text-xs text-slate-400">{formatDateHeure(sortie.created_at)}</p>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="xl:col-span-5">
                    {!active && <div className="flex h-full min-h-64 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm text-slate-500">Sélectionnez une sortie à gauche.</div>}
                    {active && (
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">{active.ref_sortie} — {motifSortieLabels[active.motif] ?? active.motif}</h2>
                                    <p className="text-xs text-slate-500">Sélection FEFO conseillée : le premier lot périmé sort en premier.</p>
                                </div>
                                <Button onClick={openLigne}>Ajouter une ligne</Button>
                            </div>

                            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-left text-slate-600">
                                        <tr>
                                            {['Réf.', 'Article', 'Lot', 'Qté sortie', 'Restant lot', 'Montant perte'].map((label) => (
                                                <th key={label} className="px-4 py-3 font-medium">{label}</th>
                                            ))}
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {lignes.length === 0 && (
                                            <tr>
                                                <td colSpan="7" className="px-4 py-6 text-center text-slate-500">Aucune ligne.</td>
                                            </tr>
                                        )}
                                        {lignes.map((ligne) => (
                                            <tr key={ligne.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3 font-mono text-xs">{ligne.reference}</td>
                                                <td className="px-4 py-3 font-medium">{ligne.designation}</td>
                                                <td className="px-4 py-3 font-mono text-xs">{ligne.lot}</td>
                                                <td className="px-4 py-3">{ligne.qte_sortie_boites} · {ligne.qte_sortie_unites} u.</td>
                                                <td className="px-4 py-3">{ligne.stock_restant_lot_boites} · {ligne.stock_dispo_boites}</td>
                                                <td className="px-4 py-3 font-semibold text-red-600">{montant(ligne.montant_perte, 2)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <UndoButton onClick={() => setLigneToDelete(ligne)} label="Annuler la ligne et restituer le stock" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <Modal
                open={formIndex !== null}
                title="Nouvelle sortie"
                subtitle="Référence et motif de l'ajustement ou de la perte."
                onClose={() => setFormIndex(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFormIndex(null)}>Annuler</Button>
                        <Button type="submit" form="form-sortie" disabled={submitting}>Enregistrer</Button>
                    </>
                }
            >
                {formIndex && (
                    <form id="form-sortie" onSubmit={submitIndex} className="flex flex-col gap-4">
                        <Field label="Référence" name="ref_sortie" required maxLength={20} placeholder="SOR-001" value={formIndex.ref_sortie} onChange={(e) => setFormIndex({ ...formIndex, ref_sortie: e.target.value })} error={errors.ref_sortie} />
                        <Field label="Motif" name="motif" as="select" required value={formIndex.motif} onChange={(e) => setFormIndex({ ...formIndex, motif: e.target.value })} error={errors.motif}>
                            <option value="ajustement">Ajustement d'inventaire</option>
                            <option value="perte">Perte</option>
                            <option value="casse">Casse</option>
                            <option value="perime">Péremption</option>
                        </Field>
                    </form>
                )}
            </Modal>

            <Modal
                open={formLigne !== null}
                title="Ligne de sortie"
                subtitle="Sélection FEFO : le lot le plus proche de la péremption sort en premier."
                onClose={() => setFormLigne(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFormLigne(null)}>Annuler</Button>
                        <Button type="submit" form="form-ligne-sortie" disabled={submitting || quantiteTropGrande}>Sortir du stock</Button>
                    </>
                }
            >
                {formLigne && (
                    <form id="form-ligne-sortie" onSubmit={submitLigne} className="flex flex-col gap-4">
                        <Alert>{errors._general}</Alert>
                        <ArticleSelect articles={articles} value={formLigne.article_id} onChange={chooseArticle} />
                        <Field label="Lot disponible (ordre FEFO)" name="entree_detail_id" as="select" required disabled={lots.length === 0} value={formLigne.entree_detail_id} onChange={(e) => setFormLigne({ ...formLigne, entree_detail_id: Number(e.target.value) })} error={errors.entree_detail_id}>
                            <option value="">— Choisir un article d'abord —</option>
                            {lots.map((lot) => (
                                <option key={lot.id} value={lot.id}>
                                    {lot.lot} — reste {lot.restant} u. — pér. {lot.date_peremption ? formatDate(lot.date_peremption) : '—'}
                                    {lot.perime ? ' ⚠ PÉRIMÉ' : ''}
                                </option>
                            ))}
                        </Field>
                        <div>
                            <Field label="Quantité à sortir (unités)" name="qte_sortie" type="number" min="1" step="1" required value={formLigne.qte_sortie} onChange={(e) => setFormLigne({ ...formLigne, qte_sortie: Number(e.target.value) })} error={errors.qte_sortie} />
                            {quantiteTropGrande && <p className="mt-1 text-xs text-red-600">La quantité dépasse le restant du lot sélectionné.</p>}
                        </div>
                    </form>
                )}
            </Modal>

            <ConfirmDialog open={ligneToDelete !== null} message="Annuler cette ligne et restaurer le stock ?" confirmLabel="Restituer" onConfirm={deleteLigne} onCancel={() => setLigneToDelete(null)} />
        </div>
    );
}
