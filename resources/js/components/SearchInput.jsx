/**
 * Champ de filtrage d'un tableau déjà chargé.
 */
export default function SearchInput({ value, onChange, placeholder = 'Rechercher…', className = '' }) {
    return (
        <input
            type="search"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            placeholder={placeholder}
            aria-label={placeholder}
            className={`rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-sky-200 focus:outline-none ${className}`}
        />
    );
}
