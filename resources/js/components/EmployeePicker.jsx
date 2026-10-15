import { useEffect, useRef, useState } from 'react';
import api from '../services/api';

/**
 * Champ de recherche d'un agent par nom ou matricule.
 * Appelle onChange(employee) dès qu'un agent est sélectionné, ou onChange(null) si le champ est vidé.
 */
export default function EmployeePicker({ label = 'Agent', value, onChange, error }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    // Fermer la liste au clic en dehors
    useEffect(() => {
        function handleClickOutside(event) {
            if (ref.current && !ref.current.contains(event.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Recherche avec délai
    useEffect(() => {
        if (query.length < 2) {
            setResults([]);
            setOpen(false);
            return;
        }
        const timer = setTimeout(() => {
            api.get('/employees', { params: { search: query, status: 'actif' } })
                .then(({ data }) => {
                    setResults(data.data);
                    setOpen(true);
                })
                .catch(() => setResults([]));
        }, 250);
        return () => clearTimeout(timer);
    }, [query]);

    function select(employee) {
        onChange(employee);
        setQuery(`${employee.nom} ${employee.prenom}`);
        setOpen(false);
    }

    function handleChange(event) {
        setQuery(event.target.value);
        if (!event.target.value) {
            onChange(null);
        }
    }

    return (
        <div ref={ref} className="relative flex flex-col gap-1">
            {label && <label className="text-sm font-medium text-slate-700">{label} <span className="text-red-500">*</span></label>}
            <input
                type="text"
                autoComplete="off"
                placeholder="Nom ou matricule…"
                value={value ? `${value.nom} ${value.prenom}` : query}
                onChange={handleChange}
                onFocus={() => { if (query.length >= 2) { setOpen(true); } }}
                className={`w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 ${error ? 'border-red-400 bg-red-50' : 'border-slate-300 bg-white'}`}
            />
            {value && (
                <p className="text-xs text-slate-500">{value.matricule} · {value.department?.name ?? ''}</p>
            )}
            {error && <p className="text-xs text-red-600">{error}</p>}
            {open && results.length > 0 && (
                <ul className="absolute top-full z-10 mt-1 w-full rounded-md border border-slate-200 bg-white shadow-lg">
                    {results.map((emp) => (
                        <li key={emp.id}>
                            <button
                                type="button"
                                onClick={() => select(emp)}
                                className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            >
                                <span className="font-medium">{emp.nom} {emp.prenom}</span>
                                <span className="text-xs text-slate-500">{emp.matricule} · {emp.department?.name ?? ''}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
