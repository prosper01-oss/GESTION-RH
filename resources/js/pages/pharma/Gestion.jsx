import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import { DeleteButton, EditButton } from '../../components/IconButton';
import ConfirmDialog from '../../components/ConfirmDialog';
import Field from '../../components/Field';
import Modal from '../../components/Modal';
import PriceField from '../../components/PriceField';
import SearchInput from '../../components/SearchInput';
import Tabs from '../../components/Tabs';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage, validationErrors } from '../../services/api';
import { montant } from '../../services/pharma';

const reference = (row) => `REF-${String(row.id).padStart(4, '0')}`;

const badge = (texte, actif) => (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${actif ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'}`}>{texte}</span>
);

/**
 * Onglets de gestion du catalogue. Chaque onglet décrit son point d'accès API, ses colonnes et son formulaire.
 */
const onglets = [
    {
        key: 'analyse',
        label: 'Analyses',
        endpoint: 'analyses',
        columns: [
            { label: 'Réf.', render: reference },
            { label: 'Nom', render: (row) => row.nom },
            { label: 'Prix (Ar)', render: (row) => montant(row.prix) },
        ],
        fields: [
            { name: 'nom', label: 'Nom', type: 'text', placeholder: "Nom de l'acte" },
            { name: 'prix', label: 'Prix (Ar)', type: 'price' },
        ],
        empty: { nom: '', prix: '' },
        toForm: (row) => ({ nom: row.nom, prix: String(row.prix) }),
    },
    {
        key: 'echographie',
        label: 'Échographies',
        endpoint: 'echographies',
        columns: [
            { label: 'Nom', render: (row) => row.nom },
            { label: 'Prix (Ar)', render: (row) => montant(row.prix) },
        ],
        fields: [
            { name: 'nom', label: 'Nom', type: 'text', placeholder: "Nom de l'acte" },
            { name: 'prix', label: 'Prix (Ar)', type: 'price' },
        ],
        empty: { nom: '', prix: '' },
        toForm: (row) => ({ nom: row.nom, prix: String(row.prix) }),
    },
    {
        key: 'categorie',
        label: 'Catégories',
        endpoint: 'categories',
        columns: [{ label: 'Nom', render: (row) => row.nom_cat }],
        fields: [{ name: 'nom_cat', label: 'Nom de la catégorie', type: 'text', maxLength: 30 }],
        empty: { nom_cat: '' },
        toForm: (row) => ({ nom_cat: row.nom_cat }),
    },
    {
        key: 'fournisseur',
        label: 'Fournisseurs',
        endpoint: 'fournisseurs',
        permission: 'pharma.articles',
        columns: [
            { label: 'Fournisseur', render: (row) => row.nom },
            { label: 'Téléphone', render: (row) => row.telephone || '—' },
            { label: 'Email', render: (row) => row.email || '—' },
            { label: 'Adresse', render: (row) => row.adresse || '—' },
            { label: 'Entrées', render: (row) => row.nb_entrees },
            { label: 'Statut', render: (row) => badge(row.actif ? 'Actif' : 'Inactif', row.actif) },
        ],
        fields: [
            { name: 'nom', label: 'Nom du fournisseur', type: 'text', maxLength: 120 },
            { name: 'telephone', label: 'Téléphone', type: 'text', maxLength: 40, placeholder: '034 00 000 00', required: false },
            { name: 'email', label: 'Email', type: 'email', maxLength: 120, required: false },
            { name: 'adresse', label: 'Adresse', type: 'text', maxLength: 255, required: false },
        ],
        empty: { nom: '', telephone: '', email: '', adresse: '' },
        toForm: (row) => ({ nom: row.nom, telephone: row.telephone, email: row.email, adresse: row.adresse }),
        toPayload: (form) => ({ ...form, telephone: form.telephone || null, email: form.email || null, adresse: form.adresse || null }),
    },
    {
        key: 'service',
        label: 'Services',
        endpoint: 'services',
        options: 'services/categories',
        columns: [
            { label: 'Catégorie', render: (row) => row.categorie },
            { label: 'Service', render: (row) => row.nom_service },
            { label: 'Prix (Ar)', render: (row) => montant(row.prix_service) },
            { label: 'Type', render: (row) => badge(row.isConsult === 1 ? 'Consultation' : row.acte_dentaire ? 'Acte dentaire' : 'Prestation', row.isConsult === 1 || row.acte_dentaire) },
        ],
        fields: [
            { name: 'categorie_id', label: 'Catégorie', type: 'select', choices: (options) => (options ?? []).map((c) => ({ value: c.id, label: c.nom_cat })) },
            { name: 'nom_service', label: 'Nom du service', type: 'text', maxLength: 60 },
            { name: 'prix_service', label: 'Prix du service (Ar)', type: 'price' },
            { name: 'isConsult', label: 'Service de consultation', type: 'checkbox' },
            { name: 'acte_dentaire', label: "Acte dentaire (le dentiste peut l'envoyer en caisse)", type: 'checkbox' },
        ],
        empty: { categorie_id: '', nom_service: '', prix_service: '', isConsult: false, acte_dentaire: false },
        toForm: (row) => ({ categorie_id: row.categorie_id, nom_service: row.nom_service, prix_service: String(row.prix_service), isConsult: row.isConsult === 1, acte_dentaire: row.acte_dentaire }),
    },
    {
        key: 'kit',
        label: 'Kits',
        endpoint: 'kits',
        columns: [
            { label: 'Kit', render: (row) => row.nom_kit },
            { label: 'Prix (Ar)', render: (row) => montant(row.prix_kit) },
        ],
        fields: [
            { name: 'nom_kit', label: 'Nom du kit', type: 'text', maxLength: 60 },
            { name: 'prix_kit', label: 'Prix du kit (Ar)', type: 'price' },
        ],
        empty: { nom_kit: '', prix_kit: '' },
        toForm: (row) => ({ nom_kit: row.nom_kit, prix_kit: String(row.prix_kit) }),
    },
    {
        key: 'detailkit',
        label: 'Composition des kits',
        endpoint: 'detail-kits',
        options: 'detail-kits/options',
        columns: [
            { label: 'Kit', render: (row) => row.kit },
            { label: 'Article', render: (row) => row.article },
            { label: 'Qté', render: (row) => row.qte_kit },
        ],
        fields: [
            { name: 'id_kit', label: 'Kit', type: 'select', choices: (options) => (options?.kits ?? []).map((k) => ({ value: k.id, label: k.nom })) },
            { name: 'id_article', label: 'Article', type: 'select', choices: (options) => (options?.articles ?? []).map((a) => ({ value: a.id, label: a.nom })) },
            { name: 'qte_kit', label: 'Quantité dans le kit', type: 'number', min: 1 },
        ],
        empty: { id_kit: '', id_article: '', qte_kit: 1 },
        toForm: (row) => ({ id_kit: row.id_kit, id_article: row.id_article, qte_kit: row.qte_kit }),
    },
];

export default function Gestion() {
    const { can } = useAuth();
    const tabs = useMemo(() => onglets.filter((onglet) => !onglet.permission || can(onglet.permission)), [can]);
    const [actif, setActif] = useState(tabs[0]?.key);
    const onglet = tabs.find((item) => item.key === actif) ?? tabs[0];

    const [rows, setRows] = useState([]);
    const [options, setOptions] = useState(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [message, setMessage] = useState({ type: 'error', text: '' });
    const [form, setForm] = useState(null);
    const [editing, setEditing] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [toDelete, setToDelete] = useState(null);

    const load = useCallback(() => {
        setLoading(true);
        setRows([]);
        setOptions(null);
        Promise.all([api.get(`/pharma/${onglet.endpoint}`), onglet.options ? api.get(`/pharma/${onglet.options}`) : null])
            .then(([liste, referentiel]) => {
                setRows(liste.data.data);
                if (referentiel) {
                    setOptions(referentiel.data.data ?? referentiel.data);
                }
            })
            .catch((error) => setMessage({ type: 'error', text: errorMessage(error) }))
            .finally(() => setLoading(false));
    }, [onglet]);

    useEffect(() => {
        setSearch('');
        setMessage({ type: 'error', text: '' });
        load();
    }, [load]);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q ? rows.filter((row) => Object.values(row).some((value) => value !== null && String(value).toLowerCase().includes(q))) : rows;
    }, [rows, search]);

    function openForm(row = null) {
        setEditing(row);
        setForm(row ? onglet.toForm(row) : { ...onglet.empty });
        setErrors({});
    }

    function closeForm() {
        setForm(null);
        setEditing(null);
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        const payload = onglet.toPayload ? onglet.toPayload(form) : form;

        try {
            const { data } = editing ? await api.put(`/pharma/${onglet.endpoint}/${editing.id}`, payload) : await api.post(`/pharma/${onglet.endpoint}`, payload);
            setMessage({ type: 'success', text: data.message ?? 'Enregistré.' });
            closeForm();
            load();
        } catch (error) {
            setErrors(validationErrors(error));
            if (error.response?.status !== 422 || error.response.data.errors === undefined) {
                setErrors({ _general: errorMessage(error) });
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleDelete() {
        try {
            const { data } = await api.delete(`/pharma/${onglet.endpoint}/${toDelete.id}`);
            setMessage({ type: 'success', text: data.message ?? 'Supprimé.' });
            load();
        } catch (error) {
            setMessage({ type: 'error', text: errorMessage(error) });
        } finally {
            setToDelete(null);
        }
    }

    function renderField(field) {
        const common = { label: field.label, name: field.name, error: errors[field.name], required: field.required !== false };
        const set = (value) => setForm({ ...form, [field.name]: value });

        switch (field.type) {
            case 'price':
                return <PriceField key={field.name} {...common} value={form[field.name]} onChange={set} />;
            case 'select':
                return (
                    <Field key={field.name} {...common} as="select" value={form[field.name]} onChange={(event) => set(event.target.value)}>
                        <option value="">— Choisir —</option>
                        {field.choices(options).map((choice) => (
                            <option key={choice.value} value={choice.value}>
                                {choice.label}
                            </option>
                        ))}
                    </Field>
                );
            case 'checkbox':
                return (
                    <label key={field.name} className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={Boolean(form[field.name])} onChange={(event) => set(event.target.checked)} />
                        {field.label}
                    </label>
                );
            default:
                return <Field key={field.name} {...common} type={field.type} min={field.min} maxLength={field.maxLength} placeholder={field.placeholder} value={form[field.name]} onChange={(event) => set(field.type === 'number' ? Number(event.target.value) : event.target.value)} />;
        }
    }

    if (!onglet) {
        return <Alert>Aucun onglet de gestion n'est accessible avec vos droits.</Alert>;
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Gestion des paramètres</h1>
            <Tabs tabs={tabs} active={onglet.key} onChange={setActif} />
            <Alert type={message.type}>{message.text}</Alert>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-semibold text-slate-700">{onglet.label}</h2>
                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput value={search} onChange={setSearch} />
                    <Button onClick={() => openForm()}>Ajouter</Button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-left text-slate-600">
                        <tr>
                            {onglet.columns.map((column) => (
                                <th key={column.label} className="px-4 py-3 font-medium">
                                    {column.label}
                                </th>
                            ))}
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={onglet.columns.length + 1} className="px-4 py-6 text-center text-slate-500">
                                    {loading ? 'Chargement…' : rows.length === 0 ? 'Aucune donnée.' : 'Aucun résultat pour cette recherche.'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((row) => (
                            <tr key={row.id} className="hover:bg-slate-50">
                                {onglet.columns.map((column) => (
                                    <td key={column.label} className="px-4 py-3">
                                        {column.render(row)}
                                    </td>
                                ))}
                                <td className="space-x-1 px-4 py-2 text-right whitespace-nowrap">
                                    <EditButton onClick={() => openForm(row)} />
                                    <DeleteButton onClick={() => setToDelete(row)} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal
                open={form !== null}
                title={editing ? `Modifier — ${onglet.label}` : `Ajouter — ${onglet.label}`}
                subtitle="Ajouter ou modifier une donnée de l'onglet actif."
                onClose={closeForm}
                footer={
                    <>
                        <Button variant="secondary" onClick={closeForm}>
                            Annuler
                        </Button>
                        <Button type="submit" form="form-gestion" disabled={submitting}>
                            {submitting ? '…' : 'Enregistrer'}
                        </Button>
                    </>
                }
            >
                {form && (
                    <form id="form-gestion" onSubmit={handleSubmit} className="flex flex-col gap-4">
                        <Alert>{errors._general}</Alert>
                        {onglet.fields.map(renderField)}
                    </form>
                )}
            </Modal>

            <ConfirmDialog open={toDelete !== null} message="Confirmer la suppression ?" confirmLabel="Supprimer" onConfirm={handleDelete} onCancel={() => setToDelete(null)} />
        </div>
    );
}
