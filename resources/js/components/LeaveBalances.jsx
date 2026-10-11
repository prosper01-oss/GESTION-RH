import { useEffect, useState } from 'react';
import api, { errorMessage } from '../services/api';
import { formatDays } from '../services/labels';
import Alert from './Alert';

const summaryRows = [
    ['acquired', 'Acquis'],
    ['carried_over', 'Reports'],
    ['consumed', 'Consommés'],
    ['reserved', 'Réservés'],
    ['adjusted', 'Corrections'],
    ['expired', 'Périmés'],
];

/**
 * Soldes de congés d'une année, avec le détail des mouvements.
 * url : /leave-balances/me ou /leave-balances/{id}. reloadKey force le rechargement.
 */
export default function LeaveBalances({ url, reloadKey = 0 }) {
    const currentYear = new Date().getFullYear();
    const [year, setYear] = useState(currentYear);
    const [balances, setBalances] = useState(null);
    const [error, setError] = useState('');
    const [openHistory, setOpenHistory] = useState(null);

    useEffect(() => {
        setError('');
        api.get(url, { params: { year } })
            .then(({ data }) => setBalances(data.data))
            .catch((err) => setError(errorMessage(err)));
    }, [url, year, reloadKey]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center gap-3">
                <label htmlFor="annee-solde" className="text-sm text-slate-600">
                    Année
                </label>
                <select id="annee-solde" value={year} onChange={(event) => setYear(Number(event.target.value))} className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    {[currentYear + 1, currentYear, currentYear - 1, currentYear - 2].map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>

            <Alert>{error}</Alert>
            {balances && balances.length === 0 && <p className="text-sm text-slate-500">Aucun type de congé tenu en solde n'est paramétré.</p>}

            {balances?.map((balance) => (
                <div key={balance.leave_type.id} className="rounded-lg border border-slate-200">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <p className="font-medium">{balance.leave_type.name}</p>
                        <p className="text-sm text-slate-600">
                            Disponible : <span className="text-lg font-semibold text-sky-800">{formatDays(balance.summary.available)}</span>
                        </p>
                    </div>
                    <dl className="grid grid-cols-3 gap-3 px-4 py-3 text-sm sm:grid-cols-6">
                        {summaryRows.map(([key, label]) => (
                            <div key={key}>
                                <dt className="text-slate-500">{label}</dt>
                                <dd className="font-medium">{formatDays(balance.summary[key])}</dd>
                            </div>
                        ))}
                    </dl>
                    {balance.entries.length > 0 && (
                        <div className="border-t border-slate-100 px-4 py-2">
                            <button type="button" className="text-sm text-sky-700 hover:underline" onClick={() => setOpenHistory(openHistory === balance.leave_type.id ? null : balance.leave_type.id)}>
                                {openHistory === balance.leave_type.id ? "Masquer l'historique" : `Historique (${balance.entries.length} mouvements)`}
                            </button>
                            {openHistory === balance.leave_type.id && (
                                <div className="mt-2 overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="text-left text-slate-500">
                                            <tr>
                                                <th className="py-1 pr-4 font-medium">Date</th>
                                                <th className="py-1 pr-4 font-medium">Mouvement</th>
                                                <th className="py-1 pr-4 text-right font-medium">Jours</th>
                                                <th className="py-1 pr-4 font-medium">Motif</th>
                                                <th className="py-1 font-medium">Par</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {balance.entries.map((entry) => (
                                                <tr key={entry.id}>
                                                    <td className="py-1 pr-4 whitespace-nowrap">{new Date(entry.created_at).toLocaleDateString('fr-FR')}</td>
                                                    <td className="py-1 pr-4">{entry.entry_type_label}</td>
                                                    <td className={`py-1 pr-4 text-right font-mono ${entry.days < 0 ? 'text-red-700' : 'text-emerald-700'}`}>
                                                        {entry.days > 0 ? '+' : ''}
                                                        {entry.days.toLocaleString('fr-FR')}
                                                    </td>
                                                    <td className="py-1 pr-4">{entry.reason ?? '—'}</td>
                                                    <td className="py-1">{entry.author ?? 'Automatique'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
