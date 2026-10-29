import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import { EditButton } from '../../components/IconButton';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';
import { accrualRuleLabels, countingModeLabels, formatDate, formatDays } from '../../services/labels';

const emptyForm = {
    code: '',
    name: '',
    is_paid: true,
    has_balance: true,
    requires_attachment: false,
    is_medical: false,
    allows_half_day: false,
    min_notice_days: 0,
    attachment_deadline_hours: '',
    is_active: true,
    policy: { accrual_rule: 'monthly', annual_entitlement: 30, counting_mode: 'calendar_excluding_holidays', carry_over_max: '', effective_from: '' },
};

const flags = [
    ['is_paid', 'Rémunéré'],
    ['has_balance', 'Tenu en solde'],
    ['requires_attachment', 'Justificatif obligatoire'],
    ['is_medical', 'Justificatif médical (accès restreint)'],
    ['allows_half_day', 'Demi-journées autorisées'],
    ['is_active', 'Actif'],
];

export default function LeaveTypeSettings() {
    const [types, setTypes] = useState([]);
    const [form, setForm] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');

    function load() {
        api.get('/admin/leave-types')
            .then(({ data }) => setTypes(data.data))
            .catch((err) => setMessage(errorMessage(err)));
    }

    useEffect(load, []);

    function edit(type) {
        setEditingId(type.id);
        setErrors({});
        setForm({
            ...Object.fromEntries(Object.keys(emptyForm).filter((key) => key !== 'policy').map((key) => [key, type[key]])),
            policy: {
                accrual_rule: type.policy?.accrual_rule ?? 'monthly',
                annual_entitlement: type.policy?.annual_entitlement ?? 0,
                counting_mode: type.policy?.counting_mode ?? 'calendar',
                carry_over_max: type.policy?.carry_over_max ?? '',
                effective_from: '',
            },
        });
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');
        const payload = {
            ...form,
            min_notice_days: form.min_notice_days === '' ? 0 : Number(form.min_notice_days),
            attachment_deadline_hours: form.attachment_deadline_hours === '' || form.attachment_deadline_hours === null ? null : Number(form.attachment_deadline_hours),
            policy: { ...form.policy, carry_over_max: form.policy.carry_over_max === '' ? null : form.policy.carry_over_max, effective_from: form.policy.effective_from || null },
        };
        try {
            if (editingId) {
                await api.put(`/admin/leave-types/${editingId}`, payload);
            } else {
                await api.post('/admin/leave-types', payload);
            }
            setForm(null);
            setEditingId(null);
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            setMessage(error.response?.status === 422 ? 'Corrigez les champs signalés.' : errorMessage(error));
        }
    }

    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });
    const updatePolicy = (event) => setForm({ ...form, policy: { ...form.policy, [event.target.name]: event.target.value } });

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Types de congés</h1>
                {!form && (
                    <Button onClick={() => { setEditingId(null); setErrors({}); setForm(emptyForm); }}>
                        Nouveau type
                    </Button>
                )}
            </div>
            <Alert>{message}</Alert>

            {form && (
                <form onSubmit={handleSubmit} className="flex flex-col gap-5 rounded-lg border border-slate-200 bg-white p-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Code" name="code" required value={form.code} onChange={update} error={errors.code} />
                        <div className="sm:col-span-2">
                            <Field label="Nom" name="name" required value={form.name} onChange={update} error={errors.name} />
                        </div>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {flags.map(([name, label]) => (
                            <label key={name} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form[name]} onChange={(event) => setForm({ ...form, [name]: event.target.checked })} />
                                {label}
                            </label>
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Délai de dépôt minimal (jours avant le 1er jour, hors urgence)"
                            name="min_notice_days"
                            type="number"
                            min="0"
                            value={form.min_notice_days ?? 0}
                            onChange={(event) => setForm({ ...form, min_notice_days: event.target.value })}
                            error={errors.min_notice_days}
                        />
                        {form.requires_attachment && (
                            <Field
                                label="Justificatif à fournir sous … heures après le 1er jour (vide = avant l'envoi)"
                                name="attachment_deadline_hours"
                                type="number"
                                min="1"
                                value={form.attachment_deadline_hours ?? ''}
                                onChange={(event) => setForm({ ...form, attachment_deadline_hours: event.target.value })}
                                error={errors.attachment_deadline_hours}
                            />
                        )}
                    </div>
                    <fieldset className="grid gap-4 rounded-md border border-slate-200 p-4 sm:grid-cols-2">
                        <legend className="px-1 text-sm font-medium">Règles</legend>
                        <Field label="Acquisition" name="accrual_rule" as="select" value={form.policy.accrual_rule} onChange={updatePolicy} error={errors['policy.accrual_rule']}>
                            {Object.entries(accrualRuleLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Field>
                        <Field label="Droit annuel (jours)" name="annual_entitlement" type="number" step="0.5" min="0" value={form.policy.annual_entitlement} onChange={updatePolicy} error={errors['policy.annual_entitlement']} />
                        <Field label="Décompte" name="counting_mode" as="select" value={form.policy.counting_mode} onChange={updatePolicy} error={errors['policy.counting_mode']}>
                            {Object.entries(countingModeLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Field>
                        <Field label="Report maximal en fin d'année (vide = illimité)" name="carry_over_max" type="number" step="0.5" min="0" value={form.policy.carry_over_max ?? ''} onChange={updatePolicy} error={errors['policy.carry_over_max']} />
                        <div className="sm:col-span-2">
                            <Field label="Date d'effet des règles (vide = aujourd'hui)" name="effective_from" type="date" value={form.policy.effective_from} onChange={updatePolicy} error={errors['policy.effective_from']} />
                            <p className="mt-1 text-xs text-slate-500">Une modification des règles crée une nouvelle version : les périodes passées gardent leurs anciennes règles.</p>
                        </div>
                    </fieldset>
                    <div className="flex gap-3">
                        <Button type="submit">Enregistrer</Button>
                        <Button variant="secondary" onClick={() => setForm(null)}>
                            Annuler
                        </Button>
                    </div>
                </form>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Acquisition</th>
                            <th className="px-4 py-3 font-medium">Décompte</th>
                            <th className="px-4 py-3 font-medium">Report max.</th>
                            <th className="px-4 py-3 font-medium">Règles depuis</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {types.map((type) => (
                            <tr key={type.id} className={type.is_active ? '' : 'text-slate-400'}>
                                <td className="px-4 py-3">
                                    <span className="font-medium">{type.name}</span> <span className="font-mono text-xs">({type.code})</span>
                                    {!type.has_balance && <span className="ml-2 text-xs text-slate-500">sans solde</span>}
                                    {!type.is_active && <span className="ml-2 text-xs">inactif</span>}
                                </td>
                                <td className="px-4 py-3">
                                    {type.policy ? `${accrualRuleLabels[type.policy.accrual_rule]}${type.policy.annual_entitlement ? `, ${formatDays(type.policy.annual_entitlement)}/an` : ''}` : '—'}
                                </td>
                                <td className="px-4 py-3">{countingModeLabels[type.policy?.counting_mode] ?? '—'}</td>
                                <td className="px-4 py-3">{type.policy?.carry_over_max == null ? 'Illimité' : formatDays(type.policy.carry_over_max)}</td>
                                <td className="px-4 py-3">{formatDate(type.policy?.effective_from)}</td>
                                <td className="px-4 py-3 text-right">
                                    <EditButton onClick={() => edit(type)} label={`Modifier ${type.name}`} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
