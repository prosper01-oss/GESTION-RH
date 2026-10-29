import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import ChartCanvas from '../../components/Chart';
import Field from '../../components/Field';
import StatTile from '../../components/StatTile';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage } from '../../services/api';
import { formatDate, formatDateHeure, montant, motifSortieLabels } from '../../services/pharma';

const ariary = (valeur) => `${montant(valeur ?? 0, 2)} Ar`;
const pill = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

const palette = {
    clair: ['#b98c2c', '#57534e', '#0f766e', '#9f1239', '#6d28d9'],
    sombre: ['#dcbd68', '#a8a29e', '#2dd4bf', '#fb7185', '#a78bfa'],
};

function Section({ titre, action, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 className="text-sm font-semibold">{titre}</h2>
                {action}
            </div>
            <div className="overflow-x-auto">{children}</div>
        </div>
    );
}

export default function PharmaDashboard() {
    const { can } = useAuth();
    const [filters, setFilters] = useState({ date_debut: '', date_fin: '' });
    const [data, setData] = useState(null);
    const [message, setMessage] = useState('');

    function load(params = filters) {
        setMessage('');
        api.get('/pharma/dashboard', { params: Object.fromEntries(Object.entries(params).filter(([, value]) => value)) })
            .then((response) => {
                setData(response.data);
                setFilters({ date_debut: response.data.periode.debut, date_fin: response.data.periode.fin });
            })
            .catch((error) => setMessage(errorMessage(error)));
    }

    useEffect(() => load(), []);

    function reset() {
        load({ date_debut: '', date_fin: '' });
    }

    const periode = data ? `${formatDate(data.periode.debut)} → ${formatDate(data.periode.fin)}` : '';
    const natures = data ? Object.entries(data.repartition) : [];

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-xl font-semibold">Tableau de bord — Pharmacie</h1>
            <Alert>{message}</Alert>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    load();
                }}
                className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3"
            >
                <Field label="Période — du" name="date_debut" type="date" value={filters.date_debut} onChange={(e) => setFilters({ ...filters, date_debut: e.target.value })} />
                <Field label="au" name="date_fin" type="date" value={filters.date_fin} onChange={(e) => setFilters({ ...filters, date_fin: e.target.value })} />
                <Button type="submit">Filtrer</Button>
                {data?.periode.personnalisee && <Button variant="secondary" onClick={reset}>Réinitialiser</Button>}
                <p className="ml-auto text-xs font-semibold text-slate-500">{periode}</p>
            </form>

            {data && (
                <>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="flex items-center gap-4 rounded-lg border border-slate-200 bg-white p-5">
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-amber-50 text-xl text-amber-600">⚠</span>
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold">Stock critique</p>
                                <p className="text-sm text-slate-500">{data.nb_ruptures} article(s) sous le seuil minimum — réapprovisionnement requis.</p>
                            </div>
                            {can('pharma.articles') && <Link to="/pharmacie/articles" className="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Voir</Link>}
                        </div>
                        <div className="flex items-center gap-4 rounded-lg border border-slate-200 bg-white p-5">
                            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-red-50 text-xl text-red-600">◷</span>
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold">Expiration proche</p>
                                <p className="text-sm text-slate-500">{data.peremption30j.length} lot(s) périment dans les 30 prochains jours.</p>
                            </div>
                            {can('pharma.entrees') && <Link to="/pharmacie/entrees" className="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Consulter</Link>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile label="CA de la période" value={ariary(data.ca)} />
                        <StatTile label="Ventes de la période" value={data.nb_ventes} />
                        <StatTile label="Panier moyen" value={ariary(data.panier_moyen)} hint="Par vente sur la période" />
                        <StatTile label="Valeur du stock" value={ariary(data.valeur_stock)} hint="Coût Moyen Pondéré d'Achat" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <StatTile label="CMUP global" value={ariary(data.cmup_global)} hint="Coût moyen d'achat par unité" />
                        <StatTile
                            label="Pertes de la période"
                            value={ariary(data.pertes)}
                            hint={Object.entries(data.pertes_par_motif).map(([motif, total]) => `${motifSortieLabels[motif] ?? motif} : ${ariary(total)}`).join(' · ') || undefined}
                        />
                        <StatTile label="Ruptures / seuil" value={data.nb_ruptures} hint="Articles à réapprovisionner" />
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div className="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2">
                            <h2 className="text-sm font-semibold">Chiffre d'affaires</h2>
                            <p className="mb-3 text-xs text-slate-500">CA quotidien — {periode}</p>
                            <ChartCanvas
                                deps={[data]}
                                build={(dark) => ({
                                    type: 'line',
                                    data: {
                                        labels: data.graphe.labels,
                                        datasets: [{ label: 'CA (Ar)', data: data.graphe.data, borderColor: dark ? '#dcbd68' : '#b98c2c', backgroundColor: dark ? 'rgba(220,189,104,0.15)' : 'rgba(185,140,44,0.15)', fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2.5 }],
                                    },
                                    options: {
                                        maintainAspectRatio: false,
                                        plugins: { legend: { display: false } },
                                        scales: {
                                            y: { beginAtZero: true, grid: { color: dark ? 'rgba(168,162,158,0.15)' : 'rgba(28,25,23,0.08)' }, ticks: { color: dark ? '#a8a29e' : '#78716c' } },
                                            x: { grid: { display: false }, ticks: { color: dark ? '#a8a29e' : '#78716c' } },
                                        },
                                    },
                                })}
                            />
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-white p-5">
                            <h2 className="text-sm font-semibold">Répartition du CA</h2>
                            <p className="mb-3 text-xs text-slate-500">Par nature — {periode}</p>
                            <ChartCanvas
                                deps={[data]}
                                build={(dark) => ({
                                    type: 'doughnut',
                                    data: { labels: natures.map(([nature]) => nature), datasets: [{ data: natures.map(([, total]) => total), backgroundColor: dark ? palette.sombre : palette.clair, borderWidth: 0, hoverOffset: 6 }] },
                                    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: dark ? '#a8a29e' : '#57534e', usePointStyle: true, pointStyle: 'circle' } } } },
                                })}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Section titre={`Top 5 médicaments (${periode})`}>
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600"><tr><th className="px-4 py-3 font-medium">Article</th><th className="px-4 py-3 font-medium">Qté vendue</th><th className="px-4 py-3 font-medium">Montant</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.top_articles.length === 0 && <tr><td colSpan="3" className="px-4 py-6 text-center text-slate-500">Aucune vente sur la période.</td></tr>}
                                    {data.top_articles.map((top) => (
                                        <tr key={top.designation}>
                                            <td className="px-4 py-3 font-medium">{top.designation}</td>
                                            <td className="px-4 py-3"><span className={`${pill} bg-sky-50 text-sky-700 ring-sky-600/20`}>{top.qte}</span></td>
                                            <td className="px-4 py-3">{ariary(top.montant)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>

                        <Section titre="Dernières ventes" action={can('pharma.historique') && <Link to="/pharmacie/historique" className="text-xs font-semibold text-sky-700 hover:underline">Voir tout →</Link>}>
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600"><tr><th className="px-4 py-3 font-medium">Référence</th><th className="px-4 py-3 font-medium">Client</th><th className="px-4 py-3 font-medium">Montant</th><th className="px-4 py-3 font-medium">Date</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.dernieres_ventes.length === 0 && <tr><td colSpan="4" className="px-4 py-6 text-center text-slate-500">Aucune vente enregistrée.</td></tr>}
                                    {data.dernieres_ventes.map((vente) => (
                                        <tr key={vente.id}>
                                            <td className="px-4 py-3 font-mono text-xs">{vente.reference}</td>
                                            <td className="px-4 py-3">{vente.client}</td>
                                            <td className="px-4 py-3">{ariary(vente.montant_brut)}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{formatDateHeure(vente.created_at)} · {vente.caissier}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Section titre="Articles en rupture / sous seuil">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600"><tr><th className="px-4 py-3 font-medium">Article</th><th className="px-4 py-3 font-medium">Stock</th><th className="px-4 py-3 font-medium">Seuil</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.ruptures.length === 0 && <tr><td colSpan="3" className="px-4 py-6 text-center text-slate-500">Aucune alerte de stock.</td></tr>}
                                    {data.ruptures.map((article) => (
                                        <tr key={article.id}>
                                            <td className="px-4 py-3 font-medium">{article.designation}</td>
                                            <td className="px-4 py-3"><span className={`${pill} bg-red-50 text-red-700 ring-red-600/20`}>{article.stock}</span></td>
                                            <td className="px-4 py-3">{article.seuil}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>

                        <Section titre="Lots périmant dans 30 jours">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600"><tr><th className="px-4 py-3 font-medium">Article</th><th className="px-4 py-3 font-medium">Lot</th><th className="px-4 py-3 font-medium">Restant</th><th className="px-4 py-3 font-medium">Péremption</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.peremption30j.length === 0 && <tr><td colSpan="4" className="px-4 py-6 text-center text-slate-500">Aucune péremption proche.</td></tr>}
                                    {data.peremption30j.map((lot) => (
                                        <tr key={lot.id}>
                                            <td className="px-4 py-3 font-medium">{lot.article}</td>
                                            <td className="px-4 py-3 text-xs text-slate-500">{lot.lot}</td>
                                            <td className="px-4 py-3">{lot.restant}</td>
                                            <td className="px-4 py-3"><span className={`${pill} bg-amber-50 text-amber-700 ring-amber-600/20`}>{formatDate(lot.date_peremption)}</span></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    </div>
                </>
            )}
        </div>
    );
}
