import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { KeyIcon, LogoutIcon } from '../components/Icons';
import NotificationBell from '../components/NotificationBell';
import ThemeToggle from '../components/ThemeToggle';
import { useAuth } from '../hooks/useAuth';
import { roleLabels } from '../services/labels';

const navigation = [
    { to: '/', label: 'Tableau de bord', end: true },
    { to: '/demandes', label: 'Mes demandes', employeeOnly: true },
    { to: '/a-traiter', label: 'À traiter', anyPermission: ['requests.review', 'requests.decide'], orExitApprover: true },
    { to: '/signature', label: 'Ma signature', permission: 'requests.decide' },
    { to: '/sorties', label: 'Sorties', permission: 'requests.review' },
    { to: '/rh/demandes', label: 'Demandes RH', permission: 'requests.review' },
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
    { section: 'Pharmacie' },
    { to: '/pharmacie', label: 'Tableau de bord', end: true, permission: 'pharma.dashboard' },
    { to: '/pharmacie/consultations', label: 'Mes patients', permission: 'pharma.consultations' },
    { to: '/pharmacie/caisse', label: 'Caisse', permission: 'pharma.caisse' },
    { to: '/pharmacie/historique', label: 'Historique des ventes', permission: 'pharma.historique' },
    { to: '/pharmacie/articles', label: 'Articles et stock', permission: 'pharma.articles' },
    { to: '/pharmacie/entrees', label: 'Entrées de stock', permission: 'pharma.entrees' },
    { to: '/pharmacie/sorties', label: 'Sorties de stock', permission: 'pharma.sorties' },
    { to: '/pharmacie/mouvements', label: 'Mouvements', permission: 'pharma.mouvements' },
    { to: '/pharmacie/inventaire', label: 'Inventaire', permission: 'pharma.inventaire' },
    { to: '/pharmacie/reappro', label: 'Réapprovisionnement', permission: 'pharma.reappro' },
    { to: '/pharmacie/cmm', label: 'CMM', permission: 'pharma.cmm' },
    { to: '/pharmacie/gestion', label: 'Gestion Pharmacie', permission: 'pharma.gestion' },
];

/** Initiales du nom affiché : « RH OMIT » → « RO ». */
function initials(name) {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0].toUpperCase())
            .join('') || '?'
    );
}

export default function DashboardLayout() {
    const { user, logout, can } = useAuth();
    const navigate = useNavigate();
    const [menuOpen, setMenuOpen] = useState(false);

    const allowed = navigation.filter(
        (item) =>
            (!item.permission || can(item.permission)) &&
            (!item.anyPermission || item.anyPermission.some((permission) => can(permission)) || (item.orExitApprover && user.approves_exits)) &&
            (!item.employeeOnly || user.employee_id) &&
            (!item.anyRole || item.anyRole.some((role) => user.roles.includes(role)) || (item.orDelegate && (can('requests.review') || can('requests.decide')))),
    );
    // Un intitulé de section n'est affiché que s'il reste au moins un lien accessible dessous.
    const links = allowed.filter((item, index) => !item.section || (allowed[index + 1] && !allowed[index + 1].section));

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
                    {links.map((item) =>
                        item.section ? (
                            <p key={item.section} className="px-3 pt-4 pb-1 text-xs font-medium tracking-wider text-gold uppercase">
                                {item.section}
                            </p>
                        ) : (
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
                        ),
                    )}
                </nav>
            </aside>

            <div className="flex-1">
                <header className="sticky top-0 z-30 flex items-center justify-end gap-1 border-b border-gold/40 bg-ink px-4 py-2 text-stone-200 sm:gap-2 sm:px-6 print:hidden">
                    <ThemeToggle className="text-stone-300 hover:bg-white/10 hover:text-gold-light" />
                    <NotificationBell />

                    <span className="mx-1 hidden h-6 w-px bg-gold/30 sm:mx-2 sm:block" aria-hidden="true" />

                    <div className="flex items-center gap-3">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/5 text-sm font-semibold text-gold-light ring-1 ring-gold/60" aria-hidden="true">
                            {initials(user.name)}
                        </span>
                        <div className="hidden min-w-0 leading-tight sm:block">
                            <p className="max-w-48 truncate text-sm font-medium text-stone-100">{user.name}</p>
                            <p className="max-w-48 truncate text-xs text-gold">{user.roles.map((role) => roleLabels[role] ?? role).join(' · ')}</p>
                        </div>
                    </div>

                    <span className="mx-1 hidden h-6 w-px bg-gold/30 sm:mx-2 sm:block" aria-hidden="true" />

                    <NavLink
                        to="/mot-de-passe"
                        title="Changer mon mot de passe"
                        aria-label="Changer mon mot de passe"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-full text-stone-300 transition-colors hover:bg-white/10 hover:text-gold-light focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                    >
                        <KeyIcon />
                    </NavLink>
                    <button
                        type="button"
                        onClick={handleLogout}
                        title="Se déconnecter"
                        aria-label="Se déconnecter"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-full text-stone-300 transition-colors hover:bg-red-500/15 hover:text-red-400 focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:outline-none"
                    >
                        <LogoutIcon />
                    </button>
                </header>
                <main className="mx-auto max-w-6xl p-4 md:p-6 print:max-w-none print:p-0">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
