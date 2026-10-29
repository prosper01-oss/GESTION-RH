import { useEffect, useMemo, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import SearchInput from '../../components/SearchInput';
import StatTile from '../../components/StatTile';
import api, { errorMessage } from '../../services/api';
import { formatDateHeure, mouvementLabels } from '../../services/pharma';

const emptyFilters = { date_debut: '', date_fin: '', type: '' };
const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

export default function Mouvements() {
    const [filters, setFilters] = useState(emptyFilters);
    const [search, setSearch] = useState('');
    const [mouvements, setMouvements] = useState([]);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');

    function load(params = filters) {
        setLoading(true);
        setMessage('');
        api.get('/pharma/mouvements', { params: Object.fromEntries(Object.entries(params).filter(([, value]) => value)) })
            .then(({ data }) => setMouvements(data.data))
            .catch((error) => setMessage(errorMessage(error)))
            .finally(() => setLoading(false));
    }

    useEffect(() => load(), []);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q ? mouvements.filter((m) => [m.article, m.lot, m.reference, m.libelle, m.user].some((value) => String(value ?? '').toLowerCase().includes(q))) : mouvements;
    }, [mouvements, search]);

    const resume = useMemo(() => {
        let entrees = 0;
        let sorties = 0;
        mouvements.forEach((m) => (m.qte > 0 ? (entrees += m.qte) : (sorties += Math.abs(m.qte))));

        return { entrees, sorties };
    }, [mouvements]);

    function reset() {
        setFilters(emptyFilters);
        setSearch('');
        load(emptyFilters);
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Journal des mouvements de stock</h1>
            <Alert>{message}</Alert>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    load();
                }}
                className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4"
            >
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Field label="Du" name="date_debut" type="date" value={filters.date_debut} onChange={(e) => setFilters({ ...filters, date_debut: e.target.value })} />
                    <Field label="Au" name="date_fin" type="date" value={filters.date_fin} onChange={(e) => setFilters({ ...filters, date_fin: e.target.value })} />
                    <Field label="Type" name="type" as="select" value={filters.type} onChange={(e) => setFilters({ ...filters, type: e.target.value })}>
                        <option value="">Tous</option>
                        {Object.entries(mouvementLabels).map(([type, label]) => (
                            <option key={type} value={type}>{label}</option>
                        ))}
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button type="submit" disabled={loading}>Filtrer</Button>
                        <Button variant="secondary" onClick={reset}>Réinitialiser</Button>
                    </div>
                </div>
                <SearchInput value={search} onChange={setSearch} placeholder="Article, lot, référence ou utilisateur…" />
            </form>

            {mouvements.length > 0 && (
                <div className="grid grid-cols-3 gap-3">
                    <StatTile label="Mouvements" value={mouvements.length} />
                    <StatTile label="Entrées (u.)" value={resume.entrees} />
                    <StatTile label="Sorties (u.)" value={resume.sorties} />
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Date', 'Type', 'Article', 'Lot'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                            {['Qté', 'Avant', 'Après'].map((label) => <th key={label} className="px-4 py-3 text-right font-medium">{label}</th>)}
                            {['Référence', 'Utilisateur'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan="9" className="px-4 py-8 text-center text-slate-500">
                                    {loading ? 'Chargement…' : mouvements.length === 0 ? 'Aucun mouvement pour ces filtres.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((m) => (
                            <tr key={m.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 text-xs text-slate-500">{formatDateHeure(m.date)}</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${m.qte > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'}`}>{mouvementLabels[m.type] ?? m.type}</span>
                                </td>
                                <td className="px-4 py-3">{m.article}</td>
                                <td className="px-4 py-3 font-mono text-xs">{m.lot || '—'}</td>
                                <td className={`px-4 py-3 text-right font-semibold ${m.qte > 0 ? 'text-emerald-700' : 'text-red-700'}`}>{m.qte > 0 ? '+' : ''}{m.qte}</td>
                                <td className="px-4 py-3 text-right text-xs text-slate-500">{m.stock_avant ?? '—'}</td>
                                <td className="px-4 py-3 text-right text-xs text-slate-500">{m.stock_apres ?? '—'}</td>
                                <td className="px-4 py-3 text-xs">{m.reference || '—'}</td>
                                <td className="px-4 py-3 text-xs text-slate-500">{m.user || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
