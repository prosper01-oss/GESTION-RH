import { useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage, validationErrors } from '../../services/api';

/**
 * Bloc d'import : vérification à blanc, puis import réel si le fichier est sans erreur.
 */
function ImportBlock({ title, description, templateType, endpoint }) {
    const [file, setFile] = useState(null);
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function send(dryRun) {
        if (!dryRun && !window.confirm('Importer définitivement ce fichier ?')) {
            return;
        }
        const body = new FormData();
        body.append('file', file);
        body.append('dry_run', dryRun ? '1' : '0');
        setBusy(true);
        setError('');
        try {
            const { data } = await api.post(endpoint, body);
            setResult(data.data);
        } catch (err) {
            if (err.response?.status === 422 && err.response.data.data) {
                setResult(err.response.data.data);
            } else {
                setResult(null);
                setError(validationErrors(err).file ?? errorMessage(err));
            }
        } finally {
            setBusy(false);
        }
    }

    const verified = result?.dry_run && result.errors.length === 0;

    return (
        <section className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-6">
            <h2 className="text-base font-semibold">{title}</h2>
            <p className="text-sm text-slate-600">{description}</p>
            <a href={`/api/v1/imports/templates/${templateType}`} className="text-sm text-sky-700 hover:underline">
                Télécharger le modèle CSV
            </a>
            <input type="file" accept=".csv,text/csv" onChange={(event) => { setFile(event.target.files[0] ?? null); setResult(null); }} className="text-sm" />
            <div className="flex flex-wrap gap-3">
                <Button variant="secondary" disabled={!file || busy} onClick={() => send(true)}>
                    1. Vérifier le fichier
                </Button>
                <Button disabled={!verified || busy} onClick={() => send(false)}>
                    2. Importer
                </Button>
            </div>
            <Alert>{error}</Alert>
            {result && result.errors.length > 0 && (
                <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p className="mb-2 font-medium">{result.errors.length} ligne(s) à corriger — rien n'a été importé :</p>
                    <ul className="flex max-h-64 flex-col gap-1 overflow-y-auto">
                        {result.errors.map((item) => (
                            <li key={item.line}>
                                Ligne {item.line} : {item.messages.join(' ')}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {verified && (
                <Alert type="info">
                    {result.rows} ligne(s) valides.
                    {result.new_departments?.length > 0 && ` Services qui seront créés : ${result.new_departments.join(', ')}.`} Cliquez sur « Importer » pour enregistrer.
                </Alert>
            )}
            {result && !result.dry_run && result.errors.length === 0 && <Alert type="success">{result.created} enregistrement(s) importé(s).</Alert>}
        </section>
    );
}

export default function ImportData() {
    const { can } = useAuth();

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold">Reprise des données</h1>
            <p className="text-sm text-slate-600">
                Remplissez le modèle dans Excel, enregistrez-le au format CSV, puis vérifiez-le avant l'import. Un fichier contenant une seule erreur n'est pas importé : corrigez les lignes signalées et recommencez.
            </p>
            {can('employees.manage') && (
                <ImportBlock
                    title="1. Fiches du personnel"
                    description="Colonnes : matricule, nom, prénom, service, fonction, date d'embauche (JJ/MM/AAAA), type de contrat (cdi, cdd, stage, autre), téléphone et email facultatifs. Les services absents sont créés."
                    templateType="personnel"
                    endpoint="/employees/import"
                />
            )}
            {can('leave_balances.adjust') && (
                <ImportBlock
                    title="2. Soldes de congés"
                    description="Colonnes : matricule, code du type de congé (ex. CA), année, nombre de jours (multiple de 0,5), motif facultatif. Chaque ligne devient une correction visible dans l'historique du solde."
                    templateType="soldes"
                    endpoint="/leave-balances/import"
                />
            )}
        </div>
    );
}
