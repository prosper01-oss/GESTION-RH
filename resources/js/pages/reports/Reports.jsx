import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Field from '../../components/Field';
import StatTile from '../../components/StatTile';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDays, formatHours, requestTypeLabels } from '../../services/labels';

function isoDate(date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

/**
 * Périodes prédéfinies pour les rapports mensuel et annuel (EF-74).
 */
function presets() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();

    return {
        'Ce mois-ci': [new Date(year, month, 1), new Date(year, month + 1, 0)],
        'Mois dernier': [new Date(year, month - 1, 1), new Date(year, month, 0)],
        'Cette année': [new Date(year, 0, 1), new Date(year, 11, 31)],
        'Année dernière': [new Date(year - 1, 0, 1), new Date(year - 1, 11, 31)],
    };
}

const statusOrder = ['soumise', 'a_corriger', 'transmise_medecin', 'renvoyee_rh', 'approuvee', 'finalisee', 'terminee', 'refusee', 'annulee'];

export default function Reports() {
    const [dashboard, setDashboard] = useState(null);
    const [departments, setDepartments] = useState([]);
    const [filters, setFilters] = useState(() => {
        const [from, to] = presets()['Ce mois-ci'];

        return { from: isoDate(from), to: isoDate(to), department_id: '', type: '' };
    });
    const [report, setReport] = useState(null);
    const [errors, setErrors] = useState({});
    const [error, setError] = useState('');

    useEffect(() => {
        api.get('/reports/dashboard').then(({ data }) => setDashboard(data.data)).catch((err) => setError(errorMessage(err)));
        api.get('/departments').then(({ data }) => setDepartments(data.data));
    }, []);

    useEffect(() => {
        if (!filters.from || !filters.to) {
            return;
        }
        setErrors({});
        api.get('/reports/absences', { params: cleanFilters(filters) })
            .then(({ data }) => setReport(data))
            .catch((err) => {
                setErrors(validationErrors(err));
                setReport(null);
                if (err.response?.status !== 422 || !Object.keys(validationErrors(err)).length) {
                    setError(errorMessage(err));
                }
            });
    }, [filters]);

    function cleanFilters(values) {
        return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));
    }

    function exportUrl(path, format) {
        return `/api/v1${path}?${new URLSearchParams({ ...cleanFilters(filters), format })}`;
    }

    const update = (event) => setFilters({ ...filters, [event.target.name]: event.target.value });
    const statusTotals = dashboard
        ? statusOrder
              .map((status) => ({
                  status,
                  label: dashboard.requests_by_type_and_status.find((row) => row.status === status)?.status_label,
                  byType: Object.fromEntries(
                      Object.keys(requestTypeLabels).map((type) => [
                          type,
                          dashboard.requests_by_type_and_status.find((row) => row.status === status && row.request_type === type)?.total ?? 0,
                      ]),
                  ),
              }))
              .filter((row) => row.label)
        : [];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Tableau de bord RH</h1>
                <div className="flex gap-2 text-sm">
                    <a href={`/api/v1/reports/staff/export?format=pdf`} className="rounded-md border border-slate-300 bg-white px-3 py-2 hover:bg-slate-50">
                        Effectif (PDF)
                    </a>
                    <a href={`/api/v1/reports/staff/export?format=xlsx`} className="rounded-md border border-slate-300 bg-white px-3 py-2 hover:bg-slate-50">
                        Effectif (Excel)
                    </a>
                </div>
            </div>
            <Alert>{error}</Alert>

            {dashboard && (
                <>
                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile label="Personnel actif" value={dashboard.active_employees} to="/personnel" />
                        <StatTile label="Absents aujourd'hui" value={dashboard.absent_today} hint="Congé ou permission en cours" />
                        <StatTile label="Congés accordés ce mois-ci" value={dashboard.leaves_granted_this_month} />
                        <StatTile label="Sorties en cours" value={dashboard.exits_in_progress} hint={`${dashboard.exits_today} sortie(s) prévue(s) aujourd'hui`} to="/sorties" />
                    </section>
                    <section className="grid gap-4 sm:grid-cols-3">
                        <StatTile label="À contrôler (RH)" value={dashboard.pending.control} to="/a-traiter" />
                        <StatTile label="À décider (Médecin-Chef)" value={dashboard.pending.decide} to="/a-traiter" />
                        <StatTile label="À finaliser (RH)" value={dashboard.pending.finalize} to="/a-traiter" />
                    </section>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="rounded-lg border border-slate-200 bg-white">
                            <h2 className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Effectif actif par service</h2>
                            <table className="min-w-full text-sm">
                                <tbody className="divide-y divide-slate-100">
                                    {dashboard.employees_by_department.map((department) => (
                                        <tr key={department.name}>
                                            <td className="px-4 py-2">{department.name}</td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums">{department.count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                        <section className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                            <h2 className="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Demandes par type et par statut</h2>
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Statut</th>
                                        {Object.values(requestTypeLabels).map((label) => (
                                            <th key={label} className="px-4 py-2 text-right font-medium">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {statusTotals.length === 0 && (
                                        <tr>
                                            <td colSpan="4" className="px-4 py-4 text-center text-slate-500">
                                                Aucune demande envoyée.
                                            </td>
                                        </tr>
                                    )}
                                    {statusTotals.map((row) => (
                                        <tr key={row.status}>
                                            <td className="px-4 py-2">{row.label}</td>
                                            {Object.keys(requestTypeLabels).map((type) => (
                                                <td key={type} className="px-4 py-2 text-right tabular-nums">
                                                    {row.byType[type]}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    </div>
                </>
            )}

            <section className="flex flex-col gap-4">
                <h2 className="text-lg font-semibold">Rapport des absences</h2>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(presets()).map(([label, [from, to]]) => (
                        <button key={label} type="button" onClick={() => setFilters({ ...filters, from: isoDate(from), to: isoDate(to) })} className="rounded-full border border-slate-300 bg-white px-3 py-1 text-sm hover:bg-slate-50">
                            {label}
                        </button>
                    ))}
                </div>
                <div className="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4">
                    <Field label="Du" name="from" type="date" value={filters.from} onChange={update} error={errors.from} />
                    <Field label="Au" name="to" type="date" value={filters.to} onChange={update} error={errors.to} />
                    <Field label="Service" name="department_id" as="select" value={filters.department_id} onChange={update}>
                        <option value="">Tous</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </Field>
                    <Field label="Type" name="type" as="select" value={filters.type} onChange={update}>
                        <option value="">Tous</option>
                        {Object.entries(requestTypeLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </Field>
                </div>

                {report && (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm text-slate-600">
                                Congés : <strong>{report.totals.conge.count}</strong> ({formatDays(report.totals.conge.days)}) · Permissions : <strong>{report.totals.permission.count}</strong> ({formatHours(report.totals.permission.hours)}) · Sorties :{' '}
                                <strong>{report.totals.sortie.count}</strong>
                            </p>
                            <div className="flex gap-2 text-sm">
                                <a href={exportUrl('/reports/absences/export', 'pdf')} className="rounded-md bg-ink px-3 py-2 font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                                    Exporter en PDF
                                </a>
                                <a href={exportUrl('/reports/absences/export', 'xlsx')} className="rounded-md bg-ink px-3 py-2 font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                                    Exporter en Excel
                                </a>
                            </div>
                        </div>
                        <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Agent</th>
                                        <th className="px-4 py-3 font-medium">Service</th>
                                        <th className="px-4 py-3 font-medium">Nature</th>
                                        <th className="px-4 py-3 font-medium">Début</th>
                                        <th className="px-4 py-3 font-medium">Fin</th>
                                        <th className="px-4 py-3 text-right font-medium">Durée</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {report.data.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-4 py-6 text-center text-slate-500">
                                                Aucune absence sur cette période.
                                            </td>
                                        </tr>
                                    )}
                                    {report.data.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-2">
                                                <Link to={`/demandes/${row.id}`} className="text-sky-700 hover:underline">
                                                    {row.employee}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2">{row.department}</td>
                                            <td className="px-4 py-2">
                                                <span className="text-xs text-slate-500">{row.type_label} · </span>
                                                {row.nature}
                                            </td>
                                            <td className="px-4 py-2 whitespace-nowrap">{row.start}</td>
                                            <td className="px-4 py-2 whitespace-nowrap">{row.end}</td>
                                            <td className="px-4 py-2 text-right whitespace-nowrap tabular-nums">{row.days ? formatDays(row.days) : formatHours(row.hours)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </section>
        </div>
    );
}
