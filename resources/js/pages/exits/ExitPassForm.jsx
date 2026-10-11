import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';

const emptyForm = { departure_at: '', expected_return_at: '', destination: '', reason: '' };

function toLocalInput(value) {
    const date = new Date(value);

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export default function ExitPassForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (id) {
            api.get(`/requests/${id}`)
                .then(({ data }) => {
                    const exitPass = data.data.exit_pass;
                    setForm({
                        departure_at: toLocalInput(exitPass.departure_at),
                        expected_return_at: toLocalInput(exitPass.expected_return_at),
                        destination: exitPass.destination,
                        reason: exitPass.reason,
                    });
                })
                .catch((err) => setMessage(errorMessage(err)));
        }
    }, [id]);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');
        try {
            const { data } = id ? await api.put(`/exit-passes/${id}`, form) : await api.post('/exit-passes', form);
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
            <h1 className="text-xl font-semibold">{id ? 'Corriger le billet de sortie' : 'Nouveau billet de sortie'}</h1>
            <Alert>{message}</Alert>
            <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <Field label="Départ" name="departure_at" type="datetime-local" required value={form.departure_at} onChange={update} error={errors.departure_at} />
                <Field label="Retour prévu" name="expected_return_at" type="datetime-local" required min={form.departure_at || undefined} value={form.expected_return_at} onChange={update} error={errors.expected_return_at} />
                <div className="sm:col-span-2">
                    <Field label="Destination" name="destination" required value={form.destination} onChange={update} error={errors.destination} />
                </div>
                <div className="sm:col-span-2">
                    <Field label="Motif" name="reason" as="textarea" rows={3} required value={form.reason} onChange={update} error={errors.reason} />
                </div>
            </section>
            <div className="flex gap-3">
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Enregistrement…' : 'Enregistrer le brouillon'}
                </Button>
                <Button variant="secondary" onClick={() => navigate(-1)}>
                    Annuler
                </Button>
            </div>
        </form>
    );
}
