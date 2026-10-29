import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, inventaireStatutLabels } from '../../services/pharma';

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
const statutStyles = {
    applique: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    valide: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    annule: 'bg-slate-100 text-slate-500 ring-slate-500/20',
    brouillon: 'bg-sky-50 text-sky-700 ring-sky-600/20',
};
const ecartStyle = (ecart) => (ecart > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : ecart < 0 ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/20');
const signe = (n) => `${n > 0 ? '+' : ''}${n}`;
const today = () => new Date().toISOString().slice(0, 10);

export default function Inventaire() {
    const [seances, setSeances] = useState([]);
    const [selected, setSelected] = useState(null);
    const [detail, setDetail] = useState(null);
    const [lignes, setLignes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState({ type: 'error', text: '' });
    const [formNouveau, setFormNouveau] = useState(null);
    const [errors, setErrors] = useState({});
    const [confirm, setConfirm] = useState(null);

    const fail = (error) => setMessage({ type: 'error', text: errorMessage(error) });

    const loadDetail = useCallback(async (id) => {
        setSelected(id);
        try {
            const { data } = await api.get(`/pharma/inventaires/${id}`);
            setDetail(data.inventaire);
            setLignes(data.data);
        } catch (error) {
            fail(error);
        }
    }, []);

    const loadSeances = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/pharma/inventaires');
            setSeances(data.data);

            return data.data;
        } catch (error) {
            fail(error);

            return [];
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadSeances().then((liste) => liste.length > 0 && loadDetail(liste[0].id));
    }, [loadSeances, loadDetail]);

    const totaux = useMemo(() => {
        const theorique = lignes.reduce((somme, l) => somme + l.theorique, 0);
        const compte = lignes.reduce((somme, l) => somme + (l.compte ?? 0), 0);

        return { theorique, compte, ecart: compte - theorique };
    }, [lignes]);

    const aComptage = lignes.some((l) => l.compte !== null && l.compte !== '');

    async function create(event) {
        event.preventDefault();
        setBusy(true);
        setErrors({});
        try {
            const { data } = await api.post('/pharma/inventaires', { date_inventaire: formNouveau.date_inventaire, motif: formNouveau.motif || null });
            setMessage({ type: 'success', text: data.message });
            setFormNouveau(null);
            await loadSeances();
            await loadDetail(data.id);
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                fail(error);
            }
        } finally {
            setBusy(false);
        }
    }

    async function saveCount(ligne, value) {
        const compte = value === '' ? null : Number(value);
        setLignes((current) => current.map((l) => (l.id === ligne.id ? { ...l, compte, ecart: (compte ?? 0) - l.theorique } : l)));
        if (compte === null) {
            return;
        }
        try {
            await api.put(`/pharma/inventaire-lignes/${ligne.id}`, { compte });
        } catch (error) {
            fail(error);
            await loadDetail(selected);
        }
    }

    async function run(request, { reset = false } = {}) {
        setBusy(true);
        try {
            const { data } = await request();
            setMessage({ type: 'success', text: data.message });
            await loadSeances();
            if (reset) {
                setSelected(null);
                setDetail(null);
                setLignes([]);
            } else {
                await loadDetail(selected);
            }
        } catch (error) {
            fail(error);
        } finally {
            setBusy(false);
            setConfirm(null);
        }
    }

    const actions = {
        valider: { message: 'Verrouiller cet inventaire (fini au comptage) ?', label: 'Valider', variant: 'primary', run: () => run(() => api.post(`/pharma/inventaires/${selected}/valider`)) },
        appliquer: { message: 'Appliquer les écarts au stock ? Cette action est définitive.', label: 'Appliquer', variant: 'danger', run: () => run(() => api.post(`/pharma/inventaires/${selected}/appliquer`)) },
        annuler: { message: "Annuler cette séance d'inventaire ?", label: "Annuler l'inventaire", variant: 'danger', run: () => run(() => api.delete(`/pharma/inventaires/${selected}`), { reset: true }) },
    };

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Inventaire physique des stocks</h1>
            <Alert type={message.type}>{message.text}</Alert>

            <div className="grid gap-4 xl:grid-cols-3">
                <div className="flex max-h-[calc(100vh-190px)] flex-col overflow-hidden rounded-lg border border-slate-200 bg-white p-4">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Séances d'inventaire</h2>
                        <Button className="!px-3 !py-1.5 text-xs" onClick={() => { setFormNouveau({ date_inventaire: today(), motif: '' }); setErrors({}); }}>Nouvel inventaire</Button>
                    </div>
                    <div className="overflow-y-auto">
                        {seances.map((s) => (
                            <button key={s.id} type="button" onClick={() => loadDetail(s.id)} className={`mb-2 w-full rounded-lg border p-3 text-left hover:border-gold ${selected === s.id ? 'border-gold bg-sky-50' : 'border-slate-200 bg-white'}`}>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-semibold">{s.reference}</span>
                                    <span className={`${pill} ${statutStyles[s.statut]}`}>{inventaireStatutLabels[s.statut]}</span>
                                </div>
                                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>{formatDate(s.date)}</span>
                                    <span>{s.nb_articles} article(s)</span>
                                    {s.total_ecart !== 0 && <span className={`font-semibold ${s.total_ecart > 0 ? 'text-emerald-700' : 'text-red-700'}`}>Écart : {signe(s.total_ecart)}</span>}
                                    {s.user && <span className="italic">{s.user}</span>}
                                </div>
                                {s.motif && <p className="mt-1 text-xs text-slate-400">{s.motif}</p>}
                            </button>
                        ))}
                        {seances.length === 0 && <p className="py-8 text-center text-sm text-slate-500">{loading ? 'Chargement…' : "Aucune séance d'inventaire pour le moment."}</p>}
                    </div>
                </div>

                <div className="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2">
                    {!detail && <div className="flex min-h-64 items-center justify-center text-sm text-slate-500">Sélectionnez ou créez une séance d'inventaire.</div>}
                    {detail && (
                        <div>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="font-bold">{detail.reference}</h2>
                                        <span className={`${pill} ${statutStyles[detail.statut]}`}>{inventaireStatutLabels[detail.statut]}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">Date : {formatDate(detail.date)}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {detail.statut === 'brouillon' && <Button variant="secondary" disabled={!aComptage || busy} onClick={() => setConfirm('valider')}>Valider</Button>}
                                    {!['applique', 'annule'].includes(detail.statut) && <Button disabled={!aComptage || busy} onClick={() => setConfirm('appliquer')}>Appliquer au stock</Button>}
                                    {['brouillon', 'valide'].includes(detail.statut) && <Button variant="secondary" disabled={busy} onClick={() => setConfirm('annuler')}>Annuler</Button>}
                                </div>
                            </div>

                            {!['brouillon', 'annule'].includes(detail.statut) && (
                                <div className="mb-3 flex flex-wrap gap-3 text-xs font-semibold">
                                    <span className={`${pill} bg-slate-100 text-slate-600 ring-slate-500/20`}>Théorique : {totaux.theorique}</span>
                                    <span className={`${pill} bg-slate-100 text-slate-600 ring-slate-500/20`}>Compté : {totaux.compte}</span>
                                    <span className={`${pill} ${ecartStyle(totaux.ecart)}`}>Écart : {signe(totaux.ecart)}</span>
                                </div>
                            )}

                            <div className="overflow-x-auto rounded-lg border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-left text-slate-600">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Article</th>
                                            <th className="px-4 py-3 text-right font-medium">Théorique</th>
                                            <th className="px-4 py-3 text-right font-medium">Compté</th>
                                            <th className="px-4 py-3 text-right font-medium">Écart</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {lignes.length === 0 && (
                                            <tr>
                                                <td colSpan="4" className="px-4 py-8 text-center text-slate-500">Aucune ligne.</td>
                                            </tr>
                                        )}
                                        {lignes.map((ligne) => (
                                            <tr key={ligne.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-3">{ligne.designation}</td>
                                                <td className="px-4 py-3 text-right font-medium">{ligne.theorique}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {detail.statut === 'brouillon' ? (
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="1"
                                                            aria-label={`Quantité comptée — ${ligne.designation}`}
                                                            value={ligne.compte ?? ''}
                                                            onChange={(event) => setLignes((current) => current.map((l) => (l.id === ligne.id ? { ...l, compte: event.target.value === '' ? null : Number(event.target.value) } : l)))}
                                                            onBlur={(event) => event.target.value !== '' && saveCount(ligne, event.target.value)}
                                                            className="w-24 rounded-md border border-slate-300 bg-white px-2 py-1 text-right text-sm focus:ring-2 focus:ring-sky-200 focus:outline-none"
                                                        />
                                                    ) : (
                                                        (ligne.compte ?? '—')
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <span className={`${pill} ${ecartStyle(ligne.ecart)}`}>{signe(ligne.ecart)}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {detail.statut === 'brouillon' && (
                                <p className="mt-3 text-xs text-slate-500">
                                    Saisissez la quantité comptée pour les articles inventoriés (les autres restent tels quels). En <b>appliquant</b>, l'écart est recalculé sur le stock courant : un surplus crée un lot d'inventaire, un manquant est soustrait aux lots (FEFO).
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <Modal
                open={formNouveau !== null}
                title="Nouvel inventaire"
                onClose={() => setFormNouveau(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFormNouveau(null)}>Fermer</Button>
                        <Button type="submit" form="form-inventaire" disabled={busy}>Créer et commencer</Button>
                    </>
                }
            >
                {formNouveau && (
                    <form id="form-inventaire" onSubmit={create} className="flex flex-col gap-4">
                        <Field label="Date de l'inventaire" name="date_inventaire" type="date" required value={formNouveau.date_inventaire} onChange={(e) => setFormNouveau({ ...formNouveau, date_inventaire: e.target.value })} error={errors.date_inventaire} />
                        <Field label="Motif (facultatif)" name="motif" placeholder="Ex. : comptage mensuel" value={formNouveau.motif} onChange={(e) => setFormNouveau({ ...formNouveau, motif: e.target.value })} error={errors.motif} />
                    </form>
                )}
            </Modal>

            <ConfirmDialog open={confirm !== null} message={confirm && actions[confirm].message} confirmLabel={confirm && actions[confirm].label} variant={confirm ? actions[confirm].variant : 'primary'} busy={busy} onConfirm={() => actions[confirm].run()} onCancel={() => setConfirm(null)} />
        </div>
    );
}
