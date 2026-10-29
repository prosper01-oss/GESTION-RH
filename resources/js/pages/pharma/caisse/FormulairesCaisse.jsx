import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../../components/Alert';
import ArticleSelect from '../../../components/ArticleSelect';
import Button from '../../../components/Button';
import Field from '../../../components/Field';
import { useAuth } from '../../../hooks/useAuth';
import api from '../../../services/api';
import { montant } from '../../../services/pharma';

const ariary = (valeur) => `${montant(valeur, 2)} Ar`;

const emptyConsultation = { nom_patient: '', sex_patient: 1, age_patient: '', unite_age: 1, type_docteur: 'generaliste', docteur: '' };

/**
 * Formulaires d'ajout au panier : un par onglet de la caisse.
 * `ajouter(pageType, payload)` renvoie true si la ligne a bien été ajoutée.
 */
export default function FormulairesCaisse({ onglet, referentiel, articles, panier, ajouter }) {
    const consultationPresente = panier.some((ligne) => ligne.type === 'Consultation');

    if (onglet === 'consultation') {
        return <FormulaireConsultation referentiel={referentiel} deja={consultationPresente} ajouter={ajouter} />;
    }
    if (onglet === 'service') {
        return <FormulaireService referentiel={referentiel} ajouter={ajouter} />;
    }
    if (onglet === 'article') {
        return <FormulaireArticle articles={articles} ajouter={ajouter} />;
    }
    if (onglet === 'analyse') {
        return <FormulaireAnalyses referentiel={referentiel} ajouter={ajouter} />;
    }

    return <FormulaireKit referentiel={referentiel} ajouter={ajouter} />;
}

/**
 * Avertit qu'un élément nécessaire à la vente n'est pas configuré et propose d'aller le configurer
 * (le lien n'apparaît que pour les comptes qui ont le droit de le faire).
 */
function ConfigurationManquante({ titre, aide, to, lien, permission }) {
    const { can } = useAuth();

    return (
        <Alert type="warning">
            <p className="font-medium">{titre}</p>
            <p className="mt-0.5">{aide}</p>
            {can(permission) ? (
                <Link to={to} className="mt-1 inline-block font-medium underline">
                    {lien}
                </Link>
            ) : (
                <p className="mt-1 text-xs">Demandez à un responsable de la Pharmacie de le configurer.</p>
            )}
        </Alert>
    );
}

const carte = 'flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4';

function FormulaireConsultation({ referentiel, deja, ajouter }) {
    const [form, setForm] = useState(emptyConsultation);
    const [docteurs, setDocteurs] = useState([]);

    // Les docteurs proposés sont les agents actifs du Personnel dont la fonction correspond au type choisi :
    // « Généraliste » affiche les docteurs, « Dentiste » affiche les dentistes.
    useEffect(() => {
        api.get('/pharma/docteurs', { params: { type_docteur: form.type_docteur } }).then(({ data }) => {
            setDocteurs(data.data);
            setForm((current) => (data.data.some((d) => d.id === Number(current.docteur)) ? current : { ...current, docteur: data.data[0]?.id ?? '' }));
        });
    }, [form.type_docteur]);

    const set = (name, number = false) => (event) => setForm({ ...form, [name]: number ? (event.target.value === '' ? '' : Number(event.target.value)) : event.target.value });

    async function submit(event) {
        event.preventDefault();
        if (await ajouter('consultation', form)) {
            setForm({ ...emptyConsultation, type_docteur: form.type_docteur, docteur: form.docteur });
        }
    }

    return (
        <form onSubmit={submit} className={carte}>
            <Field label="Nom du patient *" name="nom_patient" required maxLength={100} value={form.nom_patient} onChange={set('nom_patient')} />
            <div className="grid grid-cols-2 gap-3">
                <Field label="Sexe" name="sex_patient" as="select" value={form.sex_patient} onChange={set('sex_patient', true)}>
                    <option value={1}>Homme</option>
                    <option value={0}>Femme</option>
                </Field>
                <div className="grid grid-cols-2 gap-2">
                    <Field label="Âge" name="age_patient" type="number" min="0" max="150" required value={form.age_patient} onChange={set('age_patient', true)} />
                    <Field label="Unité" name="unite_age" as="select" value={form.unite_age} onChange={set('unite_age', true)}>
                        <option value={1}>Ans</option>
                        <option value={0}>Mois</option>
                    </Field>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Type de consultation" name="type_docteur" as="select" required value={form.type_docteur} onChange={set('type_docteur')}>
                    {referentiel.types_docteur.map((type) => (
                        <option key={type.valeur} value={type.valeur}>{type.libelle}</option>
                    ))}
                </Field>
                <Field label="Docteur" name="docteur" as="select" required value={form.docteur} onChange={set('docteur', true)}>
                    {docteurs.map((docteur) => (
                        <option key={docteur.id} value={docteur.id}>Dr {docteur.nom}</option>
                    ))}
                </Field>
            </div>
            {!referentiel.consultation && (
                <ConfigurationManquante
                    titre="Aucun service de consultation configuré."
                    aide="Créez un service et cochez « Service de consultation » : son prix sera celui de la consultation."
                    to="/pharmacie/gestion"
                    lien="Ouvrir la gestion de la Pharmacie (onglet Services)"
                    permission="pharma.gestion"
                />
            )}
            {docteurs.length === 0 && (
                <ConfigurationManquante
                    titre={`Aucun ${form.type_docteur === 'dentiste' ? 'dentiste' : 'docteur'} disponible.`}
                    aide="Renseignez la fonction « Docteur », « Médecin » ou « Dentiste » sur une fiche du Personnel actif."
                    to="/personnel"
                    lien="Ouvrir le Personnel"
                    permission="employees.manage"
                />
            )}
            <p className="text-sm text-slate-600">
                Prix de la consultation : <strong>{referentiel.consultation ? ariary(referentiel.consultation.prix) : 'non configuré'}</strong>
            </p>
            <div>
                <Button type="submit" disabled={deja || !referentiel.consultation || !form.docteur}>Ajouter au panier</Button>
            </div>
        </form>
    );
}

