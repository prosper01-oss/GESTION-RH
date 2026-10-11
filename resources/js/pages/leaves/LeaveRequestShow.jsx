import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import RequestStatusBadge from '../../components/RequestStatusBadge';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, formatDays, formatPeriod } from '../../services/labels';

/**
 * Action proposée pour chaque statut atteignable. Le serveur indique lesquelles sont permises
 * (available_transitions) ; il reste seul juge à l'envoi.
 */
const actions = {
    soumise: { label: 'Envoyer aux RH', variant: 'primary', path: (id) => `/leave-requests/${id}/submit` },
    transmise_medecin: { label: 'Transmettre au Médecin-Chef', variant: 'primary', decision: 'transmettre' },
    approuvee: { label: 'Approuver', variant: 'primary', decision: 'approuver' },
    a_corriger: { label: 'Renvoyer à l’agent pour correction', variant: 'secondary', decision: 'a_corriger', needsComment: true },
    renvoyee_rh: { label: 'Renvoyer aux RH', variant: 'secondary', decision: 'renvoyer_rh', needsComment: true },
    refusee: { label: 'Refuser', variant: 'danger', decision: 'refuser', needsComment: true, confirm: 'Refuser définitivement cette demande ?' },
    finalisee: { label: 'Finaliser (imprimé et enregistré)', variant: 'primary', path: (id) => `/requests/${id}/finalize` },
    terminee: { label: 'Clôturer', variant: 'secondary', path: (id) => `/requests/${id}/complete`, confirm: 'Clôturer ce congé ? Les jours seront définitivement consommés.' },
    annulee: { label: 'Annuler la demande', variant: 'danger', path: (id) => `/leave-requests/${id}/cancel`, confirm: 'Annuler cette demande ?' },
};

const printableStatuses = ['approuvee', 'finalisee', 'terminee'];

