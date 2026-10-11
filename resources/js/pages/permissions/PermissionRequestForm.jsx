import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatHours } from '../../services/labels';

const emptyForm = { permission_type_id: '', start_at: '', end_at: '', reason: '' };

/**
 * Convertit une date ISO du serveur pour un champ datetime-local (heure locale).
 */
function toLocalInput(value) {
    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export default function PermissionRequestForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [types, setTypes] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get('/permission-types').then(({ data }) => {
            setTypes(data.data);
            if (!id) {
                setForm((current) => ({ ...current, permission_type_id: current.permission_type_id || data.data[0]?.id || '' }));
            }
        });
        if (id) {
            api.get(`/requests/${id}`)
                .then(({ data }) => {
                    const permission = data.data.permission;
                    setForm({
                        permission_type_id: permission.permission_type.id,
                        start_at: toLocalInput(permission.start_at),
                        end_at: toLocalInput(permission.end_at),
                        reason: permission.reason ?? '',
                    });
                })
                .catch((err) => setMessage(errorMessage(err)));
        }
    }, [id]);

    const selectedType = types.find((type) => String(type.id) === String(form.permission_type_id));
    const hours = form.start_at && form.end_at ? (new Date(form.end_at) - new Date(form.start_at)) / 3600000 : null;

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');
        try {
            const { data } = id ? await api.put(`/permission-requests/${id}`, form) : await api.post('/permission-requests', form);
            navigate(`/demandes/${data.data.id}`);
        } catch (error) {
            setErrors(validationErrors(error));
            setMessage(error.response?.status === 422 ? 'Corrigez les champs signalés.' : errorMessage(error));
        } finally {
            setSubmitting(false);
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Link to={id ? `/demandes/${id}` : '/demandes'} className="text-sm text-sky-700 hover:underline">
                ← Retour
            </Link>
            <h1 className="text-xl font-semibold">{id ? 'Corriger la permission' : 'Nouvelle demande de permission'}</h1>
            <Alert>{message}</Alert>
            {types.length === 0 && <Alert type="info">Aucune catégorie de permission n'est encore paramétrée par les RH.</Alert>}

            <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <Field label="Catégorie" name="permission_type_id" as="select" required value={form.permission_type_id} onChange={update} error={errors.permission_type_id}>
                        {types.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                                {type.is_paid ? '' : ' (non rémunérée)'}
                            </option>
                        ))}
                    </Field>
                    {selectedType && (
                        <p className="mt-1 text-xs text-slate-500">
                            {selectedType.max_duration_hours ? `Durée maximale : ${formatHours(selectedType.max_duration_hours)}. ` : ''}
                            {selectedType.requires_attachment ? 'Un justificatif sera demandé avant l’envoi.' : ''}
                        </p>
                    )}
                </div>
                <Field label="Début" name="start_at" type="datetime-local" required value={form.start_at} onChange={update} error={errors.start_at} />
                <Field label="Fin" name="end_at" type="datetime-local" required min={form.start_at || undefined} value={form.end_at} onChange={update} error={errors.end_at} />
                <div className="sm:col-span-2">
                    <Field label="Motif (facultatif)" name="reason" as="textarea" rows={3} value={form.reason} onChange={update} error={errors.reason} />
                </div>
            </section>

            {hours > 0 && (
                <p className="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    Durée : <strong>{formatHours(Math.round(hours * 100) / 100)}</strong>
                    {selectedType?.max_duration_hours && hours > selectedType.max_duration_hours && <span className="ml-2 text-red-700">— dépasse le maximum de la catégorie</span>}
                </p>
            )}

            <div className="flex gap-3">
                <Button type="submit" disabled={submitting || types.length === 0}>
                    {submitting ? 'Enregistrement…' : 'Enregistrer le brouillon'}
                </Button>
                <Button variant="secondary" onClick={() => navigate(-1)}>
                    Annuler
                </Button>
            </div>
        </form>
    );
}
