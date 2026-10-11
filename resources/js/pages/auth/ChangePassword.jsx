import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage, validationErrors } from '../../services/api';

export default function ChangePassword() {
    const { user, refresh, logout } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');

        try {
            await api.patch('/me/password', form);
            await refresh();
            navigate('/', { replace: true });
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
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <h2 className="text-base font-semibold">Changer le mot de passe</h2>
            {user.must_change_password && (
                <Alert type="info">Vous utilisez un mot de passe provisoire. Choisissez-en un nouveau pour continuer.</Alert>
            )}
            <Alert>{message}</Alert>
            <Field label="Mot de passe actuel" name="current_password" type="password" autoComplete="current-password" required value={form.current_password} onChange={update} error={errors.current_password} />
            <Field label="Nouveau mot de passe" name="password" type="password" autoComplete="new-password" required value={form.password} onChange={update} error={errors.password} />
            <p className="-mt-2 text-xs text-slate-500">Au moins 10 caractères, avec des lettres et des chiffres.</p>
            <Field label="Confirmation" name="password_confirmation" type="password" autoComplete="new-password" required value={form.password_confirmation} onChange={update} error={errors.password_confirmation} />
            <Button type="submit" disabled={submitting}>
                {submitting ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
            {user.must_change_password ? (
                <Button variant="secondary" onClick={logout}>
                    Se déconnecter
                </Button>
            ) : (
                <Button variant="secondary" onClick={() => navigate(-1)}>
                    Annuler
                </Button>
            )}
        </form>
    );
}
