import EmployeePicker from './EmployeePicker';
import Field from './Field';

/**
 * Lendemain d'une date (AAAA-MM-JJ), ou le jour même si le congé s'arrête au matin.
 */
export function suggestedReturnDate(endDate, endsMorning) {
    if (!endDate) {
        return '';
    }
    if (endsMorning) {
        return endDate;
    }
    const date = new Date(`${endDate}T00:00:00`);
    date.setDate(date.getDate() + 1);

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

/**
 * Date de reprise du travail et intérimaire, obligatoires pour toute demande de congé.
 */
export default function ReturnAndInterimFields({ form, setForm, errors }) {
    return (
        <>
            <div className="flex flex-col gap-1">
                <Field
                    label="Date de reprise du travail"
                    name="return_date"
                    type="date"
                    required
                    min={form.end_date || undefined}
                    value={form.return_date ?? ''}
                    onChange={(event) => setForm({ ...form, return_date: event.target.value, return_date_touched: true })}
                    error={errors.return_date}
                />
                <p className="text-xs text-slate-500">Proposée automatiquement d'après le dernier jour ; modifiable (week-end, jour férié).</p>
            </div>
            <EmployeePicker
                label="Intérim assuré par"
                endpoint="/colleagues"
                value={form.interim}
                onChange={(interim) => setForm({ ...form, interim, interim_employee_id: interim?.id ?? '' })}
                error={errors.interim_employee_id}
            />
        </>
    );
}
