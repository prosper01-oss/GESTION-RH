import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import { useAuth } from '../../hooks/useAuth';
import { errorMessage, validationErrors } from '../../services/api';

export default function Login() {
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [form, setForm] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    if (user) {
        return <Navigate to="/" replace />;
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');

        try {
            const loggedIn = await login(form.email, form.password);
            navigate(loggedIn.must_change_password ? '/mot-de-passe' : (location.state?.from ?? '/'), { replace: true });
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status === 429) {
                setMessage('Trop de tentatives. Réessayez dans une minute.');
            } else if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        } finally {
            setSubmitting(false);
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <h2 className="text-base font-semibold">Connexion</h2>
            <Alert>{message}</Alert>
            <Field label="Adresse email" name="email" type="email" autoComplete="username" required value={form.email} onChange={update} error={errors.email} />
            <Field label="Mot de passe" name="password" type="password" autoComplete="current-password" required value={form.password} onChange={update} error={errors.password} />
            <Button type="submit" disabled={submitting}>
                {submitting ? 'Connexion…' : 'Se connecter'}
            </Button>
        </form>
    );
}
