import { useEffect, useState } from 'react';
import api from '../services/api';
import { formatDays } from '../services/labels';

function countDays(start, end, workday) {
    let days = 0;
    for (const day = new Date(start.getFullYear(), start.getMonth(), start.getDate()); day < end; day.setDate(day.getDate() + 1)) {
        const dayStart = workday ? new Date(day.getFullYear(), day.getMonth(), day.getDate(), 8) : new Date(day);
        const dayEnd = workday ? new Date(day.getFullYear(), day.getMonth(), day.getDate(), 17) : new Date(day.getFullYear(), day.getMonth(), day.getDate() + 1);
        const hours = (Math.min(end, dayEnd) - Math.max(start, dayStart)) / 3600000;
        if (hours > 0) {
            days += hours < 4 ? 0.5 : 1;
        }
    }

    return days;
}

/**
 * Aperçu des jours de permission décomptés : par date, sur les heures de service (8 h – 17 h),
 * moins de 4 h = une demi-journée, sinon une journée. Le serveur (App\Services\PermissionQuota) fait foi.
 */
export function countPermissionDays(startAt, endAt) {
    const start = new Date(startAt);
    const end = new Date(endAt);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
        return 0;
    }

    return countDays(start, end, true) || countDays(start, end, false);
}

/**
 * Quota annuel de permissions : jours accordés, engagés et restants (remis à zéro chaque 1er janvier).
 */
export default function PermissionQuota({ url, requestedDays = 0 }) {
    const [quota, setQuota] = useState(null);

    useEffect(() => {
        if (!url) {
            setQuota(null);
            return;
        }
        api.get(url)
            .then(({ data }) => setQuota(data.data))
            .catch(() => setQuota(null));
    }, [url]);

    if (!quota || quota.entitlement === null) {
        return null;
    }

    const exceeds = requestedDays > quota.remaining;
    const percent = Math.min(100, (quota.used / quota.entitlement) * 100);

    return (
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="font-medium text-slate-700">Permissions {quota.year}</p>
                <p className="text-slate-600">
                    Reste <strong className={quota.remaining > 0 ? 'text-sky-800' : 'text-red-700'}>{formatDays(quota.remaining)}</strong> sur {formatDays(quota.entitlement)}
                </p>
            </div>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div className="h-full rounded-full bg-sky-600" style={{ width: `${percent}%` }} />
            </div>
            <p className="mt-2 text-xs text-slate-500">
                {formatDays(quota.used)} déjà pris ou en cours. Le quota repart à {formatDays(quota.entitlement)} chaque 1er janvier, sans report du reliquat.
            </p>
            {requestedDays > 0 && (
                <p className={`mt-1 text-xs ${exceeds ? 'font-medium text-red-700' : 'text-slate-500'}`}>
                    Cette demande compte {formatDays(requestedDays)}
                    {exceeds ? ' : elle dépasse le quota restant.' : '.'}
                </p>
            )}
        </div>
    );
}
