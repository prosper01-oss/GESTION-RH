import { useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import api, { errorMessage, validationErrors } from '../../services/api';

export default function PublicHolidaySettings() {
    const currentYear = new Date().getFullYear();
    const [year, setYear] = useState(currentYear);
    const [holidays, setHolidays] = useState([]);
    const [form, setForm] = useState({ date: '', label: '', is_recurring: false });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');

    function load() {
        api.get('/admin/public-holidays', { params: { year } })
            .then(({ data }) => setHolidays(data.data))
            .catch((err) => setMessage(errorMessage(err)));
    }

    useEffect(load, [year]); // eslint-disable-line react-hooks/exhaustive-deps

    async function add(event) {
        event.preventDefault();
        setErrors({});
        setMessage('');
        try {
            await api.post('/admin/public-holidays', form);
            setForm({ date: '', label: '', is_recurring: false });
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422) {
                setMessage(errorMessage(error));
            }
        }
    }

    async function remove(holiday) {
        if (!window.confirm(`Supprimer « ${holiday.label} » ?`)) {
            return;
        }
        try {
            await api.delete(`/admin/public-holidays/${holiday.id}`);
            load();
        } catch (error) {
            setMessage(errorMessage(error));
        }
    }

    function displayDate(holiday) {
        const date = new Date(`${holiday.date}T00:00:00`);

        return holiday.is_recurring
            ? date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
            : date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-xl font-semibold">Jours fériés</h1>
                <select aria-label="Année" value={year} onChange={(event) => setYear(Number(event.target.value))} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    {[currentYear - 1, currentYear, currentYear + 1].map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>
            <Alert type="info">Les fêtes à date fixe sont récurrentes. Les fêtes mobiles (lundi de Pâques, Ascension, lundi de Pentecôte) sont à ajouter chaque année.</Alert>
            <Alert>{message}</Alert>

            <form onSubmit={add} className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end">
                <Field label="Date" name="date" type="date" required value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} error={errors.date} />
                <div className="flex-1">
                    <Field label="Libellé" name="label" required value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} error={errors.label} />
                </div>
                <label className="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" checked={form.is_recurring} onChange={(e) => setForm({ ...form, is_recurring: e.target.checked })} />
                    Chaque année
                </label>
                <Button type="submit">Ajouter</Button>
            </form>

            <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {holidays.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucun jour férié.</li>}
                {holidays.map((holiday) => (
                    <li key={holiday.id} className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <div>
                            <p className="font-medium">{holiday.label}</p>
                            <p className="text-slate-500">
                                {displayDate(holiday)}
                                {holiday.is_recurring && ' · chaque année'}
                            </p>
                        </div>
                        <button type="button" className="text-red-600 hover:underline" onClick={() => remove(holiday)}>
                            Supprimer
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
