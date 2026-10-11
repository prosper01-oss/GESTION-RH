import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Pagination from '../../components/Pagination';
import RequestStatusBadge from '../../components/RequestStatusBadge';
import api, { errorMessage } from '../../services/api';
import { requestFormPaths, requestSummary, requestTypeLabels } from '../../services/labels';

export default function RequestList() {
    const [requests, setRequests] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [type, setType] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        api.get('/requests', { params: { page, type: type || undefined } })
            .then(({ data }) => {
                setRequests(data.data);
                setMeta(data.meta);
            })
            .catch((err) => setError(errorMessage(err)))
            .finally(() => setLoading(false));
    }, [page, type]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Mes demandes</h1>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(requestFormPaths).map(([key, path]) => (
                        <Link key={key} to={path} className="rounded-md bg-ink px-3 py-2 text-sm font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                            + {requestTypeLabels[key]}
                        </Link>
                    ))}
                </div>
            </div>
            <select aria-label="Type" value={type} onChange={(event) => { setType(event.target.value); setPage(1); }} className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm sm:w-60">
                <option value="">Tous les types</option>
                {Object.entries(requestTypeLabels).map(([value, label]) => (
                    <option key={value} value={value}>
                        {label}
                    </option>
                ))}
            </select>
            <Alert>{error}</Alert>
            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Référence</th>
                            <th className="px-4 py-3 font-medium">Demande</th>
                            <th className="px-4 py-3 font-medium">Période</th>
                            <th className="px-4 py-3 text-right font-medium">Durée</th>
                            <th className="px-4 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {!loading && requests.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-4 py-6 text-center text-slate-500">
                                    Aucune demande.
                                </td>
                            </tr>
                        )}
                        {requests.map((request) => {
                            const summary = requestSummary(request);

                            return (
                                <tr key={request.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <Link to={`/demandes/${request.id}`} className="font-mono text-xs text-sky-700 hover:underline">
                                            {request.reference ?? 'Brouillon'}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-xs text-slate-500">{requestTypeLabels[request.request_type]}</p>
                                        <p>{summary.title}</p>
                                    </td>
                                    <td className="px-4 py-3">{summary.period}</td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">{summary.quantity}</td>
                                    <td className="px-4 py-3">
                                        <RequestStatusBadge status={request.status} label={request.status_label} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <Pagination meta={meta} onPageChange={setPage} />
        </div>
    );
}
