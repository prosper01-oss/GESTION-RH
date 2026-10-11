import { Navigate, Route, Routes } from 'react-router-dom';
import RequireAuth from './components/RequireAuth';
import AuthLayout from './layouts/AuthLayout';
import DashboardLayout from './layouts/DashboardLayout';
import AuditLogList from './pages/admin/AuditLogList';
import UserList from './pages/admin/UserList';
import ChangePassword from './pages/auth/ChangePassword';
import Login from './pages/auth/Login';
import Dashboard from './pages/dashboard/Dashboard';
import DepartmentList from './pages/departments/DepartmentList';
import EmployeeForm from './pages/employees/EmployeeForm';
import EmployeeList from './pages/employees/EmployeeList';
import EmployeeShow from './pages/employees/EmployeeShow';

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

                    <Route element={<RequireAuth permission="employees.view" />}>
                        <Route path="/personnel" element={<EmployeeList />} />
                        <Route path="/personnel/:id" element={<EmployeeShow />} />
                    </Route>
                    <Route element={<RequireAuth permission="employees.manage" />}>
                        <Route path="/personnel/nouveau" element={<EmployeeForm />} />
                        <Route path="/personnel/:id/modifier" element={<EmployeeForm />} />
                    </Route>
                    <Route element={<RequireAuth permission="users.manage" />}>
                        <Route path="/admin/comptes" element={<UserList />} />
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
