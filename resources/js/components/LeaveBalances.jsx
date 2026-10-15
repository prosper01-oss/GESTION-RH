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
 * Barre de progression vers le plafond de 90 jours.
 */
function CapBar({ total, max }) {
    const pct = Math.min(100, Math.round((total / max) * 100));
    const color = pct >= 100 ? 'bg-red-500' : pct >= 80 ? 'bg-amber-400' : 'bg-sky-500';

    return (
        <div className="flex items-center gap-3 text-sm">
            <span className="w-32 shrink-0 text-slate-500">Plafond {max} j</span>
            <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${pct}%` }} />
            </div>
            <span className={`w-20 text-right font-medium ${pct >= 100 ? 'text-red-600' : 'text-slate-700'}`}>
                {formatDays(total)} / {max} j
            </span>
        </div>
    );
}

/**
 * Tableau mensuel multi-années (toutes les années avec un solde + l'année en cours).
 * 2026 : janvier → mois actuel. Années passées : janvier → décembre.
 */
function MonthlyBreakdown({ url }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const currentYear = new Date().getFullYear();
    const currentMonthKey = `${currentYear}-${String(new Date().getMonth() + 1).padStart(2, '0')}`;

    // Années réduites : par défaut l'année en cours est ouverte, les autres fermées
    const [openYears, setOpenYears] = useState([currentYear]);

    useEffect(() => {
        api.get(`${url}/monthly`)
            .then(({ data: res }) => {
                setData(res.data);
                // Ouvrir toutes les années qui ont des données
                const allYears = (res.data ?? []).flatMap((item) =>
                    item.years.map((y) => y.year),
                );
                setOpenYears([...new Set([...allYears, currentYear])]);
            })
            .catch((err) => {
                if (err.response?.status !== 403) {
                    setError(errorMessage(err));
                }
            });
    }, [url]);

    if (error) {
        return <p className="text-sm text-red-600">{error}</p>;
    }

    if (!data) {
        return null;
    }

    const toggleYear = (y) =>
        setOpenYears((prev) => (prev.includes(y) ? prev.filter((x) => x !== y) : [...prev, y]));

    // Filtrer les mois vides sauf le mois en cours
    const filterMonths = (months, yearNum) =>
        months.filter(
            (row) =>
                (yearNum === currentYear && row.month === currentMonthKey) ||
                row.acquired !== 0 ||
                row.consumed !== 0 ||
                row.available !== 0,
        );

    // Ne garder que les types qui ont au moins une année avec des données
    const types = data.filter((item) =>
        item.years.some((y) => filterMonths(y.months, y.year).length > 0),
    );

    if (types.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                Congés disponibles — mois par mois
            </p>
            {types.map((item) => (
                <div key={item.leave_type.id} className="rounded-lg border border-slate-200">
                    <p className="border-b border-slate-100 px-4 py-2 text-sm font-medium text-slate-700">
                        {item.leave_type.name}
                    </p>
                    {[...item.years].reverse().map(({ year: y, months }) => {
                        const visibleMonths = filterMonths(months, y);
                        if (visibleMonths.length === 0) {
                            return null;
                        }
                        const isOpen = openYears.includes(y);
                        return (
                            <div key={y} className="border-b border-slate-100 last:border-0">
                                <button
                                    type="button"
                                    onClick={() => toggleYear(y)}
                                    className="flex w-full items-center justify-between px-4 py-2 text-left hover:bg-slate-50"
                                >
                                    <span className={`text-sm font-semibold ${y === currentYear ? 'text-sky-700' : 'text-slate-600'}`}>
                                        {y} {y === currentYear && '— en cours'}
                                    </span>
                                    <span className="text-xs text-slate-400">{isOpen ? '▲' : '▼'}</span>
                                </button>
                                {isOpen && (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase">
                                                <tr>
                                                    <th className="px-4 py-2">Mois</th>
                                                    <th className="px-4 py-2 text-right">Acquis</th>
                                                    <th className="px-4 py-2 text-right">Consommés</th>
                                                    <th className="px-4 py-2 text-right">Disponible fin du mois</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {visibleMonths.map((row) => {
                                                    const isCurrent = row.month === currentMonthKey;
                                                    return (
                                                        <tr key={row.month} className={isCurrent ? 'bg-sky-50 font-medium' : 'hover:bg-slate-50'}>
                                                            <td className="px-4 py-2 capitalize">{row.label}</td>
                                                            <td className="px-4 py-2 text-right text-emerald-700">
                                                                {row.acquired > 0 ? `+${row.acquired.toLocaleString('fr-FR')}` : '—'}
                                                            </td>
                                                            <td className="px-4 py-2 text-right text-red-700">
                                                                {row.consumed > 0 ? `-${row.consumed.toLocaleString('fr-FR')}` : '—'}
                                                            </td>
                                                            <td className={`px-4 py-2 text-right font-semibold ${row.available > 0 ? 'text-sky-800' : 'text-slate-400'}`}>
                                                                {formatDays(row.available)}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}

/**
 * Tableau récapitulatif du solde restant par année (toutes les années avec un solde).
 */
function YearsSummary({ url }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get(`${url}/years`)
            .then(({ data: res }) => setData(res.data))
            .catch((err) => setError(errorMessage(err)));
    }, [url]);

    if (error) {
        return <Alert>{error}</Alert>;
    }
    if (!data) {
        return null;
    }

    const hasYears = data.some((item) => item.years.length > 0);
    if (!hasYears) {
        return null;
    }

    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p className="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Solde restant par année</p>
            <div className="flex flex-col gap-4">
                {data.map((item) => {
                    if (item.years.length === 0) {
                        return null;
                    }

                    return (
                        <div key={item.leave_type.id}>
                            <p className="mb-2 text-sm font-medium text-slate-700">{item.leave_type.name}</p>
                            <div className="mb-2 flex flex-wrap gap-3">
                                {item.years.map((row) => (
                                    <div key={row.year} className="flex items-baseline gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span className="text-slate-500">{row.year}</span>
                                        <span className={`text-base font-semibold ${row.available > 0 ? 'text-sky-800' : 'text-slate-400'}`}>
                                            {formatDays(row.available)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            {item.max_days !== null && (
                                <CapBar total={item.total_available} max={item.max_days} />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

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
            {/* Tableau mensuel multi-années */}
            <MonthlyBreakdown url={url} />

            {/* Récapitulatif multi-années */}
            <YearsSummary url={url} />

            <div className="flex items-center gap-3">
                <label htmlFor="annee-solde" className="text-sm text-slate-600">
                    Détail année
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
