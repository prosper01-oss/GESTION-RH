import { useTheme } from '../hooks/useTheme';

const labels = { light: 'Clair', dark: 'Sombre', system: 'Auto' };
const icons = { light: '☀', dark: '☾', system: '◐' };

/**
 * Bouton qui passe de Auto à Clair puis à Sombre.
 */
export default function ThemeToggle({ className = '' }) {
    const { choice, cycle } = useTheme();
    const next = { system: 'clair', light: 'sombre', dark: 'automatique' }[choice];

    return (
        <button
            type="button"
            onClick={cycle}
            title={`Thème : ${labels[choice]}. Cliquer pour passer en mode ${next}.`}
            aria-label={`Thème ${labels[choice]}, passer en mode ${next}`}
            className={`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm ${className}`}
        >
            <span aria-hidden="true">{icons[choice]}</span>
            {labels[choice]}
        </button>
    );
}
