import { useCallback, useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate } from '../../services/labels';

/**
 * Suppléances (EF-53) : désigner un suppléant pour une période, et voir celles que l'on exerce.
 */
export default function DelegationList() {
    const [delegations, setDelegations] = useState([]);
    const [roles, setRoles] = useState([]);
    const [candidates, setCandidates] = useState([]);
    const [form, setForm] = useState({ role: '', delegate_id: '', start_date: '', end_date: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');

    const load = useCallback(() => {
        api.get('/delegations')
            .then(({ data }) => {
                setDelegations(data.data);
                setRoles(data.delegable_roles);
                setForm((current) => ({ ...current, role: current.role || data.delegable_roles[0]?.value || '' }));
                if (data.delegable_roles.length > 0) {
                    api.get('/delegations/candidates').then((response) => setCandidates(response.data.data));
                }
            })
            .catch((err) => setMessage(errorMessage(err)));
    }, []);

    useEffect(load, [load]);

    async function create(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');
        try {
            await api.post('/delegations', form);
            setForm({ ...form, delegate_id: '', start_date: '', end_date: '' });
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        }
    }

    async function revoke(delegation) {
        if (!window.confirm(`Mettre fin à la suppléance de ${delegation.delegate} ?`)) {
            return;
        }
        try {
            await api.delete(`/delegations/${delegation.id}`);
            load();
        } catch (error) {
            setMessage(errorMessage(error));
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Suppléances</h1>
            <Alert>{message}</Alert>

            {roles.length > 0 && (
                <form onSubmit={create} className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                    <p className="text-sm text-slate-600 sm:col-span-2">
                        Pendant la période, le suppléant reçoit vos droits de validation. Ses décisions indiquent qu'elles sont prises en votre suppléance. Il ne peut pas traiter vos propres demandes.
                    </p>
                    <Field label="Rôle délégué" name="role" as="select" value={form.role} onChange={update} error={errors.role}>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </Field>
                    <Field label="Suppléant" name="delegate_id" as="select" required value={form.delegate_id} onChange={update} error={errors.delegate_id}>
                        <option value="">Choisir…</option>
                        {candidates.map((candidate) => (
                            <option key={candidate.id} value={candidate.id}>
                                {candidate.name}
                            </option>
                        ))}
                    </Field>
                    <Field label="Du" name="start_date" type="date" required value={form.start_date} onChange={update} error={errors.start_date} />
                    <Field label="Au" name="end_date" type="date" required min={form.start_date || undefined} value={form.end_date} onChange={update} error={errors.end_date} />
                    <div className="sm:col-span-2">
                        <Button type="submit">Désigner le suppléant</Button>
                    </div>
                </form>
            )}

            <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {delegations.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucune suppléance.</li>}
                {delegations.map((delegation) => (
                    <li key={delegation.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p>
                                <span className="font-medium">{delegation.delegate}</span> supplée <span className="font-medium">{delegation.delegator}</span> ({delegation.role_label})
                                {delegation.is_current && <span className="ml-2 rounded bg-emerald-50 px-1.5 text-xs text-emerald-700">en cours</span>}
                                {delegation.revoked_at && <span className="ml-2 text-xs text-slate-500">terminée le {formatDate(delegation.revoked_at)}</span>}
                            </p>
                            <p className="text-slate-500">
                                du {formatDate(delegation.start_date)} au {formatDate(delegation.end_date)}
                            </p>
                        </div>
                        {delegation.can_revoke && (
                            <button type="button" className="text-red-600 hover:underline" onClick={() => revoke(delegation)}>
                                Mettre fin
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
