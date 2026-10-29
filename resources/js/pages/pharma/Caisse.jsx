import { useCallback, useEffect, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import { DeleteButton, RemoveButton } from '../../components/IconButton';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import PriceField from '../../components/PriceField';
import Tabs from '../../components/Tabs';
import api, { errorMessage } from '../../services/api';
import { formatDateHeure, montant } from '../../services/pharma';
import FormulairesCaisse from './caisse/FormulairesCaisse';

const ariary = (valeur) => `${montant(valeur ?? 0, 2)} Ar`;

const onglets = [
    { key: 'consultation', label: 'Consultation' },
    { key: 'service', label: 'Services' },
    { key: 'article', label: 'Médicaments' },
    { key: 'analyse', label: 'Analyses' },
    { key: 'kit', label: 'Kits' },
];

const messagesCodes = { insuffisant: 'Stock insuffisant.', deja_ajoutee: 'Élément déjà présent dans le panier.' };
const arrondirCentaine = (valeur) => {
    if (valeur <= 0) {
        return 0;
    }
    const centaines = Math.floor(valeur / 100);

    return valeur - centaines * 100 > 50 ? (centaines + 1) * 100 : centaines * 100;
};

export default function Caisse() {
    const [chargement, setChargement] = useState(true);
    const [busy, setBusy] = useState(false);
    const [session, setSession] = useState(null);
    const [onglet, setOnglet] = useState('consultation');
    const [referentiel, setReferentiel] = useState({ services: [], consultation: null, types_docteur: [], analyses: [], kits: [] });
    const [articles, setArticles] = useState([]);
    const [panier, setPanier] = useState({ lignes: [], total: 0 });
    const [client, setClient] = useState('');
    const [montantPaye, setMontantPaye] = useState('');
    const [message, setMessage] = useState({ type: 'error', text: '', recu: null });
    const [ouverture, setOuverture] = useState(null);
    const [fermeture, setFermeture] = useState(null);
    const [confirmVider, setConfirmVider] = useState(false);
    const [ordres, setOrdres] = useState([]);

    const echec = (error) => {
        const code = error.response?.data?.code;
        const premiereErreur = Object.values(error.response?.data?.errors ?? {})[0]?.[0];
        setMessage({ type: 'error', text: messagesCodes[code] ?? premiereErreur ?? errorMessage(error), recu: null });
    };

    const chargerSession = useCallback(async () => {
        const { data } = await api.get('/pharma/caisse/session');
        setSession(data.data);
    }, []);

    const chargerPanier = useCallback(async () => {
        const { data } = await api.get('/pharma/caisse/panier');
        setPanier({ lignes: data.data, total: data.total });
        // Préremplissage du client avec le patient de la consultation du panier
        setClient((current) => current || data.patient || '');
    }, []);

    // Soins envoyés en caisse par les dentistes, en attente de paiement.
    const chargerOrdres = useCallback(async () => {
        setOrdres((await api.get('/pharma/caisse/ordres-soins')).data.data);
    }, []);

    const chargerArticles = useCallback(async () => {
        setArticles((await api.get('/pharma/caisse/articles')).data.data);
    }, []);

    useEffect(() => {
        (async () => {
            try {
                const [services, analyses, kits] = await Promise.all([api.get('/pharma/services/options'), api.get('/pharma/analyses/options'), api.get('/pharma/kits/options')]);
                setReferentiel({ services: services.data.services, consultation: services.data.consultation, types_docteur: services.data.types_docteur, analyses: analyses.data.data.map((a) => ({ id: a.id, nom: a.nom, prix: a.prix })), kits: kits.data.data });
                await Promise.all([chargerSession(), chargerArticles(), chargerPanier(), chargerOrdres()]);
            } catch (error) {
                echec(error);
            } finally {
                setChargement(false);
            }
        })();
    }, []);

    // Un patient envoyé par le dentiste apparaît sans recharger la page.
    useEffect(() => {
        if (!session) {
            return undefined;
        }
        const timer = setInterval(() => chargerOrdres().catch(() => {}), 30000);

        return () => clearInterval(timer);
    }, [session, chargerOrdres]);

    async function ajouterSoins(ordre) {
        try {
            const { data } = await api.post(`/pharma/caisse/ordres-soins/${ordre.id}/panier`);
            setMessage({ type: 'success', text: `${data.message} (${data.patient})`, recu: null });
            await Promise.all([chargerPanier(), chargerOrdres()]);
        } catch (error) {
            echec(error);
            await chargerOrdres().catch(() => {});
        }
    }

    async function ajouter(pageType, payload) {
        try {
            const { data } = await api.post('/pharma/caisse/panier', { page_type: pageType, ...payload });
            setMessage({ type: 'success', text: data.message, recu: null });
            await chargerPanier();
            if (pageType === 'article') {
                await chargerArticles();
            }

            return true;
        } catch (error) {
            echec(error);

            return false;
        }
    }

    async function retirer(id) {
        try {
            await api.delete(id ? `/pharma/caisse/panier/${id}` : '/pharma/caisse/panier');
            await Promise.all([chargerPanier(), chargerArticles(), chargerOrdres()]);
        } catch (error) {
            echec(error);
        }
    }

    async function ouvrirCaisse(event) {
        event.preventDefault();
        setBusy(true);
        try {
            const { data } = await api.post('/pharma/caisse/ouvrir', { fond_de_caisse: ouverture.fond || 0 });
            setMessage({ type: 'success', text: data.message, recu: null });
            setOuverture(null);
            await chargerSession();
        } catch (error) {
            echec(error);
        } finally {
            setBusy(false);
        }
    }

    async function fermerCaisse(event) {
        event.preventDefault();
        if (!(Number(fermeture.reel) > 0)) {
            setMessage({ type: 'error', text: 'Saisissez le montant réel compté.', recu: null });

            return;
        }
        setBusy(true);
        try {
            const { data } = await api.post('/pharma/caisse/fermer', { montant_reel: fermeture.reel, observations: fermeture.observations || null });
            setMessage({ type: 'success', text: data.message, recu: null });
            setFermeture(null);
            setSession(null);
            setPanier({ lignes: [], total: 0 });
            setClient('');
            setMontantPaye('');
            await chargerArticles();
        } catch (error) {
            echec(error);
        } finally {
            setBusy(false);
        }
    }

    async function validerVente() {
        if (!(Number(montantPaye) > 0)) {
            setMessage({ type: 'error', text: 'Saisissez le montant payé.', recu: null });

            return;
        }
        setBusy(true);
        try {
            const { data } = await api.post('/pharma/caisse/ventes', { client, montant_paye: montantPaye });
            const vente = data.data;
            const rendue = montant(vente.monnaie_arrondie, 0);
            setMessage({
                type: 'success',
                text: vente.monnaie !== vente.monnaie_arrondie ? `Vente validée — monnaie : ${rendue} Ar (calculée : ${montant(vente.monnaie, 2)} Ar)` : `Vente validée — monnaie : ${rendue} Ar`,
                recu: vente.recu_url,
            });
            window.open(vente.recu_url, '_blank');
            setClient('');
            setMontantPaye('');
            await Promise.all([chargerPanier(), chargerSession(), chargerOrdres()]);
        } catch (error) {
            echec(error);
        } finally {
            setBusy(false);
        }
    }

    const monnaieCalculee = Math.max(Number(montantPaye || 0) - panier.total, 0);
    const monnaieArrondie = arrondirCentaine(monnaieCalculee);

    if (chargement) {
        return <p className="text-sm text-slate-500">Chargement de la caisse…</p>;
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Caisse</h1>

            <Alert type={message.type}>
                {message.text}
                {message.recu && (
                    <>
                        {' '}
                        <a href={message.recu} target="_blank" rel="noopener" className="font-medium underline">
                            Ouvrir le reçu
                        </a>
                    </>
                )}
            </Alert>

            {session ? (
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset">● Caisse ouverte</span>
                    <span className="text-sm text-slate-600">Ouverte le <strong>{formatDateHeure(session.ouverture_at)}</strong></span>
                    <span className="text-sm text-slate-600">Fond de caisse : <strong>{ariary(session.fond_de_caisse)}</strong></span>
                    <span className="text-sm text-slate-600">Ventes : <strong>{session.nb_ventes}</strong></span>
                    <span className="text-sm text-slate-600">Encaissé : <strong>{ariary(session.total_encaisse)}</strong></span>
                    <Button variant="danger" className="ml-auto !px-3 !py-1.5 !text-xs" onClick={() => setFermeture({ reel: '', observations: '' })}>
                        Fermer la caisse
                    </Button>
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white py-16 text-center">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 ring-inset">● Caisse fermée</span>
                    <h2 className="text-lg font-semibold">Aucune session en cours</h2>
                    <p className="max-w-md text-sm text-slate-500">Ouvrez la caisse pour commencer à encaisser. Le fond de caisse de départ sera enregistré et comparé à l'encaissement lors de la clôture.</p>
                    <Button onClick={() => setOuverture({ fond: '' })}>Ouvrir la caisse</Button>
                </div>
            )}

            {session && (
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <div className="flex flex-col gap-4 xl:col-span-2">
                        {ordres.length > 0 && (
                            <div className="rounded-lg border border-sky-200 bg-sky-50 p-4">
                                <h2 className="text-sm font-semibold text-sky-900">Patients envoyés par les docteurs ({ordres.length})</h2>
                                <ul className="mt-2 divide-y divide-sky-200">
                                    {ordres.map((ordre) => (
                                        <li key={ordre.id} className="flex flex-wrap items-center justify-between gap-3 py-2">
                                            <div className="min-w-0 text-sm text-slate-800">
                                                <p className="font-medium">
                                                    {ordre.patient} <span className="font-normal text-slate-500">— Dr {ordre.docteur}</span>
                                                </p>
                                                <p className="text-xs text-slate-600">{ordre.lignes.map((ligne) => `${ligne.qte > 1 ? `${ligne.qte} × ` : ''}${ligne.nom}`).join(', ')}</p>
                                                {ordre.note && <p className="text-xs text-slate-500 italic">{ordre.note}</p>}
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <strong className="text-sm">{ariary(ordre.total)}</strong>
                                                <Button className="!px-3 !py-1.5 text-xs" disabled={ordre.dans_mon_panier || ordre.dans_un_autre_panier} onClick={() => ajouterSoins(ordre)}>
                                                    {ordre.dans_mon_panier ? 'Dans le panier' : ordre.dans_un_autre_panier ? 'Pris par une autre caisse' : 'Ajouter au panier'}
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <Tabs tabs={onglets} active={onglet} onChange={setOnglet} />
                        <FormulairesCaisse onglet={onglet} referentiel={referentiel} articles={articles} panier={panier.lignes} ajouter={ajouter} />
                    </div>

                    <div className="flex flex-col gap-4">
                        <div className="rounded-lg border border-slate-200 bg-white">
                            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                <h2 className="text-sm font-semibold">Panier</h2>
                                {panier.lignes.length > 0 && (
                                    <DeleteButton onClick={() => setConfirmVider(true)} label="Vider le panier" />
                                )}
                            </div>
                            <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                                {panier.lignes.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Panier vide.</li>}
                                {panier.lignes.map((ligne) => (
                                    <li key={ligne.id} className="px-4 py-3 hover:bg-slate-50">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{ligne.designation}</p>
                                                <p className="mt-0.5 text-xs text-slate-500">{ligne.type} · Qté {ligne.qte} · PU {montant(ligne.p_u_brut, 2)}</p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold">{montant(ligne.montant_brut, 2)}</p>
                                                <RemoveButton onClick={() => retirer(ligne.id)} label={`Retirer ${ligne.designation} du panier`} className="-mr-2" />
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <div className="space-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                <div className="flex justify-between">
                                    <span>Total</span>
                                    <strong>{ariary(panier.total)}</strong>
                                </div>
                                <div className="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-sky-800">
                                    <span>À payer</span>
                                    <span>{ariary(panier.total)}</span>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4">
                            <h2 className="text-sm font-semibold">Encaissement</h2>
                            <Field label="Client" name="client" maxLength={100} placeholder="Nom du client / patient" value={client} onChange={(e) => setClient(e.target.value)} />
                            <PriceField label="Montant payé (Ar)" name="montant_paye" value={montantPaye} onChange={setMontantPaye} />
                            <div className="rounded-md bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-800">
                                {monnaieCalculee > 0 && monnaieArrondie !== monnaieCalculee && (
                                    <div className="flex justify-between">
                                        <span>Monnaie calculée</span>
                                        <span>{ariary(monnaieCalculee)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span>Monnaie à rendre</span>
                                    <span>{ariary(monnaieArrondie)}</span>
                                </div>
                            </div>
                            <Button className="w-full" disabled={busy || panier.lignes.length === 0} onClick={validerVente}>
                                Valider la vente
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <Modal
                open={ouverture !== null}
                title="Ouvrir la caisse"
                subtitle="Enregistrez le fond de caisse de départ."
                onClose={() => setOuverture(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setOuverture(null)}>Annuler</Button>
                        <Button type="submit" form="form-ouverture" disabled={busy}>{busy ? '…' : 'Ouvrir'}</Button>
                    </>
                }
            >
                {ouverture && (
                    <form id="form-ouverture" onSubmit={ouvrirCaisse} className="flex flex-col gap-3">
                        <PriceField label="Fond de caisse (Ar) *" name="fond_de_caisse" value={ouverture.fond} onChange={(fond) => setOuverture({ fond })} />
                        <Alert type="info">Le fonds servira de base au calcul de l'écart à la clôture.</Alert>
                    </form>
                )}
            </Modal>

            <Modal
                open={fermeture !== null}
                title="Fermer la caisse"
                subtitle="Saisissez le montant réel compté dans le tiroir-caisse."
                onClose={() => setFermeture(null)}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setFermeture(null)}>Annuler</Button>
                        <Button variant="danger" type="submit" form="form-fermeture" disabled={busy}>{busy ? '…' : 'Fermer & solder'}</Button>
                    </>
                }
            >
                {fermeture && session && (
                    <form id="form-fermeture" onSubmit={fermerCaisse} className="flex flex-col gap-3">
                        <div className="grid grid-cols-2 gap-3 rounded-md bg-slate-50 p-3 text-sm">
                            <div className="flex justify-between gap-2"><span className="text-slate-500">Fond de caisse</span><strong>{ariary(session.fond_de_caisse)}</strong></div>
                            <div className="flex justify-between gap-2"><span className="text-slate-500">Total encaissé</span><strong>{ariary(session.total_encaisse)}</strong></div>
                            <div className="flex justify-between gap-2"><span className="text-slate-500">Total ventes</span><strong>{ariary(session.total_ventes)}</strong></div>
                            <div className="flex justify-between gap-2"><span className="text-slate-500">Montant théorique</span><strong>{ariary(session.theorique)}</strong></div>
                        </div>
                        <PriceField label="Montant réel compté (Ar) *" name="montant_reel" value={fermeture.reel} onChange={(reel) => setFermeture({ ...fermeture, reel })} />
                        <Field label="Observations (optionnel)" name="observations" maxLength={255} placeholder="Écart constaté, remarques…" value={fermeture.observations} onChange={(e) => setFermeture({ ...fermeture, observations: e.target.value })} />
                    </form>
                )}
            </Modal>

            <ConfirmDialog open={confirmVider} message="Vider tout le panier ? Le stock réservé sera restitué." confirmLabel="Vider" onConfirm={() => { setConfirmVider(false); retirer(0); }} onCancel={() => setConfirmVider(false)} />
        </div>
    );
}
