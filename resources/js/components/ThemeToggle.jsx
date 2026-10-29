import { useTheme } from '../hooks/useTheme';
import { MonitorIcon, MoonIcon, SunIcon } from './Icons';

const labels = { light: 'Clair', dark: 'Sombre', system: 'Automatique' };
const icons = { light: SunIcon, dark: MoonIcon, system: MonitorIcon };

/**
 * Bouton-icône qui passe de Auto à Clair puis à Sombre : soleil, lune ou écran selon le mode choisi.
 */
export default function ThemeToggle({ className = '' }) {
    const { choice, cycle } = useTheme();
    const next = { system: 'clair', light: 'sombre', dark: 'automatique' }[choice];
    const CurrentIcon = icons[choice];

    return (
        <button
            type="button"
            onClick={cycle}
            title={`Thème : ${labels[choice]} — passer en mode ${next}`}
            aria-label={`Thème ${labels[choice]}, passer en mode ${next}`}
            className={`inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none ${className}`}
        >
            <CurrentIcon />
        </button>
    );
}
