import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';
import ThemeToggle from '../components/ThemeToggle';
import { useAuth } from '../hooks/useAuth';
import { roleLabels } from '../services/labels';

const navigation = [
    { to: '/', label: 'Tableau de bord', end: true },
    { to: '/demandes', label: 'Mes demandes', employeeOnly: true },
    { to: '/a-traiter', label: 'À traiter', anyPermission: ['requests.review', 'requests.decide'] },
    { to: '/sorties', label: 'Sorties', permission: 'requests.review' },
    { to: '/rapports', label: 'Tableau de bord RH', permission: 'reports.view' },
    { to: '/suppleances', label: 'Suppléances', anyRole: ['medecin_chef', 'rh'], orDelegate: true },
    { to: '/personnel', label: 'Personnel', permission: 'employees.view' },
    { to: '/services', label: 'Services' },
    { to: '/parametres/conges', label: 'Types de congés', permission: 'leave_settings.manage' },
    { to: '/parametres/permissions', label: 'Catégories de permission', permission: 'leave_settings.manage' },
    { to: '/parametres/jours-feries', label: 'Jours fériés', permission: 'leave_settings.manage' },
    { to: '/admin/comptes', label: 'Comptes', permission: 'users.manage' },
    { to: '/admin/journal', label: "Journal d'audit", permission: 'audit_logs.view' },
    { to: '/admin/sauvegardes', label: 'Sauvegardes', permission: 'backups.manage' },
];

export default function DashboardLayout() {
    const { user, logout, can } = useAuth();
    const navigate = useNavigate();
    const [menuOpen, setMenuOpen] = useState(false);

    const links = navigation.filter(
        (item) =>
            (!item.permission || can(item.permission)) &&
            (!item.anyPermission || item.anyPermission.some((permission) => can(permission))) &&
            (!item.employeeOnly || user.employee_id) &&
            (!item.anyRole || item.anyRole.some((role) => user.roles.includes(role)) || (item.orDelegate && (can('requests.review') || can('requests.decide')))),
    );

    async function handleLogout() {
        await logout();
        navigate('/connexion');
    }

    return (
        <div className="min-h-screen md:flex">
            <aside className="bg-noir-dore border-b border-gold/40 md:min-h-screen md:w-60 md:border-r md:border-b-0 print:hidden">
                <div className="flex items-center justify-between px-5 py-4">
                    <div>
                        <p className="text-or text-lg font-semibold tracking-[0.25em]">OMIT</p>
                        <p className="text-sm text-stone-300">Gestion du personnel</p>
                    </div>
                    <button
                        type="button"
                        className="rounded-md border border-gold/60 px-3 py-1 text-sm text-gold-light md:hidden"
                        onClick={() => setMenuOpen((open) => !open)}
                        aria-expanded={menuOpen}
                    >
                        Menu
                    </button>
                </div>
                <nav className={`${menuOpen ? 'flex' : 'hidden'} flex-col gap-1 px-3 pb-4 md:flex`}>
                    {links.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            end={item.end}
                            onClick={() => setMenuOpen(false)}
                            className={({ isActive }) =>
                                `rounded-md px-3 py-2 text-sm ${isActive ? 'border-l-2 border-gold bg-white/10 font-medium text-gold-light' : 'text-stone-300 hover:bg-white/5 hover:text-gold-light'}`
                            }
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>

            <div className="flex-1">
                <header className="flex flex-wrap items-center justify-end gap-4 border-b border-gold/40 bg-ink px-6 py-3 text-stone-200 print:hidden">
                    <ThemeToggle className="text-stone-300 hover:bg-white/10 hover:text-gold-light" />
                    <NotificationBell />
                    <div className="text-right">
                        <p className="text-sm font-medium text-stone-100">{user.name}</p>
                        <p className="text-xs text-gold">{user.roles.map((role) => roleLabels[role] ?? role).join(' · ')}</p>
                    </div>
                    <NavLink to="/mot-de-passe" className="text-sm text-stone-300 hover:text-gold-light">
                        Mot de passe
                    </NavLink>
                    <button type="button" onClick={handleLogout} className="text-sm text-stone-300 hover:text-red-400">
                        Déconnexion
                    </button>
                </header>
                <main className="mx-auto max-w-6xl p-4 md:p-6 print:max-w-none print:p-0">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
