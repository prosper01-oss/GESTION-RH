import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import ArticleSelect from '../../components/ArticleSelect';
import Button from '../../components/Button';
import { DeleteButton, EditButton } from '../../components/IconButton';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import PriceField from '../../components/PriceField';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, formatDateHeure, montant } from '../../services/pharma';

const emptyIndex = { ref_entree: '', motif: '', fournisseur_id: '' };
const emptyLot = { article_id: '', qte_entree: 1, prix_achat_boite: '', date_peremption: '' };

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
const peremptionStyles = {
    ok: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    proche: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    urgent: 'bg-orange-50 text-orange-700 ring-orange-600/20',
    perime: 'bg-red-50 text-red-700 ring-red-600/20',
    null: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

const peremptionTexte = (lot) => (!lot ? '—' : lot.peremption_statut === 'perime' ? `Périmé ${formatDate(lot.date_peremption)}` : lot.date_peremption ? formatDate(lot.date_peremption) : '—');

function genererRefReappro() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');

    return `REA-${p(d.getFullYear() % 100)}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

export default function Entrees() {
    const [searchParams, setSearchParams] = useSearchParams();
    const [indexes, setIndexes] = useState([]);
    const [taux, setTaux] = useState(null);
    const [fournisseurs, setFournisseurs] = useState([]);
    const [articles, setArticles] = useState([]);
    const [active, setActive] = useState(null);
    const [lots, setLots] = useState([]);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({ type: 'error', text: '' });

    const [formIndex, setFormIndex] = useState(null);
    const [formLot, setFormLot] = useState(null);
    const [editLotId, setEditLotId] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [lotToValidate, setLotToValidate] = useState(null);
    const [lotToCancel, setLotToCancel] = useState(null);
    const [lotToDelete, setLotToDelete] = useState(null);
    const [indexToDelete, setIndexToDelete] = useState(null);
    const fileInput = useRef(null);
    const reapproDone = useRef(false);

    const fail = (error) => setMessage({ type: 'error', text: errorMessage(error) });
    const ok = (text) => setMessage({ type: 'success', text });

    const loadIndexes = useCallback(async () => {
        setLoading(true);
        try {
            const [liste, fournisseursReponse, articlesReponse] = await Promise.all([api.get('/pharma/entrees'), api.get('/pharma/fournisseurs/options').catch(() => ({ data: { data: [] } })), api.get('/pharma/stock/articles')]);
            setIndexes(liste.data.data);
            setTaux(liste.data.taux);
            setFournisseurs(fournisseursReponse.data.data);
            setArticles(articlesReponse.data.data);

            return { indexes: liste.data.data, articles: articlesReponse.data.data };
        } catch (error) {
            fail(error);

            return null;
        } finally {
            setLoading(false);
        }
    }, []);

    const loadLots = useCallback(async (entree) => {
        try {
            const { data } = await api.get(`/pharma/entrees/${entree.id}/lots`);
            setLots(data.data);
        } catch (error) {
            fail(error);
        }
    }, []);

    async function chooseEntree(entree) {
        setActive(entree);
        await loadLots(entree);
    }

    // Lien « Réapprovisionner » : ?article=ID&qte=N crée une entrée et ouvre le formulaire de lot pré-rempli.
    useEffect(() => {
        loadIndexes().then(async (loaded) => {
            const articleId = searchParams.get('article');
            if (!loaded || !articleId || reapproDone.current) {
                return;
            }
            reapproDone.current = true;
            const article = loaded.articles.find((item) => Number(item.id) === Number(articleId));
            if (!article) {
                fail({ response: { data: { message: 'Article introuvable.' } } });

                return;
            }

            let entreeId = null;
            for (let i = 0; i < 5 && !entreeId; i++) {
                try {
                    const { data } = await api.post('/pharma/entrees', { ref_entree: genererRefReappro() + (i ? `-${i}` : ''), motif: 'Réapprovisionnement' });
                    entreeId = data.id;
                } catch {
                    entreeId = null;
                }
            }
            if (!entreeId) {
                fail({ response: { data: { message: "Échec de la création de l'entrée." } } });

                return;
            }

            const refreshed = await loadIndexes();
            const entree = refreshed?.indexes.find((item) => item.id === entreeId);
            if (entree) {
                await chooseEntree(entree);
            }
            const qte = Number(searchParams.get('qte'));
            setFormLot({ ...emptyLot, article_id: article.id, qte_entree: qte > 0 ? qte : 1 });
            setSearchParams({}, { replace: true });
        });
    }, []);

    async function submitIndex(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            const { data } = await api.post('/pharma/entrees', { ...formIndex, fournisseur_id: formIndex.fournisseur_id || null });
            ok(data.message);
            setFormIndex(null);
            await loadIndexes();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                fail(error);
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function deleteIndex() {
        try {
            const { data } = await api.delete(`/pharma/entrees/${indexToDelete.id}`);
            ok(data.message);
            if (active?.id === indexToDelete.id) {
                setActive(null);
                setLots([]);
            }
            await loadIndexes();
        } catch (error) {
            fail(error);
        } finally {
            setIndexToDelete(null);
        }
    }

    function openLot(lot = null) {
        setEditLotId(lot?.id ?? null);
        setFormLot(lot ? { article_id: lot.article_id ?? '', qte_entree: lot.qte_entree_unites, prix_achat_boite: String(lot.prix_achat_boite), date_peremption: lot.date_peremption ?? '' } : { ...emptyLot });
        setErrors({});
    }

    async function submitLot(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        const payload = { ...formLot, date_peremption: formLot.date_peremption || null };
        try {
            const { data } = editLotId ? await api.put(`/pharma/entree-lots/${editLotId}`, payload) : await api.post(`/pharma/entrees/${active.id}/lots`, payload);
            ok(data.message);
            setFormLot(null);
            await Promise.all([loadLots(active), loadIndexes()]);
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422 || !error.response.data.errors) {
                setErrors({ _general: errorMessage(error) });
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function lotAction(request, close) {
        try {
            const { data } = await request();
            ok(data.message);
            await Promise.all([loadLots(active), loadIndexes()]);
        } catch (error) {
            fail(error);
        } finally {
            close(null);
        }
    }

    async function importExcel(event) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file || !active) {
            return;
        }
        const body = new FormData();
        body.append('file', file);
        try {
            const { data } = await api.post(`/pharma/entrees/${active.id}/import`, body, { headers: { 'Content-Type': 'multipart/form-data' } });
            const ignorees = data.ignorees?.length ? ` — ${data.ignorees.length} ligne(s) ignorée(s).` : '';
            setMessage({ type: data.importes > 0 ? 'success' : 'error', text: data.message + ignorees });
            await Promise.all([loadLots(active), loadIndexes()]);
        } catch (error) {
            fail(error);
        }
    }

    const setLot = (name) => (event) => setFormLot({ ...formLot, [name]: event.target.value });

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Entrées de stock</h1>
            <Alert type={message.type}>{message.text}</Alert>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-7">
                {/* En-têtes d'entrée */}
                <div className="rounded-lg border border-slate-200 bg-white xl:col-span-2">
                    <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Entrées</h2>
                        <Button className="!px-3 !py-1.5 text-xs" onClick={() => { setFormIndex({ ...emptyIndex }); setErrors({}); }}>
                            + Nouvelle
                        </Button>
                    </div>
                    <ul className="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                        {indexes.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">{loading ? 'Chargement…' : 'Aucune entrée.'}</li>}
                        {indexes.map((entree) => (
                            <li key={entree.id}>
                                <button type="button" onClick={() => chooseEntree(entree)} className={`w-full px-4 py-3 text-left hover:bg-slate-50 ${active?.id === entree.id ? 'bg-sky-50' : ''}`}>
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono text-xs font-semibold">{entree.ref_entree}</span>
                                        <span className={`${pill} bg-slate-100 text-slate-600 ring-slate-500/20`}>{entree.motif}</span>
                                    </div>
                                    {entree.fournisseur && <p className="mt-1 text-xs font-medium text-sky-700">{entree.fournisseur}</p>}
                                    <p className="mt-1 text-xs text-slate-500">
                                        {entree.nb_article} lot(s) · Achat {montant(entree.montant_achat, 2)} · Estimé {montant(entree.montant_estime, 2)}
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-400">{formatDateHeure(entree.created_at)}</p>
                                </button>
                                {entree.peut_supprimer && (
                                    <div className="flex justify-end px-4 pb-2">
                                        <DeleteButton onClick={() => setIndexToDelete(entree)} label="Supprimer l'entrée" />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Lots de l'entrée active */}
                <div className="xl:col-span-5">
                    {!active && <div className="flex h-full min-h-64 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm text-slate-500">Sélectionnez une entrée à gauche.</div>}
                    {active && (
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">{active.ref_entree}</h2>
                                    <p className="text-xs text-slate-500">
                                        {active.fournisseur ? `Fournisseur : ${active.fournisseur}` : `Motif : ${active.motif}`} — taux {taux} %
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <input ref={fileInput} type="file" accept=".xlsx,.xls" className="hidden" onChange={importExcel} />
                                    <Button variant="secondary" onClick={() => fileInput.current.click()}>
                                        Import Excel
                                    </Button>
                                    <Button onClick={() => openLot()}>Ajouter un lot</Button>
                                </div>
                            </div>

                            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-left text-slate-600">
                                        <tr>
                                            {['Article', 'Lot', 'Qté entrée', 'Restant', 'Prix achat', 'PU vente', 'Péremption'].map((label) => (
                                                <th key={label} className="px-4 py-3 font-medium">{label}</th>
                                            ))}
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {lots.length === 0 && (
                                            <tr>
                                                <td colSpan="8" className="px-4 py-6 text-center text-slate-500">Aucun lot. Ajoutez des lignes ou importez un fichier Excel.</td>
                                            </tr>
                                        )}
                                        {lots.map((lot) => (
                                            <tr key={lot.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3 font-medium">{lot.article}</td>
                                                <td className="px-4 py-3 font-mono text-xs">{lot.lot}</td>
                                                <td className="px-4 py-3">{lot.qte_entree_boites} · {lot.qte_entree_unites} u.</td>
                                                <td className="px-4 py-3">{lot.isValide ? `${lot.stock_restant_lot_boites} · ${lot.stock_restant_lot_unites} u.` : '—'}</td>
                                                <td className="px-4 py-3">{montant(lot.prix_achat_boite, 2)}</td>
                                                <td className="px-4 py-3">{montant(lot.prix_unitaire, 2)}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`${pill} ${peremptionStyles[lot.peremption_statut]}`}>{peremptionTexte(lot)}</span>
                                                </td>
                                                <td className="space-x-2 px-4 py-3 text-right whitespace-nowrap">
                                                    {lot.peut_valider && <button type="button" onClick={() => setLotToValidate(lot)} className={`${pill} bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100`}>Valider</button>}
                                                    {lot.peut_annuler_validation && <button type="button" onClick={() => setLotToCancel(lot)} className={`${pill} bg-amber-50 text-amber-700 ring-amber-600/20 hover:bg-amber-100`}>Annuler val.</button>}
                                                    {lot.est_utilise && <span title="Lot déjà utilisé" className={`${pill} cursor-not-allowed bg-slate-100 text-slate-400 ring-slate-500/20`}>Annuler val.</span>}
                                                    {lot.peut_modifier && <EditButton onClick={() => openLot(lot)} label="Modifier le lot" />}
                                                    {lot.peut_supprimer && <DeleteButton onClick={() => setLotToDelete(lot)} label="Supprimer le lot" />}
                                                    {lot.source === 'import' && <span title="Lot importé par Excel : lecture seule" className={`${pill} bg-slate-100 text-slate-400 ring-slate-500/20`}>Importé</span>}
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

            {/* Nouvelle entrée */}
            <Modal
                open={formIndex !== null}
                title="Nouvelle entrée d'approvisionnement"
                subtitle="Référence interne et motif / fournisseur."
                onClose={() => setFormIndex(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFormIndex(null)}>Annuler</Button>
                        <Button type="submit" form="form-entree" disabled={submitting}>Enregistrer</Button>
                    </>
                }
            >
                {formIndex && (
                    <form id="form-entree" onSubmit={submitIndex} className="flex flex-col gap-4">
                        <Field label="Référence" name="ref_entree" required maxLength={20} placeholder="ENT-001" value={formIndex.ref_entree} onChange={(e) => setFormIndex({ ...formIndex, ref_entree: e.target.value })} error={errors.ref_entree} />
                        <Field label="Fournisseur" name="fournisseur_id" as="select" value={formIndex.fournisseur_id} onChange={(e) => setFormIndex({ ...formIndex, fournisseur_id: e.target.value })} error={errors.fournisseur_id}>
                            <option value="">— Aucun —</option>
                            {fournisseurs.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
                        </Field>
                        <Field label="Motif / remarque" name="motif" maxLength={100} value={formIndex.motif} onChange={(e) => setFormIndex({ ...formIndex, motif: e.target.value })} error={errors.motif} />
                    </form>
                )}
            </Modal>

            {/* Lot */}
            <Modal
                open={formLot !== null}
                size="lg"
                title="Lot d'approvisionnement"
                subtitle="Quantité, prix d'achat et péremption du lot."
                onClose={() => setFormLot(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFormLot(null)}>Annuler</Button>
                        <Button type="submit" form="form-lot" disabled={submitting}>Enregistrer</Button>
                    </>
                }
            >
                {formLot && (
                    <form id="form-lot" onSubmit={submitLot} className="flex flex-col gap-4">
                        <Alert>{errors._general}</Alert>
                        <ArticleSelect articles={articles} value={formLot.article_id} disabled={editLotId !== null} onChange={(article) => setFormLot({ ...formLot, article_id: article.id })} error={errors.article_id} />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Qté entrée (unités)" name="qte_entree" type="number" min="1" step="1" required value={formLot.qte_entree} onChange={setLot('qte_entree')} error={errors.qte_entree} />
                            <PriceField decimal label="Prix achat boîte (Ar)" name="prix_achat_boite" required value={formLot.prix_achat_boite} onChange={(value) => setFormLot({ ...formLot, prix_achat_boite: value })} error={errors.prix_achat_boite} />
                        </div>
                        <Field label="Date de péremption (facultative)" name="date_peremption" type="date" value={formLot.date_peremption} onChange={setLot('date_peremption')} error={errors.date_peremption} />
                        <Alert type="info">PU Vente = PU achat unitaire × (1 + {taux}/100).</Alert>
                    </form>
                )}
            </Modal>

            {/* Validation d'un lot */}
            <Modal
                open={lotToValidate !== null}
                title="Valider le lot"
                subtitle="Confirmez l'intégration de ce lot au stock."
                onClose={() => setLotToValidate(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setLotToValidate(null)}>Annuler</Button>
                        <Button onClick={() => lotAction(() => api.post(`/pharma/entree-lots/${lotToValidate.id}/valider`), setLotToValidate)}>Valider & intégrer</Button>
                    </>
                }
            >
                {lotToValidate && (
                    <div className="flex flex-col gap-3 text-sm">
                        <Alert type="info">
                            <p><span className="text-slate-500">Médicament :</span> <strong>{lotToValidate.article}</strong></p>
                            <p><span className="text-slate-500">Quantité à entrer :</span> <strong>{lotToValidate.qte_entree_boites} · {lotToValidate.qte_entree_unites} u.</strong></p>
                            <p><span className="text-slate-500">Lot :</span> <span className="font-mono text-xs">{lotToValidate.lot}</span></p>
                            <p><span className="text-slate-500">Péremption :</span> <span className={`${pill} ${peremptionStyles[lotToValidate.peremption_statut]}`}>{peremptionTexte(lotToValidate)}</span></p>
                        </Alert>
                        <p className="text-xs text-slate-500">Valider ce lot et l'ajouter au stock ?</p>
                    </div>
                )}
            </Modal>

            <ConfirmDialog open={lotToCancel !== null} variant="primary" message="Annuler la validation et retirer du stock ?" confirmLabel="Annuler la validation" onConfirm={() => lotAction(() => api.post(`/pharma/entree-lots/${lotToCancel.id}/annuler-validation`), setLotToCancel)} onCancel={() => setLotToCancel(null)} />
            <ConfirmDialog open={lotToDelete !== null} message="Supprimer ce lot ?" confirmLabel="Supprimer" onConfirm={() => lotAction(() => api.delete(`/pharma/entree-lots/${lotToDelete.id}`), setLotToDelete)} onCancel={() => setLotToDelete(null)} />
            <ConfirmDialog open={indexToDelete !== null} message="Confirmer la suppression de cette entrée ?" confirmLabel="Supprimer" onConfirm={deleteIndex} onCancel={() => setIndexToDelete(null)} />
        </div>
    );
}
