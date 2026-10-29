import { useCallback, useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Modal from '../../components/Modal';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import StatTile from '../../components/StatTile';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage } from '../../services/api';
import { DeleteButton } from '../../components/IconButton';
import { formatDateHeure, montant } from '../../services/pharma';

const today = () => new Date().toISOString().slice(0, 10);
const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';
const statutStyles = {
    en_attente: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    vu: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
};
const statutLabels = { en_attente: 'En attente', vu: 'Vu' };
const typeLabels = { generaliste: 'Généraliste', dentiste: 'Dentiste' };

const ordreStyles = {
    en_attente: 'bg-sky-50 text-sky-800 ring-sky-600/20',
    paye: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
};
const ordreLabels = { en_attente: 'En caisse', paye: 'Payé' };

const heure = (date) => (date ? new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—');

/**
 * Patients adressés par la caisse au docteur ou au dentiste connecté (ou à tous les docteurs pour un administrateur).
 */
export default function Consultations() {
    const { user } = useAuth();
    const [filters, setFilters] = useState({ date_debut: today(), date_fin: today(), statut: '' });
    const [rows, setRows] = useState([]);
    const [attente, setAttente] = useState(0);
    const [lie, setLie] = useState(true);
    const [message, setMessage] = useState({ type: 'error', text: '' });
    const [busy, setBusy] = useState(null);
    const [envoi, setEnvoi] = useState(null);
    const [actes, setActes] = useState(null);
    const [erreur, setErreur] = useState('');
    const [aAnnuler, setAAnnuler] = useState(null);

    const load = useCallback(
        (current = filters) =>
            api
                .get('/pharma/consultations', { params: Object.fromEntries(Object.entries(current).filter(([, value]) => value)) })
                .then(({ data }) => {
                    setRows(data.data);
                    setAttente(data.attente);
                    setLie(data.lie);
                })
                .catch((error) => setMessage({ type: 'error', text: errorMessage(error) })),
        [filters],
    );

    // La file se rafraîchit toute seule : un nouveau patient payé en caisse apparaît sans recharger la page.
    useEffect(() => {
        load();
        const timer = setInterval(load, 30000);

        return () => clearInterval(timer);
    }, [load]);

    async function markSeen(consultation) {
        setBusy(consultation.id);
        try {
            const { data } = await api.post(`/pharma/consultations/${consultation.id}/vu`);
            setMessage({ type: 'success', text: `${data.message} (${consultation.patient})` });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: errorMessage(error) });
        } finally {
            setBusy(null);
        }
    }

    async function openEnvoi(consultation) {
        setErreur('');
        setEnvoi({ consultation, choix: {}, note: '' });
        if (actes === null) {
            try {
                setActes((await api.get('/pharma/actes-dentaires')).data.data);
            } catch (error) {
                setActes([]);
                setErreur(errorMessage(error));
            }
        }
    }

    function toggleActe(acte) {
        setEnvoi((current) => {
            const choix = { ...current.choix };
            if (choix[acte.id]) {
                delete choix[acte.id];
            } else {
                choix[acte.id] = 1;
            }

            return { ...current, choix };
        });
    }

    const totalEnvoi = envoi ? (actes ?? []).reduce((somme, acte) => somme + (envoi.choix[acte.id] ?? 0) * acte.prix, 0) : 0;

    async function sendToCashDesk(event) {
        event.preventDefault();
        const lignes = Object.entries(envoi.choix).map(([service_id, qte]) => ({ service_id: Number(service_id), qte }));
        setBusy('envoi');
        setErreur('');
        try {
            const { data } = await api.post(`/pharma/consultations/${envoi.consultation.id}/ordre-soins`, { lignes, note: envoi.note || null });
            setMessage({ type: 'success', text: `${data.message} (${envoi.consultation.patient} — ${montant(data.total, 0)} Ar)` });
            setEnvoi(null);
            await load();
        } catch (error) {
            setErreur(Object.values(error.response?.data?.errors ?? {})[0]?.[0] ?? errorMessage(error));
        } finally {
            setBusy(null);
        }
    }

    async function cancelOrder() {
        try {
            const { data } = await api.delete(`/pharma/ordres-soins/${aAnnuler.id}`);
            setMessage({ type: 'success', text: data.message });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: errorMessage(error) });
        } finally {
            setAAnnuler(null);
        }
    }

    const admin = user.roles.includes('admin');

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Mes patients</h1>
            <Alert type={message.type}>{message.text}</Alert>

            {!lie && <Alert type="warning">Votre compte n'est lié à aucune fiche du Personnel : demandez à un administrateur de le rattacher à votre fiche pour recevoir vos patients.</Alert>}

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <StatTile label="Patients en attente" value={attente} hint="Payés en caisse, pas encore reçus" />
                <StatTile label="Patients de la période" value={rows.length} />
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    load();
                }}
                className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
            >
                <Field label="Du" name="date_debut" type="date" value={filters.date_debut} onChange={(e) => setFilters({ ...filters, date_debut: e.target.value })} />
                <Field label="Au" name="date_fin" type="date" value={filters.date_fin} onChange={(e) => setFilters({ ...filters, date_fin: e.target.value })} />
                <Field label="Statut" name="statut" as="select" value={filters.statut} onChange={(e) => setFilters({ ...filters, statut: e.target.value })}>
                    <option value="">Tous</option>
                    <option value="en_attente">En attente</option>
                    <option value="vu">Vus</option>
                </Field>
                <Button type="submit">Filtrer</Button>
                <Button variant="secondary" onClick={() => { const next = { date_debut: today(), date_fin: today(), statut: '' }; setFilters(next); load(next); }}>
                    Aujourd'hui
                </Button>
            </form>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Heure', 'Patient', 'Sexe', 'Âge', 'Consultation', ...(admin ? ['Docteur'] : []), 'Reçu', 'Statut', 'Soins en caisse'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={admin ? 10 : 9} className="px-4 py-8 text-center text-slate-500">Aucun patient sur cette période.</td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 text-xs text-slate-500" title={formatDateHeure(row.created_at)}>{heure(row.created_at)}</td>
                                <td className="px-4 py-3 font-medium">{row.patient}</td>
                                <td className="px-4 py-3">{row.sexe}</td>
                                <td className="px-4 py-3">{row.age}</td>
                                <td className="px-4 py-3">{typeLabels[row.type_docteur] ?? row.type_docteur}</td>
                                {admin && <td className="px-4 py-3">Dr {row.docteur}</td>}
                                <td className="px-4 py-3 font-mono text-xs">{row.reference ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${statutStyles[row.statut]}`}>{statutLabels[row.statut] ?? row.statut}</span>
                                    {row.vu_at && <span className="ml-2 text-xs text-slate-500">à {heure(row.vu_at)}</span>}
                                </td>
                                <td className="px-4 py-3 text-xs">
                                    {row.ordres.map((ordre) => (
                                        <div key={ordre.id} className="flex items-center gap-2">
                                            <span className={`${pill} ${ordreStyles[ordre.statut]}`}>{ordreLabels[ordre.statut]}</span>
                                            <span title={ordre.actes}>{montant(ordre.total, 0)} Ar</span>
                                            {ordre.statut === 'en_attente' && <DeleteButton label="Annuler l'envoi en caisse" onClick={() => setAAnnuler(ordre)} />}
                                        </div>
                                    ))}
                                </td>
                                <td className="px-4 py-2 text-right whitespace-nowrap">
                                    {row.peut_envoyer_en_caisse && (
                                        <Button variant="secondary" className="mr-2 !px-3 !py-1.5 text-xs" onClick={() => openEnvoi(row)}>
                                            Envoyer en caisse
                                        </Button>
                                    )}
                                    {row.peut_marquer && (
                                        <Button className="!px-3 !py-1.5 text-xs" disabled={busy === row.id} onClick={() => markSeen(row)}>
                                            Marquer comme vu
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal
                open={envoi !== null}
                size="lg"
                title="Envoyer le patient en caisse"
                subtitle={envoi ? `${envoi.consultation.patient} — choisissez les soins à régler en caisse.` : ''}
                onClose={() => setEnvoi(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setEnvoi(null)}>Annuler</Button>
                        <Button type="submit" form="form-soins" disabled={busy === 'envoi' || Object.keys(envoi?.choix ?? {}).length === 0}>Envoyer en caisse</Button>
                    </>
                }
            >
                {envoi && (
                    <form id="form-soins" onSubmit={sendToCashDesk} className="flex flex-col gap-4">
                        <Alert>{erreur}</Alert>
                        {actes !== null && actes.length === 0 && <Alert type="warning">Aucun acte dentaire n'est configuré. Dans Gestion Pharmacie, onglet Services, cochez « Acte dentaire » sur les soins que vous pratiquez (carie, détartrage…).</Alert>}
                        <div className="max-h-72 space-y-1 overflow-y-auto">
                            {(actes ?? []).map((acte) => (
                                <div key={acte.id} className="flex items-center justify-between gap-3 rounded-md px-3 py-2 hover:bg-slate-50">
                                    <label className="flex flex-1 cursor-pointer items-center gap-3 text-sm">
                                        <input type="checkbox" checked={Boolean(envoi.choix[acte.id])} onChange={() => toggleActe(acte)} />
                                        {acte.nom}
                                    </label>
                                    {envoi.choix[acte.id] && (
                                        <input
                                            type="number"
                                            min="1"
                                            max="99"
                                            aria-label={`Quantité — ${acte.nom}`}
                                            value={envoi.choix[acte.id]}
                                            onChange={(event) => setEnvoi({ ...envoi, choix: { ...envoi.choix, [acte.id]: Math.max(1, Number(event.target.value) || 1) } })}
                                            className="w-16 rounded-md border border-slate-300 bg-white px-2 py-1 text-right text-sm"
                                        />
                                    )}
                                    <span className="w-28 text-right text-sm font-semibold text-slate-700">{montant(acte.prix, 0)} Ar</span>
                                </div>
                            ))}
                        </div>
                        <Field label="Remarque pour la caisse (facultatif)" name="note" maxLength={255} placeholder="Ex. : dents 36 et 46" value={envoi.note} onChange={(e) => setEnvoi({ ...envoi, note: e.target.value })} />
                        <p className="text-right text-sm">
                            Total à régler : <strong>{montant(totalEnvoi, 0)} Ar</strong>
                        </p>
                    </form>
                )}
            </Modal>

            <ConfirmDialog open={aAnnuler !== null} message="Annuler l'envoi de ces soins en caisse ?" confirmLabel="Annuler l'envoi" onConfirm={cancelOrder} onCancel={() => setAAnnuler(null)} />
        </div>
    );
}
