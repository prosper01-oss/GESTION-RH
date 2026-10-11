import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import api, { errorMessage } from '../../services/api';
import { formatDateTime } from '../../services/labels';

/**
 * Suivi des sorties (EF-42) : billets prêts au départ et sorties en cours, retards signalés.
 */
export default function ExitPassFollowUp() {
    const [ready, setReady] = useState([]);
    const [ongoing, setOngoing] = useState([]);
    const [error, setError] = useState('');
    const [busyId, setBusyId] = useState(null);

    const load = useCallback(() => {
        Promise.all([
            api.get('/exit-passes', { params: { scope: 'all', status: 'finalisee' } }),
            api.get('/exit-passes', { params: { scope: 'all', state: 'en_cours' } }),
        ])
            .then(([finalized, inProgress]) => {
                setReady(finalized.data.data.filter((request) => !request.exit_pass.actual_departure_at));
                setOngoing(inProgress.data.data);
            })
            .catch((err) => setError(errorMessage(err)));
    }, []);

    useEffect(() => {
        load();
        const timer = setInterval(load, 60000);

        return () => clearInterval(timer);
    }, [load]);

    async function record(request, kind) {
        setBusyId(request.id);
        setError('');
        try {
            await api.post(`/exit-passes/${request.id}/record-${kind}`);
            load();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusyId(null);
        }
    }

    function Row({ request, action }) {
        const exitPass = request.exit_pass;

        return (
            <li className={`flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm ${exitPass.is_overdue ? 'bg-red-50' : ''}`}>
                <div>
                    <Link to={`/demandes/${request.id}`} className="font-medium text-sky-700 hover:underline">
                        {request.employee.nom} {request.employee.prenom}
                    </Link>
                    <span className="ml-2 text-slate-500">{request.employee.department}</span>
                    <p className="text-slate-600">
                        {exitPass.destination} · retour prévu {formatDateTime(exitPass.expected_return_at)}
                        {exitPass.actual_departure_at && ` · parti à ${formatDateTime(exitPass.actual_departure_at)}`}
                    </p>
                    {exitPass.is_overdue && <p className="font-medium text-red-700">Retour prévu dépassé</p>}
                </div>
                <Button disabled={busyId === request.id} onClick={() => record(request, action)}>
                    {action === 'departure' ? 'Départ' : 'Retour'}
                </Button>
            </li>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-xl font-semibold">Sorties</h1>
            <Alert>{error}</Alert>
            <section className="flex flex-col gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">En cours ({ongoing.length})</h2>
                <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                    {ongoing.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucune sortie en cours.</li>}
                    {ongoing.map((request) => (
                        <Row key={request.id} request={request} action="return" />
                    ))}
                </ul>
            </section>
            <section className="flex flex-col gap-2">
                <h2 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Billets finalisés, départ non enregistré ({ready.length})</h2>
                <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                    {ready.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucun billet en attente de départ.</li>}
                    {ready.map((request) => (
                        <Row key={request.id} request={request} action="departure" />
                    ))}
                </ul>
            </section>
        </div>
    );
}