export default function LeaveRequestShow() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [request, setRequest] = useState(null);
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [comment, setComment] = useState('');
    const [pendingAction, setPendingAction] = useState(null);
    const [busy, setBusy] = useState(false);
    const [interruptDate, setInterruptDate] = useState('');

    const load = useCallback(() => {
        api.get(`/leave-requests/${id}`)
            .then(({ data }) => setRequest(data.data))
            .catch((err) => setError(errorMessage(err)));
    }, [id]);

    useEffect(load, [load]);

    async function run(status) {
        const action = actions[status];
        if (action.needsComment && pendingAction !== status) {
            setPendingAction(status);
            setErrors({});
            return;
        }
        if (action.confirm && !window.confirm(action.confirm)) {
            return;
        }

        setBusy(true);
        setError('');
        setErrors({});
        try {
            const { data } = action.decision
                ? await api.post(`/requests/${id}/decision`, { decision: action.decision, comment: comment || null })
                : await api.post(action.path(id), { comment: comment || null });
            setRequest(data.data);
            setComment('');
            setPendingAction(null);
        } catch (err) {
            const fieldErrors = validationErrors(err);
            setErrors(fieldErrors);
            setError(Object.values(fieldErrors)[0] ?? errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    async function upload(event) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        const body = new FormData();
        body.append('file', file);
        setBusy(true);
        setError('');
        try {
            await api.post(`/leave-requests/${id}/attachments`, body);
            load();
        } catch (err) {
            setError(validationErrors(err).file ?? errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    async function removeAttachment(attachment) {
        if (!window.confirm(`Supprimer « ${attachment.original_name} » ?`)) {
            return;
        }
        try {
            await api.delete(`/attachments/${attachment.id}`);
            load();
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    async function removeDraft() {
        if (!window.confirm('Supprimer ce brouillon ?')) {
            return;
        }
        try {
            await api.delete(`/leave-requests/${id}`);
            navigate('/demandes');
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    async function interrupt(event) {
        event.preventDefault();
        if (!window.confirm('Interrompre le congé ? Seuls les jours réellement pris seront décomptés.')) {
            return;
        }
        setBusy(true);
        setError('');
        try {
            const { data } = await api.post(`/leave-requests/${id}/interrupt`, { actual_end_date: interruptDate });
            setRequest(data.data);
        } catch (err) {
            setError(validationErrors(err).actual_end_date ?? errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    if (!request) {
        return error ? <Alert>{error}</Alert> : <p className="text-sm text-slate-500">Chargement…</p>;
    }

    const leave = request.leave;
    const transitions = request.available_transitions.filter((status) => actions[status]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4 print:hidden">
                <button type="button" onClick={() => navigate(-1)} className="text-sm text-sky-700 hover:underline">
                    ← Retour
                </button>
                {printableStatuses.includes(request.status) && (
                    <Button variant="secondary" onClick={() => window.print()}>
                        Imprimer l'autorisation
                    </Button>
                )}
            </div>

            <div className="print:hidden">
                <Alert>{error}</Alert>
            </div>

            <section className="rounded-lg border border-slate-200 bg-white p-6 print:hidden">
                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-semibold">{leave.leave_type.name}</h1>
                    <RequestStatusBadge status={request.status} label={request.status_label} />
                    <span className="font-mono text-xs text-slate-500">{request.reference ?? 'Brouillon'}</span>
                </div>
                <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-slate-500">Agent</dt>
                        <dd className="font-medium">
                            {request.employee.prenom} {request.employee.nom} <span className="font-mono text-xs text-slate-500">({request.employee.matricule})</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">Service</dt>
                        <dd className="font-medium">{request.employee.department ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">Période</dt>
                        <dd className="font-medium">{formatPeriod(leave)}</dd>
                    </div>
                    <div>
                        <dt className="text-slate-500">Jours décomptés</dt>
                        <dd className="font-medium">
                            {formatDays(leave.requested_days)}
                            {leave.consumed_days !== null && leave.consumed_days !== leave.requested_days && ` (réellement pris : ${formatDays(leave.consumed_days)})`}
                        </dd>
                    </div>
                    {leave.reason && (
                        <div className="sm:col-span-2">
                            <dt className="text-slate-500">Motif</dt>
                            <dd className="whitespace-pre-line">{leave.reason}</dd>
                        </div>
                    )}
                    {request.balance && (
                        <div className="sm:col-span-2 rounded-md bg-slate-50 px-3 py-2">
                            <dt className="text-slate-500">Solde de l'agent ({request.balance.year})</dt>
                            <dd>
                                Disponible : <strong>{formatDays(request.balance.available)}</strong> · Demandable, autres demandes en cours déduites :{' '}
                                <strong className={request.balance.requestable < leave.requested_days ? 'text-red-700' : ''}>{formatDays(request.balance.requestable)}</strong>
                            </dd>
                        </div>
                    )}
                </dl>
            </section>

            <section className="rounded-lg border border-slate-200 bg-white p-6 print:hidden">
                <h2 className="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Justificatifs</h2>
                {request.attachments.length === 0 && <p className="text-sm text-slate-500">Aucun justificatif.</p>}
                <ul className="flex flex-col gap-2 text-sm">
                    {request.attachments.map((attachment) => (
                        <li key={attachment.id} className="flex flex-wrap items-center gap-3">
                            {attachment.can_view ? (
                                <a href={`/api/v1/attachments/${attachment.id}`} className="text-sky-700 hover:underline">
                                    {attachment.original_name}
                                </a>
                            ) : (
                                <span className="text-slate-500">{attachment.original_name} (accès restreint)</span>
                            )}
                            {attachment.is_medical && <span className="rounded bg-rose-50 px-1.5 text-xs text-rose-700">médical</span>}
                            {request.can_edit && (
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => removeAttachment(attachment)}>
                                    Supprimer
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
                {request.can_edit && (
                    <label className={`mt-3 inline-flex cursor-pointer rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 ${busy ? 'pointer-events-none opacity-50' : ''}`}>
                        Ajouter un justificatif (PDF, JPG, PNG — 5 Mo max.)
                        <input type="file" accept="application/pdf,image/jpeg,image/png" className="sr-only" onChange={upload} disabled={busy} />
                    </label>
                )}
            </section>

            {(transitions.length > 0 || request.can_edit || request.can_delete) && (
                <section className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-6 print:hidden">
                    <h2 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Actions</h2>
                    {pendingAction && (
                        <Field
                            label={`Commentaire (obligatoire) — ${actions[pendingAction].label}`}
                            name="comment"
                            as="textarea"
                            rows={3}
                            value={comment}
                            onChange={(event) => setComment(event.target.value)}
                            error={errors.comment}
                        />
                    )}
                    <div className="flex flex-wrap gap-3">
                        {request.can_edit && (
                            <Link to={`/demandes/${id}/modifier`} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Modifier
                            </Link>
                        )}
                        {transitions.map((status) => (
                            <Button key={status} variant={actions[status].variant} disabled={busy} onClick={() => run(status)}>
                                {pendingAction === status ? `Confirmer : ${actions[status].label.toLowerCase()}` : actions[status].label}
                            </Button>
                        ))}
                        {pendingAction && (
                            <Button variant="secondary" onClick={() => { setPendingAction(null); setComment(''); }}>
                                Annuler
                            </Button>
                        )}
                        {request.can_delete && (
                            <Button variant="secondary" onClick={removeDraft}>
                                Supprimer le brouillon
                            </Button>
                        )}
                    </div>
                    {request.can_interrupt && (
                        <form onSubmit={interrupt} className="mt-2 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-end">
                            <Field label="Interrompre : dernier jour réellement pris" name="actual_end_date" type="date" required min={leave.start_date} max={leave.end_date} value={interruptDate} onChange={(event) => setInterruptDate(event.target.value)} />
                            <Button type="submit" variant="secondary" disabled={busy}>
                                Interrompre le congé
                            </Button>
                        </form>
                    )}
                </section>
            )}

            <section className="rounded-lg border border-slate-200 bg-white p-6 print:hidden">
                <h2 className="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Historique</h2>
                <ol className="flex flex-col gap-3 text-sm">
                    {request.history.map((entry, index) => (
                        <li key={index} className="border-l-2 border-slate-200 pl-3">
                            <p>
                                <span className="font-medium">{entry.to_status_label}</span> — {entry.actor}
                            </p>
                            <p className="text-xs text-slate-500">{new Date(entry.created_at).toLocaleString('fr-FR')}</p>
                            {entry.comment && <p className="mt-1 whitespace-pre-line text-slate-700">« {entry.comment} »</p>}
                        </li>
                    ))}
                </ol>
            </section>

            {printableStatuses.includes(request.status) && <PrintableAuthorization request={request} />}
        </div>
    );
}

/**
 * Autorisation de congé imprimable (EF-28), visible seulement à l'impression.
 */
function PrintableAuthorization({ request }) {
    const leave = request.leave;
    const decision = [...request.history].reverse().find((entry) => entry.to_status === 'approuvee');

    return (
        <div className="hidden text-sm text-black print:block">
            <div className="mb-8 border-b-2 border-black pb-4 text-center">
                <p className="text-lg font-bold">OMIT</p>
                <p>Organisation Médicale Interentreprise de Toliara</p>
            </div>
            <h1 className="mb-1 text-center text-xl font-bold uppercase">Autorisation de congé</h1>
            <p className="mb-8 text-center font-mono">{request.reference}</p>
            <table className="mb-10 w-full">
                <tbody>
                    {[
                        ['Agent', `${request.employee.prenom} ${request.employee.nom}`],
                        ['Matricule', request.employee.matricule],
                        ['Service', request.employee.department ?? '—'],
                        ['Nature du congé', leave.leave_type.name],
                        ['Période', formatPeriod(leave)],
                        ['Nombre de jours', formatDays(leave.requested_days)],
                        ['Approuvé par', decision ? `${decision.actor}, le ${formatDate(decision.created_at)}` : '—'],
                    ].map(([label, value]) => (
                        <tr key={label}>
                            <td className="w-48 py-1.5 align-top font-semibold">{label}</td>
                            <td className="py-1.5">{value}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="grid grid-cols-3 gap-6 text-center">
                {["L'agent", 'Les Ressources Humaines', 'Le Médecin-Chef'].map((label) => (
                    <div key={label}>
                        <p className="font-semibold">{label}</p>
                        <div className="mt-16 border-t border-black pt-1 text-xs">Signature</div>
                    </div>
                ))}
            </div>
            <p className="mt-12 text-xs">Document généré le {new Date().toLocaleDateString('fr-FR')}.</p>
        </div>
    );
}
