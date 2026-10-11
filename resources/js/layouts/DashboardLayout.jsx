import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { roleLabels } from '../services/labels';

const navigation = [
    { to: '/', label: 'Tableau de bord', end: true },
    { to: '/personnel', label: 'Personnel', permission: 'employees.view' },
    { to: '/services', label: 'Services' },
    { to: '/parametres/conges', label: 'Types de congés', permission: 'leave_settings.manage' },
    { to: '/parametres/jours-feries', label: 'Jours fériés', permission: 'leave_settings.manage' },
    { to: '/admin/comptes', label: 'Comptes', permission: 'users.manage' },
    { to: '/admin/journal', label: "Journal d'audit", permission: 'audit_logs.view' },
];

export default function DashboardLayout() {
    const { user, logout, can } = useAuth();
    const navigate = useNavigate();
    const [menuOpen, setMenuOpen] = useState(false);

    const links = navigation.filter((item) => !item.permission || can(item.permission));

    async function handleLogout() {
        await logout();
        navigate('/connexion');
    }

    return (
        <div className="min-h-screen md:flex">
            <aside className="border-b border-slate-200 bg-white md:min-h-screen md:w-60 md:border-r md:border-b-0">
                <div className="flex items-center justify-between px-5 py-4">
                    <div>
                        <p className="text-xs font-semibold tracking-wide text-sky-700">OMIT</p>
                        <p className="text-sm font-semibold">Gestion du personnel</p>
                    </div>
                    <button
                        type="button"
                        className="rounded-md border border-slate-300 px-3 py-1 text-sm md:hidden"
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
                                `rounded-md px-3 py-2 text-sm ${isActive ? 'bg-sky-50 font-medium text-sky-800' : 'text-slate-600 hover:bg-slate-50'}`
                            }
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>

            <div className="flex-1">
                <header className="flex flex-wrap items-center justify-end gap-4 border-b border-slate-200 bg-white px-6 py-3">
                    <div className="text-right">
                        <p className="text-sm font-medium">{user.name}</p>
                        <p className="text-xs text-slate-500">{user.roles.map((role) => roleLabels[role] ?? role).join(' · ')}</p>
                    </div>
                    <NavLink to="/mot-de-passe" className="text-sm text-slate-600 hover:text-sky-700">
                        Mot de passe
                    </NavLink>
                    <button type="button" onClick={handleLogout} className="text-sm text-slate-600 hover:text-red-600">
                        Déconnexion
                    </button>
                </header>
                <main className="mx-auto max-w-6xl p-4 md:p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
