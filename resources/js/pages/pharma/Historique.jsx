import { useEffect, useMemo, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import SearchInput from '../../components/SearchInput';
import StatTile from '../../components/StatTile';
import api, { errorMessage } from '../../services/api';
import { formatDateHeure, montant } from '../../services/pharma';

const today = () => new Date().toISOString().slice(0, 10);
const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset bg-sky-50 text-sky-700 ring-sky-600/20';
const natureLabels = { Consultation: 'Consultation', Service: 'Service', Article: 'Médicament', Analyse: 'Analyse', Kit: 'Kit' };

export default function Historique() {
    const [filters, setFilters] = useState({ date_debut: today(), date_fin: today() });
    const [search, setSearch] = useState('');
    const [ventes, setVentes] = useState([]);
    const [stats, setStats] = useState([]);
    const [totaux, setTotaux] = useState({ nb_ventes: 0 });
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [detail, setDetail] = useState(null);

    function load(current = filters) {
        setLoading(true);
        setMessage('');
        api.get('/pharma/ventes', { params: current })
            .then(({ data }) => {
                setVentes(data.data);
                setStats(data.stats);
                setTotaux(data.totaux);
            })
            .catch((error) => setMessage(errorMessage(error)))
            .finally(() => setLoading(false));
    }

    useEffect(() => load(), []);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q ? ventes.filter((v) => [v.reference, v.client, v.caissier].some((value) => String(value ?? '').toLowerCase().includes(q))) : ventes;
    }, [ventes, search]);

    async function showDetail(vente) {
        try {
            setDetail((await api.get(`/pharma/ventes/${vente.id}`)).data);
        } catch (error) {
            setMessage(errorMessage(error));
        }
    }

    function aujourdhui() {
        const next = { date_debut: today(), date_fin: today() };
        setFilters(next);
        load(next);
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Historique des ventes</h1>
            <Alert>{message}</Alert>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    load();
                }}
                className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4"
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Field label="Du *" name="date_debut" type="date" required value={filters.date_debut} onChange={(e) => setFilters({ ...filters, date_debut: e.target.value })} />
                    <Field label="Au *" name="date_fin" type="date" required value={filters.date_fin} onChange={(e) => setFilters({ ...filters, date_fin: e.target.value })} />
                    <div className="flex items-end gap-2">
                        <Button type="submit" disabled={loading}>Filtrer</Button>
                        <Button variant="secondary" onClick={aujourdhui}>Aujourd'hui</Button>
                    </div>
                </div>
                <SearchInput value={search} onChange={setSearch} placeholder="Référence, client ou caissier…" />
            </form>

            {stats.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {stats.map((s) => (
                        <StatTile key={s.nature} label={natureLabels[s.nature] ?? s.nature} value={`${s.somme_qte} u.`} hint={`${s.nb_lignes} ligne(s) · ${s.pct_lignes} %`} />
                    ))}
                </div>
            )}

            {totaux.nb_ventes > 0 && (
                <p className="text-sm text-slate-600">
                    <strong>{totaux.nb_ventes}</strong> vente(s) — Total brut : <strong>{montant(totaux.montant_brut, 2)}</strong> · Encaissé : <strong>{montant(totaux.montant_paye, 2)}</strong> · Monnaie rendue : {montant(totaux.monnaie, 2)}
                </p>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {['Référence', 'Client', 'Caissier', 'Lignes', 'Brut', 'Payé', 'Monnaie', 'Date'].map((label) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan="9" className="px-4 py-8 text-center text-slate-500">
                                    {loading ? 'Chargement…' : ventes.length === 0 ? 'Aucune vente sur cette période.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((vente) => (
                            <tr key={vente.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 font-mono text-xs font-semibold">{vente.reference}</td>
                                <td className="px-4 py-3">{vente.client}</td>
                                <td className="px-4 py-3">{vente.caissier}</td>
                                <td className="px-4 py-3">{vente.nb_lignes}</td>
                                <td className="px-4 py-3">{montant(vente.montant_brut, 2)}</td>
                                <td className="px-4 py-3 font-semibold">{montant(vente.montant_paye, 2)}</td>
                                <td className="px-4 py-3">{montant(vente.monnaie, 2)}</td>
                                <td className="px-4 py-3 text-xs text-slate-500">{formatDateHeure(vente.created_at)}</td>
                                <td className="space-x-3 px-4 py-3 text-right text-xs whitespace-nowrap">
                                    <button type="button" onClick={() => showDetail(vente)} className="font-medium text-sky-700 hover:underline">Détail</button>
                                    <a href={`/api/v1/pharma/ventes/${vente.id}/recu`} target="_blank" rel="noopener" className="font-medium text-sky-700 hover:underline">Reçu</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal open={detail !== null} size="lg" title="Détail de la vente" subtitle="Lignes, consultations et montants encaissés." onClose={() => setDetail(null)}>
                {detail && (
                    <div className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2 rounded-md bg-sky-50 p-4 text-sm sm:grid-cols-3">
                            <p><span className="text-slate-500">Référence :</span> <strong>{detail.vente.reference}</strong></p>
                            <p><span className="text-slate-500">Client :</span> <strong>{detail.vente.client}</strong></p>
                            <p><span className="text-slate-500">Caissier :</span> <strong>{detail.vente.caissier}</strong></p>
                            <p><span className="text-slate-500">Date :</span> {formatDateHeure(detail.vente.created_at)}</p>
                            <p><span className="text-slate-500">Mode :</span> {detail.vente.mode_paiement}</p>
                            <p><span className="text-slate-500">Total brut :</span> <strong>{montant(detail.vente.montant_brut, 2)}</strong></p>
                            <p><span className="text-slate-500">Payé :</span> <strong>{montant(detail.vente.montant_paye, 2)}</strong></p>
                            <p><span className="text-slate-500">Monnaie :</span> <strong>{montant(detail.vente.monnaie, 2)}</strong></p>
                        </div>

                        {detail.consultations.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">Consultations</h3>
                                <ul className="space-y-1 text-sm">
                                    {detail.consultations.map((c, index) => (
                                        <li key={index} className="rounded-md border border-slate-200 px-3 py-2">
                                            {c.patient} ({c.sexe}, {c.age}) — {c.type_docteur} avec {c.docteur}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="text-left text-slate-600">
                                    <tr>
                                        {['Type', 'Libellé', 'PU Vente', 'Qté', 'Brut', 'Payé'].map((label) => <th key={label} className="py-2 pr-4 font-medium">{label}</th>)}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {detail.lignes.map((ligne, index) => (
                                        <tr key={index}>
                                            <td className="py-2 pr-4"><span className={pill}>{natureLabels[ligne.type] ?? ligne.type}</span></td>
                                            <td className="py-2 pr-4">{ligne.libelle}</td>
                                            <td className="py-2 pr-4">{montant(ligne.p_u_brut, 2)}</td>
                                            <td className="py-2 pr-4">{ligne.qte}</td>
                                            <td className="py-2 pr-4">{montant(ligne.montant_brut, 2)}</td>
                                            <td className="py-2 pr-4 font-semibold">{montant(ligne.paye, 2)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
