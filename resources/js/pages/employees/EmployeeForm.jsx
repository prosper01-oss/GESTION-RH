import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';
import { contractTypeLabels } from '../../services/labels';

const emptyForm = {
    matricule: '',
    nom: '',
    prenom: '',
    department_id: '',
    position: '',
    hire_date: '',
    contract_type: 'cdi',
    phone_pro: '',
    email_pro: '',
};

/**
 * Création (sans id) ou modification (avec id) d'une fiche employé.
 */
export default function EmployeeForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [form, setForm] = useState(emptyForm);
    const [departments, setDepartments] = useState([]);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        api.get('/departments').then(({ data }) => setDepartments(data.data));

        if (id) {
            api.get(`/employees/${id}`)
                .then(({ data }) => setForm(Object.fromEntries(Object.keys(emptyForm).map((key) => [key, data.data[key] ?? '']))))
                .catch((err) => setMessage(errorMessage(err)));
        }
    }, [id]);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');

        try {
            const { data } = id ? await api.put(`/employees/${id}`, form) : await api.post('/employees', form);
            navigate(`/personnel/${data.data.id}`);
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
            <Link to={id ? `/personnel/${id}` : '/personnel'} className="text-sm text-sky-700 hover:underline">
                ← Retour
            </Link>
            <h1 className="text-xl font-semibold">{id ? 'Modifier la fiche' : 'Nouvel employé'}</h1>
            <Alert>{message}</Alert>

            {departments.length === 0 && !id && (
                <Alert type="info">
                    Aucun service n'existe encore. <Link to="/services" className="font-medium underline">Créez d'abord un service</Link>.
                </Alert>
            )}

            <section className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <Field label="Matricule" name="matricule" required value={form.matricule} onChange={update} error={errors.matricule} />
                <Field label="Service" name="department_id" as="select" required value={form.department_id} onChange={update} error={errors.department_id}>
                    <option value="">Choisir…</option>
                    {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                            {department.name}
                        </option>
                    ))}
                </Field>
                <Field label="Nom" name="nom" required value={form.nom} onChange={update} error={errors.nom} />
                <Field label="Prénoms" name="prenom" required value={form.prenom} onChange={update} error={errors.prenom} />
                <Field label="Fonction" name="position" required value={form.position} onChange={update} error={errors.position} />
                <Field label="Date d'embauche" name="hire_date" type="date" required value={form.hire_date} onChange={update} error={errors.hire_date} />
                <Field label="Type de contrat" name="contract_type" as="select" required value={form.contract_type} onChange={update} error={errors.contract_type}>
                    {Object.entries(contractTypeLabels).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </Field>
                <Field label="Téléphone professionnel" name="phone_pro" type="tel" value={form.phone_pro} onChange={update} error={errors.phone_pro} />
                <Field label="Email professionnel" name="email_pro" type="email" value={form.email_pro} onChange={update} error={errors.email_pro} />
            </section>

            <div className="flex gap-3">
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Enregistrement…' : 'Enregistrer'}
                </Button>
                <Button variant="secondary" onClick={() => navigate(-1)}>
                    Annuler
                </Button>
            </div>
        </form>
    );
}
