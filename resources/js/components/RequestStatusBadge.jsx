const styles = {
    brouillon: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    soumise: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    a_corriger: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    transmise_medecin: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    renvoyee_rh: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    approuvee: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    refusee: 'bg-red-50 text-red-700 ring-red-600/20',
    finalisee: 'bg-emerald-100 text-emerald-800 ring-emerald-700/20',
    terminee: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    annulee: 'bg-slate-100 text-slate-500 ring-slate-400/20 line-through',
};

export default function RequestStatusBadge({ status, label }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset ${styles[status] ?? styles.brouillon}`}>
            {label}
        </span>
    );
}