function FormulaireService({ referentiel, ajouter }) {
    const [form, setForm] = useState({ service_id: '', qte: 1 });

    async function submit(event) {
        event.preventDefault();
        if (await ajouter('service', form)) {
            setForm({ service_id: '', qte: 1 });
        }
    }

    return (
        <form onSubmit={submit} className={carte}>
            {referentiel.services.length === 0 && (
                <ConfigurationManquante
                    titre="Aucun service configuré."
                    aide="Ajoutez les services d'imagerie ou de soins (échographie, radiographie…) avec leur prix."
                    to="/pharmacie/gestion"
                    lien="Ouvrir la gestion de la Pharmacie (onglet Services)"
                    permission="pharma.gestion"
                />
            )}
            <div className="grid grid-cols-3 items-end gap-3">
                <div className="col-span-2">
                    <Field label="Service" name="service_id" as="select" required value={form.service_id} onChange={(e) => setForm({ ...form, service_id: Number(e.target.value) })}>
                        <option value="">— Choisir —</option>
                        {/* Les services sont regroupés par catégorie (Imagerie, Dentisterie, Hébergement…) */}
                        {Object.entries(
                            referentiel.services.reduce((groupes, service) => ({ ...groupes, [service.categorie ?? 'Autres']: [...(groupes[service.categorie ?? 'Autres'] ?? []), service] }), {}),
                        ).map(([categorie, services]) => (
                            <optgroup key={categorie} label={categorie}>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>{service.nom} — {ariary(service.prix)}</option>
                                ))}
                            </optgroup>
                        ))}
                    </Field>
                </div>
                <Field label="Quantité" name="qte" type="number" min="1" required value={form.qte} onChange={(e) => setForm({ ...form, qte: Number(e.target.value) })} />
            </div>
            <div>
                <Button type="submit" disabled={referentiel.services.length === 0}>Ajouter au panier</Button>
            </div>
        </form>
    );
}

