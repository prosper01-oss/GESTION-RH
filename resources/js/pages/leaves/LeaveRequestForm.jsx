import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import LeaveRuleHints from '../../components/LeaveRuleHints';
import api, { errorMessage, validationErrors } from '../../services/api';
import { countingModeLabels, formatDays } from '../../services/labels';

const emptyForm = { leave_type_id: '', start_date: '', end_date: '', starts_afternoon: false, ends_morning: false, is_urgent: false, reason: '' };

/**
 * Création ou correction d'une demande de congé, avec aperçu du nombre de jours calculé par le serveur.
 */
export default function LeaveRequestForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [balances, setBalances] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [preview, setPreview] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get('/leave-types').then(({ data }) => {
            setLeaveTypes(data.data);
            if (!id) {
                setForm((current) => ({ ...current, leave_type_id: current.leave_type_id || data.data[0]?.id || '' }));
            }
        });
        api.get('/leave-balances/me').then(({ data }) => setBalances(data.data)).catch(() => setBalances([]));
        if (id) {
            api.get(`/leave-requests/${id}`)
                .then(({ data }) => {
                    const leave = data.data.leave;
                    setForm({
                        leave_type_id: leave.leave_type.id,
                        start_date: leave.start_date,
                        end_date: leave.end_date,
                        starts_afternoon: leave.starts_afternoon,
                        ends_morning: leave.ends_morning,
                        is_urgent: leave.is_urgent,
                        reason: leave.reason ?? '',
                    });
                })
                .catch((err) => setMessage(errorMessage(err)));
        }
    }, [id]);

    const selectedType = leaveTypes.find((type) => String(type.id) === String(form.leave_type_id));
    const balance = balances.find((item) => String(item.leave_type.id) === String(form.leave_type_id));

    // Aperçu du décompte dès que la période est complète.
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
        setSubmitting(true);
        setErrors({});
        setMessage('');
        try {
            const { data } = id ? await api.put(`/leave-requests/${id}`, form) : await api.post('/leave-requests', form);
            navigate(`/demandes/${data.data.id}`);
        } catch (error) {
            setErrors(validationErrors(error));
            setMessage(error.response?.status === 422 ? 'Corrigez les champs signalés.' : errorMessage(error));
        } finally {
            setSubmitting(false);
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });
    const check = (event) => setForm({ ...form, [event.target.name]: event.target.checked });

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Link to={id ? `/demandes/${id}` : '/demandes'} className="text-sm text-sky-700 hover:underline">
                ← Retour
            </Link>
            <h1 className="text-xl font-semibold">{id ? 'Corriger la demande' : 'Nouvelle demande de congé'}</h1>
            <Alert>{message}</Alert>

            <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
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
                {balance && (
                    <p>
                        Solde disponible : <strong>{formatDays(balance.summary.available)}</strong>
                    </p>
                )}
            </div>
            {preview && balance && preview.days > balance.summary.available && (
                <Alert type="error">Le nombre de jours dépasse votre solde disponible : la demande sera refusée à l'envoi.</Alert>
            )}

            <div className="flex gap-3">
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Enregistrement…' : 'Enregistrer le brouillon'}
                </Button>
                <Button variant="secondary" onClick={() => navigate(-1)}>
                    Annuler
                </Button>
            </div>
            <p className="text-xs text-slate-500">La demande reste un brouillon visible de vous seul jusqu'à son envoi aux RH.</p>
        </form>
    );
}
