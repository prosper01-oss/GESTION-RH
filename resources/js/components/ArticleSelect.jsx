import { useEffect, useMemo, useRef, useState } from 'react';

export const articleLabel = (article) => (article ? `REF-${String(article.id).padStart(4, '0')} — ${article.designation} (${article.unite ?? ''}/${article.presentation ?? 1})` : '');

/**
 * Sélecteur d'article avec recherche (désignation ou référence) et indication du stock.
 * articles = [{ id, designation, stock, epuise, unite, presentation }] ; onChange reçoit l'article choisi.
 */
export default function ArticleSelect({ label = 'Article', articles, value, onChange, disabled = false, error, placeholder = '— Rechercher —' }) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const selected = articles.find((article) => Number(article.id) === Number(value));

    // Le libellé de l'article choisi (y compris depuis l'extérieur) remplit le champ de recherche.
    useEffect(() => {
        setQuery(selected ? articleLabel(selected) : '');
    }, [selected?.id, articles.length]);

    useEffect(() => {
        const onMouseDown = (event) => ref.current && !ref.current.contains(event.target) && setOpen(false);
        document.addEventListener('mousedown', onMouseDown);

        return () => document.removeEventListener('mousedown', onMouseDown);
    }, []);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q || (selected && q === articleLabel(selected).toLowerCase())) {
            return articles;
        }

        return articles.filter((article) => String(article.designation ?? '').toLowerCase().includes(q) || `REF-${String(article.id).padStart(4, '0')}`.toLowerCase().includes(q));
    }, [articles, query, selected]);

    function choose(article) {
        onChange(article);
        setQuery(articleLabel(article));
        setOpen(false);
    }

    return (
        <div ref={ref} className="relative flex flex-col gap-1">
            {label && <label className="text-sm font-medium text-slate-700">{label}</label>}
            <input
                type="text"
                autoComplete="off"
                value={query}
                disabled={disabled}
                placeholder={placeholder}
                onFocus={() => setOpen(true)}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
                className={`w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-sky-200 focus:outline-none disabled:bg-slate-100 ${error ? 'border-red-400' : 'border-slate-300 bg-white'}`}
            />
            {error && <p className="text-sm text-red-600">{error}</p>}
            {open && (
                <div className="absolute top-full z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    {filtered.length === 0 && <p className="px-3 py-2 text-sm text-slate-500">Aucun résultat.</p>}
                    {filtered.map((article) => (
                        <button
                            key={article.id}
                            type="button"
                            onClick={() => choose(article)}
                            className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 ${Number(value) === Number(article.id) ? 'bg-sky-50' : ''}`}
                        >
                            <span className="min-w-0 truncate">
                                REF-{String(article.id).padStart(4, '0')} — {article.designation}
                            </span>
                            <span className={`inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${article.epuise ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'}`}>
                                {article.epuise ? 'ÉPUISÉ' : `${article.stock_vendable ?? article.stock} u.`}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
