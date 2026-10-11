import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

/**
 * Protège les routes : connexion obligatoire, mot de passe provisoire changé,
 * et permission éventuelle. Le serveur reste l'autorité ; ce garde évite seulement
 * d'afficher des écrans inutilisables.
 */
export default function RequireAuth({ permission }) {
    const { user, loading, can } = useAuth();
    const location = useLocation();

    if (loading) {
        return <p className="p-6 text-sm text-slate-500">Chargement…</p>;
    }

    if (!user) {
        return <Navigate to="/connexion" replace state={{ from: location.pathname }} />;
    }

    if (user.must_change_password && location.pathname !== '/mot-de-passe') {
        return <Navigate to="/mot-de-passe" replace />;
    }

    if (permission && !can(permission)) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
}
