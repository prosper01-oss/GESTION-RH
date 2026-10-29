import { Navigate, Route, Routes } from 'react-router-dom';
import RequireAuth from './components/RequireAuth';
import AuthLayout from './layouts/AuthLayout';
import DashboardLayout from './layouts/DashboardLayout';
import AuditLogList from './pages/admin/AuditLogList';
import BackupList from './pages/admin/BackupList';
import UserList from './pages/admin/UserList';
import ApprovalQueue from './pages/approvals/ApprovalQueue';
import ChangePassword from './pages/auth/ChangePassword';
import Login from './pages/auth/Login';
import MySignature from './pages/auth/MySignature';
import NotificationPreferences from './pages/auth/NotificationPreferences';
import Dashboard from './pages/dashboard/Dashboard';
import DepartmentList from './pages/departments/DepartmentList';
import EmployeeForm from './pages/employees/EmployeeForm';
import EmployeeList from './pages/employees/EmployeeList';
import EmployeeShow from './pages/employees/EmployeeShow';
import ImportData from './pages/employees/ImportData';
import DelegationList from './pages/delegations/DelegationList';
import ExitPassFollowUp from './pages/exits/ExitPassFollowUp';
import ExitPassForm from './pages/exits/ExitPassForm';
import LeaveRequestForm from './pages/leaves/LeaveRequestForm';
import Articles from './pages/pharma/Articles';
import Caisse from './pages/pharma/Caisse';
import Consultations from './pages/pharma/Consultations';
import Cmm from './pages/pharma/Cmm';
import Entrees from './pages/pharma/Entrees';
import Gestion from './pages/pharma/Gestion';
import Historique from './pages/pharma/Historique';
import Inventaire from './pages/pharma/Inventaire';
import Mouvements from './pages/pharma/Mouvements';
import PharmaDashboard from './pages/pharma/PharmaDashboard';
import Reappro from './pages/pharma/Reappro';
import Sorties from './pages/pharma/Sorties';
import PermissionRequestForm from './pages/permissions/PermissionRequestForm';
import RequestEdit from './pages/requests/RequestEdit';
import RequestList from './pages/requests/RequestList';
import RequestShow from './pages/requests/RequestShow';
import CongeRHForm from './pages/rh/CongeRHForm';
import DemandesRH from './pages/rh/DemandesRH';
import PermissionRHForm from './pages/rh/PermissionRHForm';
import Reports from './pages/reports/Reports';
import PermissionTypeSettings from './pages/settings/PermissionTypeSettings';
import LeaveTypeSettings from './pages/settings/LeaveTypeSettings';
import PublicHolidaySettings from './pages/settings/PublicHolidaySettings';

export default function App() {
    return (
        <Routes>
            <Route element={<AuthLayout />}>
                <Route path="/connexion" element={<Login />} />
            </Route>

            <Route element={<RequireAuth />}>
                <Route element={<AuthLayout />}>
                    <Route path="/mot-de-passe" element={<ChangePassword />} />
                </Route>

                <Route element={<DashboardLayout />}>
                    <Route index element={<Dashboard />} />
                    <Route path="/services" element={<DepartmentList />} />
                    <Route path="/demandes" element={<RequestList />} />
                    <Route path="/demandes/nouvelle" element={<LeaveRequestForm />} />
                    <Route path="/demandes/permission/nouvelle" element={<PermissionRequestForm />} />
                    <Route path="/demandes/sortie/nouvelle" element={<ExitPassForm />} />
                    <Route path="/demandes/:id" element={<RequestShow />} />
                    <Route path="/demandes/:id/modifier" element={<RequestEdit />} />
                    <Route path="/a-traiter" element={<ApprovalQueue />} />
                    <Route path="/suppleances" element={<DelegationList />} />
                    <Route path="/preferences" element={<NotificationPreferences />} />
                    <Route path="/signature" element={<MySignature />} />
                    <Route element={<RequireAuth permission="requests.review" />}>
                        <Route path="/sorties" element={<ExitPassFollowUp />} />
                        <Route path="/rh/demandes" element={<DemandesRH />} />
                        <Route path="/rh/demandes/conge/nouveau" element={<CongeRHForm />} />
                        <Route path="/rh/demandes/permission/nouveau" element={<PermissionRHForm />} />
                    </Route>
                    <Route element={<RequireAuth permission="reports.view" />}>
                        <Route path="/rapports" element={<Reports />} />
                    </Route>

                    <Route element={<RequireAuth permission="employees.view" />}>
                        <Route path="/personnel" element={<EmployeeList />} />
                        <Route path="/personnel/:id" element={<EmployeeShow />} />
                    </Route>
                    <Route element={<RequireAuth permission="employees.manage" />}>
                        <Route path="/personnel/nouveau" element={<EmployeeForm />} />
                        <Route path="/personnel/import" element={<ImportData />} />
                        <Route path="/personnel/:id/modifier" element={<EmployeeForm />} />
                    </Route>
                    <Route element={<RequireAuth permission="leave_settings.manage" />}>
                        <Route path="/parametres/conges" element={<LeaveTypeSettings />} />
                        <Route path="/parametres/jours-feries" element={<PublicHolidaySettings />} />
                        <Route path="/parametres/permissions" element={<PermissionTypeSettings />} />
                    </Route>
                    <Route element={<RequireAuth permission="users.manage" />}>
                        <Route path="/admin/comptes" element={<UserList />} />
                    </Route>
                    <Route element={<RequireAuth permission="backups.manage" />}>
                        <Route path="/admin/sauvegardes" element={<BackupList />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.dashboard" />}>
                        <Route path="/pharmacie" element={<PharmaDashboard />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.consultations" />}>
                        <Route path="/pharmacie/consultations" element={<Consultations />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.caisse" />}>
                        <Route path="/pharmacie/caisse" element={<Caisse />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.historique" />}>
                        <Route path="/pharmacie/historique" element={<Historique />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.gestion" />}>
                        <Route path="/pharmacie/gestion" element={<Gestion />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.articles" />}>
                        <Route path="/pharmacie/articles" element={<Articles />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.entrees" />}>
                        <Route path="/pharmacie/entrees" element={<Entrees />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.sorties" />}>
                        <Route path="/pharmacie/sorties" element={<Sorties />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.mouvements" />}>
                        <Route path="/pharmacie/mouvements" element={<Mouvements />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.inventaire" />}>
                        <Route path="/pharmacie/inventaire" element={<Inventaire />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.reappro" />}>
                        <Route path="/pharmacie/reappro" element={<Reappro />} />
                    </Route>
                    <Route element={<RequireAuth permission="pharma.cmm" />}>
                        <Route path="/pharmacie/cmm" element={<Cmm />} />
                    </Route>
                    <Route element={<RequireAuth permission="audit_logs.view" />}>
                        <Route path="/admin/journal" element={<AuditLogList />} />
                    </Route>
                </Route>
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
