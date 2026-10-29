import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import Pagination from '../../components/Pagination';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatDate, roleLabels } from '../../services/labels';
import { moduleLabels } from '../../services/pharma';

const emptyForm = { name: '', email: '', matricule: '', roles: ['personnel'] };

function RoleCheckboxes({ value, onChange, error }) {
    function toggle(role) {
        onChange(value.includes(role) ? value.filter((item) => item !== role) : [...value, role]);
    }

    return (
        <fieldset className="flex flex-col gap-1">
            <legend className="mb-1 text-sm font-medium text-slate-700">Rôles</legend>
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                {Object.entries(roleLabels).map(([role, label]) => (
                    <label key={role} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={value.includes(role)} onChange={() => toggle(role)} />
                        {label}
                    </label>
                ))}
            </div>
            {error && <p className="text-sm text-red-600">{error}</p>}
        </fieldset>
    );
}

function ModuleCheckboxes({ value, viaRoles, onChange }) {
    function toggle(module) {
        onChange(value.includes(module) ? value.filter((item) => item !== module) : [...value, module]);
    }

    return (
        <fieldset className="flex flex-col gap-1">
            <legend className="mb-1 text-sm font-medium text-slate-700">Modules Pharmacie</legend>
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                {Object.entries(moduleLabels).map(([module, label]) => {
                    const fromRole = viaRoles.includes(module);

                    return (
                        <label key={module} className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={fromRole || value.includes(module)} disabled={fromRole} onChange={() => toggle(module)} />
                            {label}
                            {fromRole && <span className="text-xs text-slate-500">(via un rôle)</span>}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}

export default function UserList() {
    const [users, setUsers] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [form, setForm] = useState(emptyForm);
    const [showForm, setShowForm] = useState(false);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [temporary, setTemporary] = useState(null);
    const [editingRoles, setEditingRoles] = useState(null);
    const [editingModules, setEditingModules] = useState(null);

    function load() {
        api.get('/admin/users', { params: { page } })
            .then(({ data }) => {
                setUsers(data.data);
                setMeta(data.meta);
            })
            .catch((err) => setMessage(errorMessage(err)));
    }

    useEffect(load, [page]); // eslint-disable-line react-hooks/exhaustive-deps

    async function createUser(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');
        try {
            const { data } = await api.post('/admin/users', { ...form, matricule: form.matricule || null });
            setTemporary({ email: data.data.email, password: data.temporary_password });
            setForm(emptyForm);
            setShowForm(false);
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        }
    }

    async function saveRoles() {
        setMessage('');
        try {
            await api.patch(`/admin/users/${editingRoles.id}/roles`, { roles: editingRoles.roles });
            setEditingRoles(null);
            load();
        } catch (error) {
            setMessage(validationErrors(error).roles ?? errorMessage(error));
        }
    }

    async function saveModules() {
        setMessage('');
        try {
            await api.put(`/admin/users/${editingModules.id}/modules`, { modules: editingModules.modules });
            setEditingModules(null);
            load();
        } catch (error) {
            setMessage(errorMessage(error));
        }
    }

    async function resetPassword(user) {
        if (!window.confirm(`Réinitialiser le mot de passe de ${user.name} ? L'ancien ne fonctionnera plus.`)) {
            return;
        }
        setMessage('');
        try {
            const { data } = await api.post(`/admin/users/${user.id}/reset-password`);
            setTemporary({ email: user.email, password: data.temporary_password });
        } catch (error) {
            setMessage(errorMessage(error));
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Comptes</h1>
                {!showForm && <Button onClick={() => setShowForm(true)}>Nouveau compte</Button>}
            </div>

            <Alert>{message}</Alert>

            {temporary && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p>
                        Mot de passe provisoire de <strong>{temporary.email}</strong> :{' '}
                        <code className="rounded bg-white px-2 py-0.5 font-mono text-base">{temporary.password}</code>
                    </p>
                    <p className="mt-1">Communiquez-le à la personne : il ne sera plus affiché. Elle devra le changer à sa première connexion.</p>
                    <button type="button" className="mt-2 font-medium underline" onClick={() => setTemporary(null)}>
                        J'ai noté le mot de passe
                    </button>
                </div>
            )}

            {showForm && (
                <form onSubmit={createUser} className="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                    <Field label="Nom affiché" name="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} />
                    <Field label="Adresse email" name="email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email} />
                    <div>
                        <Field label="Matricule de l'employé (facultatif)" name="matricule" value={form.matricule} onChange={(e) => setForm({ ...form, matricule: e.target.value })} error={errors.matricule} />
                        <p className="mt-1 text-xs text-slate-500">Rattache le compte à une fiche du personnel créée par les RH.</p>
                    </div>
                    <RoleCheckboxes value={form.roles} onChange={(roles) => setForm({ ...form, roles })} error={errors.roles ?? errors['roles.0']} />
                    <div className="flex gap-3 sm:col-span-2">
                        <Button type="submit">Créer le compte</Button>
                        <Button variant="secondary" onClick={() => { setShowForm(false); setErrors({}); }}>
                            Annuler
                        </Button>
                    </div>
                </form>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nom</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Rôles</th>
                            <th className="px-4 py-3 font-medium">Dernière connexion</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {users.map((user) => (
                            <tr key={user.id} className="align-top">
                                <td className="px-4 py-3 font-medium">
                                    {user.name}
                                    {!user.is_active && <span className="ml-2 text-xs text-slate-500">(désactivé)</span>}
                                </td>
                                <td className="px-4 py-3">{user.email}</td>
                                <td className="px-4 py-3">
                                    {editingRoles?.id === user.id ? (
                                        <div className="flex flex-col gap-2">
                                            <RoleCheckboxes value={editingRoles.roles} onChange={(roles) => setEditingRoles({ ...editingRoles, roles })} />
                                            <div className="flex gap-2">
                                                <Button onClick={saveRoles}>Enregistrer</Button>
                                                <Button variant="secondary" onClick={() => setEditingRoles(null)}>
                                                    Annuler
                                                </Button>
                                            </div>
                                        </div>
                                    ) : (
                                        user.roles.map((role) => roleLabels[role] ?? role).join(', ')
                                    )}
                                    {editingModules?.id === user.id && (
                                        <div className="mt-3 flex flex-col gap-2">
                                            <ModuleCheckboxes value={editingModules.modules} viaRoles={editingModules.viaRoles} onChange={(modules) => setEditingModules({ ...editingModules, modules })} />
                                            <div className="flex gap-2">
                                                <Button onClick={saveModules}>Enregistrer</Button>
                                                <Button variant="secondary" onClick={() => setEditingModules(null)}>
                                                    Annuler
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                    {editingModules?.id !== user.id && user.modules?.length > 0 && (
                                        <p className="mt-1 text-xs text-slate-500">Modules Pharmacie en plus : {user.modules.map((module) => moduleLabels[module] ?? module).join(', ')}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3">{formatDate(user.last_login_at)}</td>
                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button" className="mr-4 text-sky-700 hover:underline" onClick={() => setEditingRoles({ id: user.id, roles: user.roles })}>
                                        Rôles
                                    </button>
                                    <button
                                        type="button"
                                        className="mr-4 text-sky-700 hover:underline"
                                        onClick={() => setEditingModules({ id: user.id, modules: user.modules ?? [], viaRoles: user.permissions.filter((permission) => permission.startsWith('pharma.') && !(user.modules ?? []).includes(permission)) })}
                                    >
                                        Modules Pharmacie
                                    </button>
                                    <button type="button" className="text-sky-700 hover:underline" onClick={() => resetPassword(user)}>
                                        Réinitialiser
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination meta={meta} onPageChange={setPage} />
        </div>
    );
}
