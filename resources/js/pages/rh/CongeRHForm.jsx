import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import EmployeePicker from '../../components/EmployeePicker';
import Field from '../../components/Field';
import LeaveRuleHints from '../../components/LeaveRuleHints';
import ReturnAndInterimFields, { suggestedReturnDate } from '../../components/ReturnAndInterimFields';
import api, { errorMessage, validationErrors } from '../../services/api';
import { countingModeLabels, formatDays } from '../../services/labels';

const emptyForm = { leave_type_id: '', start_date: '', end_date: '', starts_afternoon: false, ends_morning: false, return_date: '', interim: null, interim_employee_id: '', is_urgent: false, reason: '' };

/**
 * Formulaire RH : créer une demande de congé pour n'importe quel agent.
 */
export default function CongeRHForm() {
    const navigate = useNavigate();
    const [employee, setEmployee] = useState(null);
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [balance, setBalance] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [preview, setPreview] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get('/leave-types').then(({ data }) => {
            setLeaveTypes(data.data);
            setForm((current) => ({ ...current, leave_type_id: current.leave_type_id || data.data[0]?.id || '' }));
        });
    }, []);

    // Charger le solde de l'agent sélectionné
    useEffect(() => {
        setBalance(null);
        if (!employee) {
            return;
        }
        api.get(`/leave-balances/${employee.id}`)
            .then(({ data }) => setBalance(data.data))
            .catch(() => setBalance(null));
    }, [employee]);

    const selectedType = leaveTypes.find((type) => String(type.id) === String(form.leave_type_id));
    const selectedBalance = balance?.find((item) => String(item.leave_type.id) === String(form.leave_type_id));

    // Aperçu du décompte
    useEffect(() => {
        setPreview(null);
        if (!form.leave_type_id || !form.start_date || !form.end_date || form.end_date < form.start_date) {
            return;
        }
        const timer = setTimeout(() => {
            api.get('/leave-days/count', {
                params: {
                    leave_type_id: form.leave_type_id,
                    start_date: form.start_date,
                    end_date: form.end_date,
                    starts_afternoon: form.starts_afternoon ? 1 : 0,
                    ends_morning: form.ends_morning ? 1 : 0,
                },
            })
                .then(({ data }) => setPreview(data.data))
                .catch(() => setPreview(null));
        }, 250);
        return () => clearTimeout(timer);
    }, [form.leave_type_id, form.start_date, form.end_date, form.starts_afternoon, form.ends_morning]);

    async function handleSubmit(event) {
        event.preventDefault();
        if (!employee) {
            setErrors({ employee_id: 'Veuillez sélectionner un agent.' });
            return;
        }
        setSubmitting(true);
        setErrors({});
        setMessage('');
        try {
            const { data } = await api.post('/leave-requests', { ...form, employee_id: employee.id });
            const requestId = data.data.id;
            await api.post(`/requests/${requestId}/submit`);
            navigate(`/demandes/${requestId}`);
        } catch (error) {
            setErrors(validationErrors(error));
            setMessage(error.response?.status === 422 ? 'Corrigez les champs signalés.' : errorMessage(error));
        } finally {
            setSubmitting(false);
        }
    }

    // Date de reprise proposée d'après le dernier jour, tant que l'utilisateur ne l'a pas modifiée.
    useEffect(() => {
        setForm((current) => (current.return_date_touched ? current : { ...current, return_date: suggestedReturnDate(current.end_date, current.ends_morning) }));
    }, [form.end_date, form.ends_morning]);

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });
    const check = (event) => setForm({ ...form, [event.target.name]: event.target.checked });

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Link to="/rh/demandes" className="text-sm text-sky-700 hover:underline">
                ← Demandes RH
            </Link>
            <h1 className="text-xl font-semibold">Nouvelle demande de congé — RH</h1>
            <Alert>{message}</Alert>

            <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <EmployeePicker
                        label="Agent"
                        value={employee}
                        onChange={(emp) => { setEmployee(emp); setErrors((prev) => ({ ...prev, employee_id: undefined })); }}
                        error={errors.employee_id}
                    />
                </div>
                <div className="sm:col-span-2">
                    <Field label="Type de congé" name="leave_type_id" as="select" required value={form.leave_type_id} onChange={update} error={errors.leave_type_id}>
                        {leaveTypes.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                            </option>
                        ))}
                    </Field>
                </div>
                <div className="flex flex-col gap-2">
                    <Field label="Premier jour" name="start_date" type="date" required value={form.start_date} onChange={update} error={errors.start_date} />
                    {selectedType?.allows_half_day && (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="starts_afternoon" checked={form.starts_afternoon} onChange={check} />
                            À partir de l'après-midi
                        </label>
                    )}
                </div>
                <div className="flex flex-col gap-2">
                    <Field label="Dernier jour" name="end_date" type="date" required min={form.start_date || undefined} value={form.end_date} onChange={update} error={errors.end_date} />
                    {selectedType?.allows_half_day && (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="ends_morning" checked={form.ends_morning} onChange={check} />
                            Jusqu'au matin seulement
                        </label>
                    )}
                </div>
                <ReturnAndInterimFields form={form} setForm={setForm} errors={errors} />
                <LeaveRuleHints leaveType={selectedType} form={form} setForm={setForm} />
                <div className="sm:col-span-2">
                    <Field label={form.is_urgent ? "Motif de l'urgence (obligatoire)" : 'Motif (facultatif)'} name="reason" as="textarea" rows={3} required={form.is_urgent} value={form.reason} onChange={update} error={errors.reason} />
                </div>
            </section>

            <div className="flex flex-wrap gap-6 rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p>
                    Jours décomptés : <strong>{preview ? formatDays(preview.days) : '—'}</strong>
                    {preview && <span className="text-sky-700"> ({(countingModeLabels[preview.counting_mode] ?? preview.counting_mode).toLowerCase()})</span>}
                </p>
                {selectedBalance && (
                    <p>
                        Solde agent : <strong>{formatDays(selectedBalance.summary.available)}</strong>
                    </p>
                )}
            </div>
            {preview && selectedBalance && preview.days > selectedBalance.summary.available && (
                <Alert type="error">Le nombre de jours dépasse le solde disponible de l'agent.</Alert>
            )}

            <div className="flex gap-3">
                <Button type="submit" disabled={submitting || !employee}>
                    {submitting ? 'Enregistrement…' : 'Soumettre la demande'}
                </Button>
                <Button variant="secondary" onClick={() => navigate('/rh/demandes')}>
                    Annuler
                </Button>
            </div>
            <p className="text-xs text-slate-500">La demande est directement soumise au nom de l'agent sélectionné.</p>
        </form>
    );
}
