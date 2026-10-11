import { useCallback, useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import api, { errorMessage } from '../../services/api';
import { formatDateTime } from '../../services/labels';

function formatSize(bytes) {
    return bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
}

/**
 * État des sauvegardes (section 10.1) et lancement manuel.
 */
export default function BackupList() {
    const [state, setState] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const load = useCallback(() => {
        api.get('/admin/backups')
            .then(({ data }) => setState(data))
            .catch((err) => setError(errorMessage(err)));
    }, []);

    useEffect(load, [load]);

    async function backupNow() {
        setBusy(true);
        setMessage('');
        setError('');
        try {
            const { data } = await api.post('/admin/backups');
            setMessage(data.message);
            load();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    const lastBackup = state?.data[0];
    const stale = !lastBackup || Date.now() - new Date(lastBackup.created_at).getTime() > 36 * 3600 * 1000;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Sauvegardes</h1>
                <Button onClick={backupNow} disabled={busy}>
                    {busy ? 'Sauvegarde en cours…' : 'Sauvegarder maintenant'}
                </Button>
            </div>
            <Alert type="success">{message}</Alert>
            <Alert>{error}</Alert>

            {state && (
                <>
                    {state.last_failure && (
                        <Alert>
                            Dernière sauvegarde en échec le {formatDateTime(state.last_failure.at)} : {state.last_failure.error}
                        </Alert>
                    )}
                    {stale && <Alert>Aucune sauvegarde depuis plus de 36 heures. Vérifiez que la tâche planifiée (cron) tourne sur le serveur.</Alert>}
                    {!state.copy_configured && (
                        <Alert type="info">Aucun second support n'est configuré (OMIT_BACKUP_COPY_PATH) : les archives ne sont conservées que sur le serveur.</Alert>
                    )}
                    <p className="text-sm text-slate-600">
                        Sauvegarde automatique chaque soir à 23 h 30 · conservation {state.retention_days} jours · restauration par la commande <code className="rounded bg-slate-100 px-1">php artisan omit:backup-restore</code>
                    </p>
                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Archive</th>
                                    <th className="px-4 py-3 font-medium">Date</th>
                                    <th className="px-4 py-3 text-right font-medium">Taille</th>
                                    <th className="px-4 py-3 font-medium">Second support</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {state.data.length === 0 && (
                                    <tr>
                                        <td colSpan="4" className="px-4 py-6 text-center text-slate-500">
                                            Aucune sauvegarde.
                                        </td>
                                    </tr>
                                )}
                                {state.data.map((backup) => (
                                    <tr key={backup.name}>
                                        <td className="px-4 py-2 font-mono text-xs">{backup.name}</td>
                                        <td className="px-4 py-2">{formatDateTime(backup.created_at)}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">{formatSize(backup.size)}</td>
                                        <td className="px-4 py-2">{backup.copied ? 'Copiée' : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
}
