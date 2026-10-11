import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Pagination from '../../components/Pagination';
import RequestStatusBadge from '../../components/RequestStatusBadge';
import api, { errorMessage } from '../../services/api';
import { formatDays, formatPeriod } from '../../services/labels';

export default function LeaveRequestList() {
    const [requests, setRequests] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        api.get('/leave-requests', { params: { page } })
            .then(({ data }) => {
                setRequests(data.data);
                setMeta(data.meta);
            })
            .catch((err) => setError(errorMessage(err)))
            .finally(() => setLoading(false));
    }, [page]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Mes demandes de congé</h1>
                <Link to="/demandes/nouvelle" className="rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">
                    Nouvelle demande
                </Link>
            </div>
            <Alert>{error}</Alert>
            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Référence</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Période</th>
                            <th className="px-4 py-3 text-right font-medium">Jours</th>
                            <th className="px-4 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {!loading && requests.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-4 py-6 text-center text-slate-500">
                                    Vous n'avez encore aucune demande.
                                </td>
                            </tr>
                        )}
                        {requests.map((request) => (
                            <tr key={request.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3">
                                    <Link to={`/demandes/${request.id}`} className="font-mono text-xs text-sky-700 hover:underline">
                                        {request.reference ?? 'Brouillon'}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">{request.leave?.leave_type.name}</td>
                                <td className="px-4 py-3">{formatPeriod(request.leave)}</td>
                                <td className="px-4 py-3 text-right">{formatDays(request.leave?.requested_days)}</td>
                                <td className="px-4 py-3">
                                    <RequestStatusBadge status={request.status} label={request.status_label} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination meta={meta} onPageChange={setPage} />
        </div>
    );
}
