import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import SearchInput from '../../components/SearchInput';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage } from '../../services/api';

const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

function defaultFilters() {
    const fin = new Date();
    const debut = new Date(fin);
    debut.setMonth(fin.getMonth() - 3);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    return { date_debut: fmt(debut), date_fin: fmt(fin), mois_couverture: 3 };
}

export default function Cmm() {
    const { can } = useAuth();
    const [filters, setFilters] = useState(defaultFilters);
    const [lignes, setLignes] = useState([]);
    const [resume, setResume] = useState({ nb_articles: 0, nb_jours: 0 });
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');

    function load(current = filters) {
        setLoading(true);
        setMessage('');
        api.get('/pharma/cmm', { params: current })
            .then(({ data }) => {
                setLignes(data.data);
                setResume(data.resume);
            })
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
        load(next);
    };

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Consommation Moyenne Mensuelle</h1>
            <Alert>{message}</Alert>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-slate-600">
                    CMM = quantité vendue sur la période × 30 / nombre de jours. La <strong>commande conseillée</strong> est déduite de cette consommation : <code className="rounded bg-slate-100 px-1">CMM × mois de couverture − stock</code>.
                </p>
                {can('pharma.reappro') && (
                    <Link to="/pharmacie/reappro" className="inline-flex rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Réapprovisionnement proposé
                    </Link>
                )}
            </div>

            <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <Field label="Période — du" name="date_debut" type="date" value={filters.date_debut} onChange={update('date_debut')} />
                <Field label="au" name="date_fin" type="date" value={filters.date_fin} onChange={update('date_fin')} />
                <div className="w-28">
                    <Field label="Mois de couverture" name="mois_couverture" type="number" min="1" max="24" step="1" value={filters.mois_couverture} onChange={update('mois_couverture')} />
                </div>
                <Button variant="secondary" onClick={() => load()}>Actualiser</Button>
                <span className={`${pill} bg-sky-50 text-sky-700 ring-sky-600/20`}>
                    {resume.nb_articles} article(s) consommé(s) · {resume.nb_jours} jour(s)
                </span>
                <div className="ml-auto">
                    <SearchInput value={search} onChange={setSearch} placeholder="Rechercher un article…" />
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Référence', 'Désignation', 'Présentation', 'Qté vendue', 'CMM / mois', 'Stock', 'Commande conseillée', 'Statut'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan="8" className="px-4 py-6 text-center text-slate-500">
                                    {loading ? 'Chargement…' : lignes.length === 0 ? 'Aucune consommation sur la période.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((ligne) => (
                            <tr key={ligne.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-mono text-xs font-semibold">{ligne.reference}</td>
                                <td className="px-4 py-3 font-medium">{ligne.designation}</td>
                                <td className="px-4 py-3">{ligne.presentation}</td>
                                <td className="px-4 py-3">{ligne.qte_vendue} u.</td>
                                <td className="px-4 py-3 font-mono text-xs font-semibold text-sky-800">{ligne.cmm.toFixed(2)} u./mois</td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${ligne.bientot_seuil ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'}`}>{ligne.stock} u.</span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} bg-sky-50 text-sky-700 ring-sky-600/20`}>{ligne.proposition} u.</span>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`${pill} ${ligne.bientot_seuil ? 'bg-amber-50 text-amber-800 ring-amber-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'}`}>{ligne.bientot_seuil ? 'Sous le seuil' : 'OK'}</span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
