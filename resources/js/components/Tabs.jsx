/**
 * Barre d'onglets : tabs = [{ key, label }], active = clé de l'onglet courant.
 */
export default function Tabs({ tabs, active, onChange }) {
    return (
        <div role="tablist" className="flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={active === tab.key}
                    onClick={() => onChange(tab.key)}
                    className={`rounded-md px-3 py-2 text-sm font-medium ${active === tab.key ? 'bg-ink text-gold-light ring-1 ring-gold/60' : 'text-slate-600 hover:bg-slate-100'}`}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
