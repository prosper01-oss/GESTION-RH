import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Pagination from '../../components/Pagination';
import api, { errorMessage } from '../../services/api';

export default function AuditLogList() {
    const [logs, setLogs] = useState([]);
    const [meta, setMeta] = useState(null);
    const [filters, setFilters] = useState({ action: '', from: '', to: '', page: 1 });
    const [error, setError] = useState('');

    useEffect(() => {
        const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value));
        api.get('/admin/audit-logs', { params })
            .then(({ data }) => {
                setLogs(data.data);
                setMeta(data.meta);
                setError('');
            })
            .catch((err) => setError(errorMessage(err)));
    }, [filters]);

    const update = (event) => setFilters({ ...filters, [event.target.name]: event.target.value, page: 1 });
    const inputClass = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm';

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Journal d'audit</h1>
            <div className="flex flex-col gap-3 sm:flex-row">
                <input name="action" placeholder="Action (ex. auth.login)" aria-label="Action" value={filters.action} onChange={update} className={`flex-1 ${inputClass}`} />
                <input name="from" type="date" aria-label="Du" value={filters.from} onChange={update} className={inputClass} />
                <input name="to" type="date" aria-label="Au" value={filters.to} onChange={update} className={inputClass} />
            </div>
            <Alert>{error}</Alert>
            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Utilisateur</th>
                            <th className="px-4 py-3 font-medium">Action</th>
                            <th className="px-4 py-3 font-medium">Élément</th>
                            <th className="px-4 py-3 font-medium">Champs modifiés</th>
                            <th className="px-4 py-3 font-medium">Adresse IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {logs.map((log) => (
                            <tr key={log.id}>
                                <td className="px-4 py-3 whitespace-nowrap">{new Date(log.created_at).toLocaleString('fr-FR')}</td>
                                <td className="px-4 py-3">{log.user?.name ?? '—'}</td>
                                <td className="px-4 py-3 font-mono text-xs">{log.action}</td>
                                <td className="px-4 py-3">{log.entity_type ? `${log.entity_type} #${log.entity_id}` : '—'}</td>
                                <td className="px-4 py-3">{log.changed_fields.join(', ') || '—'}</td>
                                <td className="px-4 py-3 font-mono text-xs">{log.ip_address}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination meta={meta} onPageChange={(page) => setFilters({ ...filters, page })} />
        </div>
    );
}
