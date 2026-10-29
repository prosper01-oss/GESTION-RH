import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Alert from '../../components/Alert';
import EmployeeDetails from '../../components/EmployeeDetails';
import LeaveBalances from '../../components/LeaveBalances';
import PermissionQuota from '../../components/PermissionQuota';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage } from '../../services/api';

const shortcuts = [
    { to: '/demandes/nouvelle', label: 'Demander un congé', description: 'Saisir et envoyer une demande', employeeOnly: true },
    { to: '/demandes/permission/nouvelle', label: 'Demander une permission', description: 'Absence de quelques heures ou jours', employeeOnly: true },
    { to: '/demandes/sortie/nouvelle', label: 'Billet de sortie', description: 'Sortie pendant les heures de service', employeeOnly: true },
    { to: '/a-traiter', label: 'Demandes à traiter', description: 'Contrôle, décision, sorties à valider', anyPermission: ['requests.review', 'requests.decide'], orExitApprover: true },
    { to: '/personnel', label: 'Annuaire du personnel', description: 'Rechercher et consulter les fiches', permission: 'employees.view' },
    { to: '/personnel/nouveau', label: 'Nouvel employé', description: 'Créer une fiche du personnel', permission: 'employees.manage' },
    { to: '/services', label: 'Services', description: 'Liste des services et effectifs' },
    { to: '/rapports', label: 'Tableau de bord RH', description: 'Indicateurs, absences et exports PDF / Excel', permission: 'reports.view' },
    { to: '/parametres/conges', label: 'Types de congés', description: 'Droits, décompte et report', permission: 'leave_settings.manage' },
    { to: '/parametres/jours-feries', label: 'Jours fériés', description: 'Calendrier des jours non travaillés', permission: 'leave_settings.manage' },
    { to: '/pharmacie/consultations', label: 'Mes patients', description: 'Patients adressés par la caisse, en attente de consultation', permission: 'pharma.consultations' },
    { to: '/pharmacie/caisse', label: 'Caisse', description: 'Encaisser les ventes de la pharmacie', permission: 'pharma.caisse' },
    { to: '/pharmacie', label: 'Tableau de bord Pharmacie', description: "Chiffre d'affaires, stock critique et péremptions", permission: 'pharma.dashboard' },
    { to: '/pharmacie/articles', label: 'Articles et stock', description: 'Médicaments, lots et fournisseurs', permission: 'pharma.articles' },
    { to: '/pharmacie/entrees', label: 'Entrées de stock', description: 'Approvisionnements et validation des lots', permission: 'pharma.entrees' },
    { to: '/admin/comptes', label: 'Comptes', description: 'Créer les comptes et attribuer les rôles', permission: 'users.manage' },
    { to: '/admin/journal', label: "Journal d'audit", description: 'Connexions et actions sensibles', permission: 'audit_logs.view' },
];

export default function Dashboard() {
    const { user, can } = useAuth();
    const [employee, setEmployee] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        if (user.employee_id) {
            api.get(`/employees/${user.employee_id}`)
                .then(({ data }) => setEmployee(data.data))
                .catch((err) => setError(errorMessage(err)));
        }
    }, [user.employee_id]);

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-xl font-semibold">Bonjour {user.name}</h1>

            {user.employee_id && (
                <section className="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Ma fiche</h2>
                    <Alert>{error}</Alert>
                    {employee && <EmployeeDetails employee={employee} />}
                </section>
            )}

            {user.employee_id && (
                <section className="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Mes soldes de congés</h2>
                    <LeaveBalances url="/leave-balances/me" />
                </section>
            )}

            {user.employee_id && <PermissionQuota url="/permission-quota/me" />}

            <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {shortcuts
                    .filter(
                        (item) =>
                            (!item.permission || can(item.permission)) &&
                            (!item.anyPermission || item.anyPermission.some((permission) => can(permission)) || (item.orExitApprover && user.approves_exits)) &&
                            (!item.employeeOnly || user.employee_id),
                    )
                    .map((item) => (
                        <Link key={item.to} to={item.to} className="rounded-lg border border-slate-200 bg-white p-5 hover:border-sky-300 hover:shadow-sm">
                            <p className="font-medium text-sky-800">{item.label}</p>
                            <p className="mt-1 text-sm text-slate-500">{item.description}</p>
                        </Link>
                    ))}
            </section>

        </div>
    );
}
