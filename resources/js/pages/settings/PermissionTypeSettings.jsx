import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';
import { formatHours } from '../../services/labels';

const emptyForm = { name: '', is_paid: true, requires_attachment: false, is_medical: false, max_duration_hours: '', requires_details: false, is_active: true };

const flags = [
    ['is_paid', 'Rémunérée'],
    ['requires_attachment', 'Justificatif obligatoire'],
    ['is_medical', 'Justificatif médical (accès restreint)'],
    ['requires_details', 'Précision obligatoire (ex. « Autres »)'],
    ['is_active', 'Active'],
];

export default function PermissionTypeSettings() {
    const [types, setTypes] = useState([]);
    const [form, setForm] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');

    function load() {
        api.get('/admin/permission-types')
            .then(({ data }) => setTypes(data.data))
            .catch((err) => setMessage(errorMessage(err)));
    }

    useEffect(load, []);

    async function handleSubmit(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');
        const payload = { ...form, max_duration_hours: form.max_duration_hours === '' ? null : form.max_duration_hours };
        try {
            if (editingId) {
                await api.put(`/admin/permission-types/${editingId}`, payload);
            } else {
                await api.post('/admin/permission-types', payload);
            }
            setForm(null);
            setEditingId(null);
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Catégories de permission</h1>
                {!form && <Button onClick={() => { setEditingId(null); setErrors({}); setForm(emptyForm); }}>Nouvelle catégorie</Button>}
            </div>
            <Alert>{message}</Alert>

            {form && (
                <form onSubmit={handleSubmit} className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <Field label="Nom" name="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} />
                        </div>
                        <Field label="Durée maximale en heures (vide = sans limite)" name="max_duration_hours" type="number" step="0.5" min="0.5" value={form.max_duration_hours ?? ''} onChange={(e) => setForm({ ...form, max_duration_hours: e.target.value })} error={errors.max_duration_hours} />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {flags.map(([name, label]) => (
                            <label key={name} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form[name]} onChange={(e) => setForm({ ...form, [name]: e.target.checked })} />
                                {label}
                            </label>
                        ))}
                    </div>
                    <div className="flex gap-3">
                        <Button type="submit">Enregistrer</Button>
                        <Button variant="secondary" onClick={() => setForm(null)}>
                            Annuler
                        </Button>
                    </div>
                </form>
            )}

            <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {types.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucune catégorie.</li>}
                {types.map((type) => (
                    <li key={type.id} className={`flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm ${type.is_active ? '' : 'text-slate-400'}`}>
                        <div>
                            <p className="font-medium">{type.name}</p>
                            <p className="text-slate-500">
                                {type.is_paid ? 'Rémunérée' : 'Non rémunérée'}
                                {type.max_duration_hours ? ` · ${formatHours(type.max_duration_hours)} max.` : ''}
                                {type.requires_attachment ? ' · justificatif obligatoire' : ''}
                                {type.is_medical ? ' · médical' : ''}
                                {type.requires_details ? ' · précision obligatoire' : ''}
                                {!type.is_active ? ' · inactive' : ''}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="text-sky-700 hover:underline"
                            onClick={() => {
                                setEditingId(type.id);
                                setErrors({});
                                setForm({ ...type, max_duration_hours: type.max_duration_hours ?? '' });
                            }}
                        >
                            Modifier
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
