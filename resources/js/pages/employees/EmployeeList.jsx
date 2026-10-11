import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import Pagination from '../../components/Pagination';
import StatusBadge from '../../components/StatusBadge';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage } from '../../services/api';
import { employeeStatusLabels } from '../../services/labels';

export default function EmployeeList() {
    const { can } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();
    const [employees, setEmployees] = useState([]);
    const [meta, setMeta] = useState(null);
    const [departments, setDepartments] = useState([]);
    const [search, setSearch] = useState(searchParams.get('search') ?? '');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get('/departments').then(({ data }) => setDepartments(data.data));
    }, []);

    useEffect(() => {
        setLoading(true);
        setError('');
        api.get('/employees', { params: Object.fromEntries(searchParams) })
            .then(({ data }) => {
                setEmployees(data.data);
                setMeta(data.meta);
            })
            .catch((err) => setError(errorMessage(err)))
            .finally(() => setLoading(false));
    }, [searchParams]);

    // Recherche lancée 300 ms après la dernière frappe.
    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (searchParams.get('search') ?? '')) {
                setFilter('search', search);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    function setFilter(name, value) {
        const next = new URLSearchParams(searchParams);
        if (value) {
            next.set(name, value);
        } else {
            next.delete(name);
        }
        if (name !== 'page') {
            next.delete('page');
        }
        setSearchParams(next);
    }

    const selectClass = 'rounded-md border border-slate-300 bg-white px-3 py-2 text-sm';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Personnel</h1>
                {can('employees.manage') && (
                    <div className="flex gap-2">
                        <Link to="/personnel/import" className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Importer
                        </Link>
                        <Link to="/personnel/nouveau" className="rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">
                            Nouvel employé
                        </Link>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
                <input
                    type="search"
                    placeholder="Nom, prénom ou matricule"
                    aria-label="Rechercher"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    className="flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                />
                <select aria-label="Service" className={selectClass} value={searchParams.get('department_id') ?? ''} onChange={(event) => setFilter('department_id', event.target.value)}>
                    <option value="">Tous les services</option>
                    {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                            {department.name}
                        </option>
                    ))}
                </select>
                <select aria-label="Statut" className={selectClass} value={searchParams.get('status') ?? ''} onChange={(event) => setFilter('status', event.target.value)}>
                    <option value="">Tous les statuts</option>
                    {Object.entries(employeeStatusLabels).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>

            <Alert>{error}</Alert>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Matricule</th>
                            <th className="px-4 py-3 font-medium">Nom et prénoms</th>
                            <th className="px-4 py-3 font-medium">Service</th>
                            <th className="px-4 py-3 font-medium">Fonction</th>
                            <th className="px-4 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {loading && (
                            <tr>
                                <td colSpan="5" className="px-4 py-6 text-center text-slate-500">
                                    Chargement…
                                </td>
                            </tr>
                        )}
                        {!loading && employees.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-4 py-6 text-center text-slate-500">
                                    Aucun employé ne correspond à la recherche.
                                </td>
                            </tr>
                        )}
                        {!loading &&
                            employees.map((employee) => (
                                <tr key={employee.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-mono text-xs">{employee.matricule}</td>
                                    <td className="px-4 py-3">
                                        <Link to={`/personnel/${employee.id}`} className="font-medium text-sky-700 hover:underline">
                                            {employee.nom} {employee.prenom}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{employee.department?.name}</td>
                                    <td className="px-4 py-3">{employee.position}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={employee.status} />
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={(page) => setFilter('page', String(page))} />
        </div>
    );
}
