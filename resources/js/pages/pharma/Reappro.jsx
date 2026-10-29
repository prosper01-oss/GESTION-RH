import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import SearchInput from '../../components/SearchInput';
import api, { errorMessage } from '../../services/api';

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

function defaultFilters() {
    const fin = new Date();
    const debut = new Date(fin);
    debut.setMonth(fin.getMonth() - 3);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    return { date_debut: fmt(debut), date_fin: fmt(fin), mois_couverture: 3, fournisseur: '' };
}

export default function Reappro() {
    const [filters, setFilters] = useState(defaultFilters);
    const [lignes, setLignes] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');

    function load(current = filters) {
        setLoading(true);
        setMessage('');
        api.get('/pharma/reappro', { params: { date_debut: current.date_debut, date_fin: current.date_fin, mois_couverture: current.mois_couverture } })
            .then(({ data }) => setLignes(data.data))
            .catch((error) => setMessage(errorMessage(error)))
            .finally(() => setLoading(false));
    }

    useEffect(() => load(), []);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q ? lignes.filter((l) => [l.reference, l.designation, l.presentation].some((value) => String(value ?? '').toLowerCase().includes(q))) : lignes;
    }, [lignes, search]);

    const update = (name) => (event) => {
        const next = { ...filters, [name]: name === 'mois_couverture' ? Number(event.target.value) : event.target.value };
        setFilters(next);
        if (name !== 'fournisseur') {
            load(next);
        }
    };

    const exportUrl = `/api/v1/pharma/reappro/export?${new URLSearchParams({ date_debut: filters.date_debut, date_fin: filters.date_fin, mois_couverture: filters.mois_couverture, fournisseur: filters.fournisseur })}`;

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Réapprovisionnement proposé</h1>
            <Alert>{message}</Alert>

            <p className="text-sm text-slate-600">
                Proposition déduite de la Consommation Moyenne Mensuelle : stock restant à zéro ou sous le seuil, quantité = <code className="rounded bg-slate-100 px-1">CMM × mois de couverture − stock</code>. Sans consommation sur la période, repli sur le stock cible (<code className="rounded bg-slate-100 px-1">qte_max</code> sinon <code className="rounded bg-slate-100 px-1">2 × seuil</code>).
            </p>

            <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <Field label="Période — du (consommation)" name="date_debut" type="date" value={filters.date_debut} onChange={update('date_debut')} />
                <Field label="au" name="date_fin" type="date" value={filters.date_fin} onChange={update('date_fin')} />
                <div className="w-28">
                    <Field label="Mois de couverture" name="mois_couverture" type="number" min="1" max="24" step="1" value={filters.mois_couverture} onChange={update('mois_couverture')} />
                </div>
                <div className="w-52">
                    <Field label="Fournisseur (export)" name="fournisseur" placeholder="Nom du fournisseur…" value={filters.fournisseur} onChange={update('fournisseur')} />
                </div>
                <Button variant="secondary" onClick={() => load()}>Actualiser</Button>
                <a href={exportUrl} className="inline-flex items-center justify-center rounded-md bg-ink px-4 py-2 text-sm font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                    Exporter Excel
                </a>
                <span className={`${pill} bg-amber-50 text-amber-800 ring-amber-600/20`}>
                    {lignes.length} article(s) · {filters.mois_couverture} mois de couverture
                </span>
                <div className="ml-auto">
                    <SearchInput value={search} onChange={setSearch} placeholder="Rechercher un article…" />
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Référence', 'Désignation', 'Présentation', 'Stock', 'Seuil', 'CMM / mois', 'À commander', 'Base', 'Statut'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan="10" className="px-4 py-6 text-center text-slate-500">
                                    {loading ? 'Chargement…' : lignes.length === 0 ? 'Aucun article à réapprovisionner.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((ligne) => (
                            <tr key={ligne.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-mono text-xs font-semibold">{ligne.reference}</td>
                                <td className="px-4 py-3 font-medium">{ligne.designation}</td>
                                <td className="px-4 py-3">{ligne.presentation}</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${ligne.rupture ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20'}`}>{ligne.stock} u.</span>
                                </td>
                                <td className="px-4 py-3">{ligne.seuil}</td>
                                <td className="px-4 py-3 font-mono text-xs">{ligne.cmm ? ligne.cmm.toFixed(2) : '—'}</td>
                                <td className="px-4 py-3 font-semibold text-sky-800">{ligne.manquant} u.</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${ligne.base === 'cmm' ? 'bg-sky-50 text-sky-700 ring-sky-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'}`}>{ligne.base === 'cmm' ? 'CMM' : 'Cible'}</span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${ligne.rupture ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-amber-50 text-amber-800 ring-amber-600/20'}`}>{ligne.rupture ? 'Rupture' : 'Sous le seuil'}</span>
                                </td>
                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                    <Link to={`/pharmacie/entrees?article=${ligne.id}&qte=${ligne.manquant}`} className="inline-flex rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-gold-light ring-1 ring-gold/60 hover:bg-black hover:text-white">
                                        Réapprovisionner
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
