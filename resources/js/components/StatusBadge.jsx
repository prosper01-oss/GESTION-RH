import { employeeStatusLabels } from '../services/labels';

const styles = {
    actif: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    inactif: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

export default function StatusBadge({ status }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${styles[status] ?? styles.inactif}`}>
            {employeeStatusLabels[status] ?? status}
        </span>
    );
}
