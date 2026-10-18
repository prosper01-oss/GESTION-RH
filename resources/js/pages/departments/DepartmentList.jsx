import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import EmployeePicker from '../../components/EmployeePicker';
import Field from '../../components/Field';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage, validationErrors } from '../../services/api';

const emptyForm = { code: '', name: '', manager: null };

export default function DepartmentList() {
    const { can } = useAuth();
    const canManage = can('departments.manage');
    const [departments, setDepartments] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function load() {
        api.get('/departments')
            .then(({ data }) => setDepartments(data.data))
            .catch((err) => setMessage(errorMessage(err)));
    }

    useEffect(load, []);

    function startEdit(department) {
        setEditingId(department.id);
        setForm({ code: department.code, name: department.name, manager: department.manager ?? null });
        setErrors({});
    }

    function reset() {
        setEditingId(null);
        setForm(emptyForm);
        setErrors({});
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage('');
        const payload = { code: form.code, name: form.name, manager_employee_id: form.manager?.id ?? null };

        try {
            if (editingId) {
                await api.put(`/departments/${editingId}`, payload);
            } else {
                await api.post('/departments', payload);
            }
            reset();
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        } finally {
            setSubmitting(false);
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Services</h1>
            <Alert>{message}</Alert>

            {canManage && (
                <form onSubmit={handleSubmit} className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end">
                    <div className="sm:w-40">
                        <Field label="Code" name="code" required value={form.code} onChange={update} error={errors.code} />
                    </div>
                    <div className="flex-1">
                        <Field label="Nom du service" name="name" required value={form.name} onChange={update} error={errors.name} />
                    </div>
                    <div className="flex-1">
                        <EmployeePicker label="Responsable (valide les sorties)" required={false} value={form.manager} onChange={(manager) => setForm({ ...form, manager })} error={errors.manager_employee_id} />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={submitting}>
                            {editingId ? 'Enregistrer' : 'Ajouter'}
                        </Button>
                        {editingId && (
                            <Button variant="secondary" onClick={reset}>
                                Annuler
                            </Button>
                        )}
                    </div>
                </form>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Service</th>
                            <th className="px-4 py-3 font-medium">Responsable</th>
                            <th className="px-4 py-3 text-right font-medium">Effectif</th>
                            {canManage && <th className="px-4 py-3" />}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {departments.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-4 py-6 text-center text-slate-500">
                                    Aucun service pour le moment.
                                </td>
                            </tr>
                        )}
                        {departments.map((department) => (
                            <tr key={department.id}>
                                <td className="px-4 py-3 font-mono text-xs">{department.code}</td>
                                <td className="px-4 py-3 font-medium">{department.name}</td>
                                <td className="px-4 py-3">{department.manager ? `${department.manager.prenom} ${department.manager.nom}` : '—'}</td>
                                <td className="px-4 py-3 text-right">{department.employees_count}</td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        <button type="button" onClick={() => startEdit(department)} className="text-sky-700 hover:underline">
                                            Modifier
                                        </button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