function FormulaireArticle({ articles, ajouter }) {
    const [form, setForm] = useState({ article_id: '', qte: 1 });
    const stockCourant = articles.find((article) => article.id === Number(form.article_id))?.stock_vendable ?? 0;
    const erreurStock = Number(form.article_id) > 0 && form.qte > stockCourant;

    async function submit(event) {
        event.preventDefault();
        if (await ajouter('article', form)) {
            setForm({ article_id: '', qte: 1 });
        }
    }

    return (
        <form onSubmit={submit} className={carte}>
            {!articles.some((article) => article.stock_vendable > 0) && (
                <ConfigurationManquante
                    titre="Aucun médicament en stock."
                    aide="Les médicaments n'apparaissent à la vente qu'après la validation d'un lot dans les entrées de stock."
                    to="/pharmacie/entrees"
                    lien="Ouvrir les entrées de stock"
                    permission="pharma.entrees"
                />
            )}
            <div className="grid grid-cols-4 items-end gap-3">
                <div className="col-span-2">
                    <ArticleSelect label="Médicament" articles={articles} value={form.article_id} onChange={(article) => setForm({ ...form, article_id: article.id })} />
                </div>
                <div className="col-span-2 sm:col-span-1">
                    <Field label="Qté (unités)" name="qte" type="number" min="1" required value={form.qte} onChange={(e) => setForm({ ...form, qte: Number(e.target.value) })} />
                    {erreurStock && <p className="mt-1 text-xs text-red-600">Stock disponible : {stockCourant}</p>}
                </div>
            </div>
            <p className="text-xs text-slate-500">Le stock est réservé dès l'ajout au panier, selon la règle FEFO/FIFO.</p>
            <div>
                <Button type="submit" disabled={erreurStock || !form.article_id}>Ajouter au panier</Button>
            </div>
        </form>
    );
}

function FormulaireAnalyses({ referentiel, ajouter }) {
    const [ids, setIds] = useState([]);
    const total = useMemo(() => referentiel.analyses.filter((analyse) => ids.includes(analyse.id)).reduce((somme, analyse) => somme + Number(analyse.prix), 0), [ids, referentiel.analyses]);

    async function submit(event) {
        event.preventDefault();
        if (await ajouter('analyse', { analyse_id: ids })) {
            setIds([]);
        }
    }

    const toggle = (id) => setIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));

    return (
        <form onSubmit={submit} className={carte}>
            <p className="text-xs text-slate-500">Une seule ligne d'analyses par panier. Montant = somme des prix.</p>
            <div className="max-h-72 space-y-1 overflow-y-auto">
                {referentiel.analyses.map((analyse) => (
                    <label key={analyse.id} className="flex cursor-pointer items-center justify-between rounded-md px-3 py-2 hover:bg-slate-50">
                        <span className="flex items-center gap-3 text-sm">
                            <input type="checkbox" checked={ids.includes(analyse.id)} onChange={() => toggle(analyse.id)} />
                            {analyse.nom}
                        </span>
                        <span className="text-sm font-semibold text-slate-700">{ariary(analyse.prix)}</span>
                    </label>
                ))}
                {referentiel.analyses.length === 0 && (
                    <ConfigurationManquante
                        titre="Aucune analyse configurée."
                        aide="Ajoutez les analyses médicales avec leur prix."
                        to="/pharmacie/gestion"
                        lien="Ouvrir la gestion de la Pharmacie (onglet Analyses)"
                        permission="pharma.gestion"
                    />
                )}
            </div>
            <p className="text-sm">
                Total sélection : <strong>{ariary(total)}</strong>
            </p>
            <div>
                <Button type="submit" disabled={ids.length === 0}>Ajouter au panier</Button>
            </div>
        </form>
    );
}

function FormulaireKit({ referentiel, ajouter }) {
    const [form, setForm] = useState({ kit_id: '', qte: 1 });

    async function submit(event) {
        event.preventDefault();
        if (await ajouter('kit', form)) {
            setForm({ kit_id: '', qte: 1 });
        }
    }

    return (
        <form onSubmit={submit} className={carte}>
            {referentiel.kits.length === 0 && (
                <ConfigurationManquante
                    titre="Aucun kit configuré."
                    aide="Ajoutez les kits (kit simple, kit avec suture…) avec leur prix."
                    to="/pharmacie/gestion"
                    lien="Ouvrir la gestion de la Pharmacie (onglet Kits)"
                    permission="pharma.gestion"
                />
            )}
            <div className="grid grid-cols-3 items-end gap-3">
                <div className="col-span-2">
                    <Field label="Kit" name="kit_id" as="select" required value={form.kit_id} onChange={(e) => setForm({ ...form, kit_id: Number(e.target.value) })}>
                        <option value="">— Choisir —</option>
                        {referentiel.kits.map((kit) => (
                            <option key={kit.id} value={kit.id}>{kit.nom} — {ariary(kit.prix)}</option>
                        ))}
                    </Field>
                </div>
                <Field label="Quantité" name="qte" type="number" min="1" required value={form.qte} onChange={(e) => setForm({ ...form, qte: Number(e.target.value) })} />
            </div>
            <div>
                <Button type="submit" disabled={referentiel.kits.length === 0}>Ajouter au panier</Button>
            </div>
        </form>
    );
}
