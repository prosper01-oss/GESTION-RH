/**
 * Champ de formulaire avec libellé et message d'erreur de validation.
 */
export default function Field({ label, name, error, as = 'input', children, ...props }) {
    const Control = as;
    const id = `champ-${name}`;
    const border = error ? 'border-red-400 focus:ring-red-200' : 'border-slate-300 focus:ring-sky-200';

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-sm font-medium text-slate-700">
                {label}
            </label>
            <Control
                id={id}
                name={name}
                className={`rounded-md border bg-white px-3 py-2 text-sm focus:ring-2 focus:outline-none ${border}`}
                aria-invalid={error ? 'true' : undefined}
                {...props}
            >
                {children}
            </Control>
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
