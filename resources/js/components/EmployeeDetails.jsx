import { contractTypeLabels, formatDate, sexeLabels, situationFamilialeLabels } from '../services/labels';
import StatusBadge from './StatusBadge';

/**
 * Fiche d'identité professionnelle, avec la photo servie par l'API après contrôle des droits.
 */
export default function EmployeeDetails({ employee, photoVersion = '' }) {
    const identiteRows = [
        ['CIN', employee.cin || '—'],
        ['Date de naissance', formatDate(employee.date_naissance)],
        ['Sexe', sexeLabels[employee.sexe] ?? '—'],
        ['Situation familiale', situationFamilialeLabels[employee.situation_familiale] ?? '—'],
        ['N° CNASP', employee.numero_cnasp || '—'],
        ['Adresse', employee.adresse || '—'],
    ];

    const proRows = [
        ['Matricule', employee.matricule],
        ['Service', employee.department?.name ?? '—'],
        ['Fonction', employee.position],
        ['Contrat', contractTypeLabels[employee.contract_type] ?? employee.contract_type],
        ["Date d'embauche", formatDate(employee.hire_date)],
        ['Téléphone professionnel', employee.phone_pro || '—'],
        ['Email professionnel', employee.email_pro || '—'],
    ];

    return (
        <div className="flex flex-col gap-6 sm:flex-row">
            <div className="flex h-32 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-3xl font-semibold text-slate-400">
                {employee.has_photo ? (
                    <img src={`/api/v1/employees/${employee.id}/photo?v=${photoVersion || employee.updated_at}`} alt={`Photo de ${employee.prenom} ${employee.nom}`} className="h-full w-full object-cover" />
                ) : (
                    `${employee.prenom[0] ?? ''}${employee.nom[0] ?? ''}`
                )}
            </div>
            <div className="flex-1 flex flex-col gap-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-lg font-semibold">
                        {employee.prenom} {employee.nom}
                    </h2>
                    <StatusBadge status={employee.status} />
                </div>

                <div>
                    <p className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Identité</p>
                    <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        {identiteRows.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-slate-500">{label}</dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div>
                    <p className="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Informations professionnelles</p>
                    <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        {proRows.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-slate-500">{label}</dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </div>
        </div>
    );
}
