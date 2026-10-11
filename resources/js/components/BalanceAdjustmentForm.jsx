import { useEffect, useState } from 'react';
import api, { errorMessage, validationErrors } from '../services/api';
import Alert from './Alert';
import Button from './Button';
import Field from './Field';

/**
 * Correction manuelle d'un solde par les RH, avec motif obligatoire.
 */
export default function BalanceAdjustmentForm({ employeeId, onSaved }) {
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [form, setForm] = useState({ leave_type_id: '', period_year: new Date().getFullYear(), days: '', reason: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get('/leave-types').then(({ data }) => {
            const withBalance = data.data.filter((type) => type.has_balance);
            setLeaveTypes(withBalance);
            setForm((current) => ({ ...current, leave_type_id: current.leave_type_id || withBalance[0]?.id || '' }));
        });
    }, []);

    async function handleSubmit(event) {
        event.preventDefault();
        if (!window.confirm(`Enregistrer une correction de ${form.days} jour(s) ? Elle restera visible dans l'historique.`)) {
            return;
        }
        setSubmitting(true);
        setErrors({});
        setMessage('');
        try {
            await api.post(`/leave-balances/${employeeId}/adjustments`, form);
            setForm({ ...form, days: '', reason: '' });
            onSaved();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        } finally {
            setSubmitting(false);
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
            <p className="text-sm font-medium">Corriger un solde</p>
            <Alert>{message}</Alert>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Type de congé" name="leave_type_id" as="select" value={form.leave_type_id} onChange={update} error={errors.leave_type_id}>
                    {leaveTypes.map((type) => (
                        <option key={type.id} value={type.id}>
                            {type.name}
                        </option>
                    ))}
                </Field>
                <Field label="Année" name="period_year" type="number" value={form.period_year} onChange={update} error={errors.period_year} />
                <Field label="Jours (négatif pour retirer)" name="days" type="number" step="0.5" required value={form.days} onChange={update} error={errors.days} />
            </div>
            <Field label="Motif" name="reason" required minLength={5} value={form.reason} onChange={update} error={errors.reason} placeholder="Ex. reprise du solde papier au 31/12/2025" />
            <div>
                <Button type="submit" disabled={submitting || leaveTypes.length === 0}>
                    Enregistrer la correction
                </Button>
            </div>
        </form>
    );
}
