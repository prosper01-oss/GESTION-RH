import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api, { csrfCookie } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        try {
            const { data } = await api.get('/me');
            setUser(data.data);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        refresh();
    }, [refresh]);

    // Session expirée ou compte désactivé : retour à l'écran de connexion.
    useEffect(() => {
        const interceptor = api.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response?.status === 401) {
                    setUser(null);
                }

                return Promise.reject(error);
            },
        );

        return () => api.interceptors.response.eject(interceptor);
    }, []);

    const login = useCallback(async (email, password) => {
        await csrfCookie();
        const { data } = await api.post('/login', { email, password });
        setUser(data.data);

        return data.data;
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/logout');
        } finally {
            setUser(null);
        }
    }, []);

    const value = useMemo(
        () => ({
            user,
            loading,
            login,
            logout,
            refresh,
            can: (permission) => user?.permissions?.includes(permission) ?? false,
        }),
        [user, loading, login, logout, refresh],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
