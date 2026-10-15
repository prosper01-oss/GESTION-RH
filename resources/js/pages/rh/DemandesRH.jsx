import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Pagination from '../../components/Pagination';
import RequestStatusBadge from '../../components/RequestStatusBadge';
import api, { errorMessage } from '../../services/api';
import { formatDate, requestSummary, requestTypeLabels } from '../../services/labels';

const tabs = [
    { key: 'conge', label: 'Congés' },
    { key: 'permission', label: 'Permissions' },
];

const statusOptions = [
    { value: '', label: 'Tous les statuts' },
    { value: 'soumise', label: 'Soumises' },
    { value: 'a_corriger', label: 'À corriger' },
    { value: 'transmise_medecin', label: 'Transmises au médecin' },
    { value: 'renvoyee_rh', label: 'Renvoyées RH' },
    { value: 'approuvee', label: 'Approuvées' },
    { value: 'finalisee', label: 'Finalisées' },
    { value: 'terminee', label: 'Terminées' },
    { value: 'refusee', label: 'Refusées' },
    { value: 'annulee', label: 'Annulées' },
];

/**
 * Page RH — toutes les demandes de congé et de permission du personnel.
 */
export default function DemandesRH() {
    const [tab, setTab] = useState('conge');
    const [status, setStatus] = useState('');
    const [requests, setRequests] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        setError('');
        api.get('/requests', {
            params: {
                scope: 'all',
                type: tab,
                status: status || undefined,
                page,
            },
        })
            .then(({ data }) => {
                setRequests(data.data);
                setMeta(data.meta);
            })
            .catch((err) => setError(errorMessage(err)))
            .finally(() => setLoading(false));
    }, [tab, status, page]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Demandes du personnel</h1>
                <div className="flex flex-wrap gap-2">
                    <Link to="/rh/demandes/conge/nouveau" className="rounded-md bg-ink px-3 py-2 text-sm font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                        + Congé
                    </Link>
                    <Link to="/rh/demandes/permission/nouveau" className="rounded-md bg-ink px-3 py-2 text-sm font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                        + Permission
                    </Link>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-4">
                <div className="flex flex-wrap gap-2 border-b border-slate-200">
                    {tabs.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => { setTab(item.key); setPage(1); }}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${tab === item.key ? 'border-sky-700 font-medium text-sky-800' : 'border-transparent text-slate-600 hover:text-slate-900'}`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
                <select
                    aria-label="Statut"
                    value={status}
                    onChange={(event) => { setStatus(event.target.value); setPage(1); }}
                    className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
                >
                    {statusOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            </div>

            <Alert>{error}</Alert>
            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Référence</th>
                            <th className="px-4 py-3 font-medium">Agent</th>
                            <th className="px-4 py-3 font-medium">Demande et période</th>
                            <th className="px-4 py-3 text-right font-medium">Durée</th>
                            <th className="px-4 py-3 font-medium">Envoyée le</th>
                            <th className="px-4 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {!loading && requests.length === 0 && (
                            <tr>
                                <td colSpan="6" className="px-4 py-6 text-center text-slate-500">
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
                                            {request.reference}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {request.employee.nom} {request.employee.prenom}
                                        </p>
                                        <p className="text-xs text-slate-500">{request.employee.department?.name}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>
                                            <span className="text-xs text-slate-500">{requestTypeLabels[request.request_type]} · </span>
                                            {summary.title}
                                        </p>
                                        <p className="text-xs text-slate-500">{summary.period}</p>
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">{summary.quantity}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">{formatDate(request.submitted_at)}</td>
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
