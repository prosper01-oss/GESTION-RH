import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import api, { errorMessage } from '../../services/api';

/**
 * Préférences de notification (EF-63). Les alertes dans l'application restent toujours actives.
 */
export default function NotificationPreferences() {
    const [preferences, setPreferences] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        api.get('/me/notification-preferences')
            .then(({ data }) => setPreferences(data.data))
            .catch((err) => setError(errorMessage(err)));
    }, []);

    async function save(event) {
        event.preventDefault();
        setMessage('');
        setError('');
        try {
            const { data } = await api.put('/me/notification-preferences', preferences);
            setPreferences(data.data);
            setMessage('Préférences enregistrées.');
        } catch (err) {
            setError(errorMessage(err));
        }
    }

    if (!preferences) {
        return error ? <Alert>{error}</Alert> : <p className="text-sm text-slate-500">Chargement…</p>;
    }

    return (
        <form onSubmit={save} className="flex max-w-xl flex-col gap-4">
            <h1 className="text-xl font-semibold">Préférences de notification</h1>
            <Alert type="success">{message}</Alert>
            <Alert>{error}</Alert>
            <div className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-6 text-sm">
                <p className="text-slate-600">Les alertes dans l'application sont toujours affichées.</p>
                <label className="flex items-start gap-3">
                    <input type="checkbox" className="mt-1" checked={preferences.notify_by_email} onChange={(e) => setPreferences({ ...preferences, notify_by_email: e.target.checked })} />
                    <span>
                        <span className="font-medium">Recevoir les emails</span>
                        <span className="block text-slate-500">À chaque étape de mes demandes et des demandes que je traite.</span>
                    </span>
                </label>
                <label className="flex items-start gap-3">
                    <input type="checkbox" className="mt-1" checked={preferences.notify_by_sms} onChange={(e) => setPreferences({ ...preferences, notify_by_sms: e.target.checked })} />
                    <span>
                        <span className="font-medium">Recevoir les SMS</span>
                        <span className="block text-slate-500">Pour les décisions importantes, sur le téléphone professionnel de ma fiche.</span>
                    </span>
                </label>
            </div>
            <div>
                <Button type="submit">Enregistrer</Button>
            </div>
        </form>
    );
}
