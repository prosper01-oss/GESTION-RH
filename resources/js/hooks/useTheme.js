import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'omit-theme';
const media = () => window.matchMedia('(prefers-color-scheme: dark)');

function readChoice() {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    } catch {
        return 'system';
    }
}

function apply(choice) {
    const dark = choice === 'dark' || (choice === 'system' && media().matches);
    document.documentElement.classList.toggle('dark', dark);
}

/**
 * Thème clair, sombre ou automatique (réglage de l'ordinateur), mémorisé sur ce navigateur.
 */
export function useTheme() {
    const [choice, setChoice] = useState(readChoice);

    useEffect(() => {
        apply(choice);
        try {
            localStorage.setItem(STORAGE_KEY, choice);
        } catch {
            // Stockage indisponible (navigation privée) : le choix vaut pour la session.
        }

        if (choice !== 'system') {
            return undefined;
        }
        const query = media();
        const follow = () => apply('system');
        query.addEventListener('change', follow);

        return () => query.removeEventListener('change', follow);
    }, [choice]);

    const cycle = useCallback(() => {
        setChoice((current) => ({ system: 'light', light: 'dark', dark: 'system' })[current] ?? 'system');
    }, []);

    return { choice, cycle };
}
