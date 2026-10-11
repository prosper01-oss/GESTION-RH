import { Link } from 'react-router-dom';

/**
 * Chiffre clé : libellé, valeur et précision éventuelle. Lien facultatif vers le détail.
 */
export default function StatTile({ label, value, hint, to }) {
    const content = (
        <>
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-slate-900 tabular-nums">{value ?? '—'}</p>
            {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
        </>
    );
    const className = 'rounded-lg border border-slate-200 bg-white p-4';

    return to ? (
        <Link to={to} className={`${className} hover:border-sky-300`}>
            {content}
        </Link>
    ) : (
        <div className={className}>{content}</div>
    );
}
