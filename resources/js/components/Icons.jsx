/**
 * Icônes de l'interface (traits arrondis, héritent de la couleur du texte).
 */
function Icon({ children, className = 'h-5 w-5', ...props }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true" {...props}>
            {children}
        </svg>
    );
}

export const SunIcon = (props) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </Icon>
);

export const MoonIcon = (props) => (
    <Icon {...props}>
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </Icon>
);

export const MonitorIcon = (props) => (
    <Icon {...props}>
        <rect x="2" y="4" width="20" height="13" rx="2" />
        <path d="M8 21h8M12 17v4" />
    </Icon>
);

export const BellIcon = (props) => (
    <Icon {...props}>
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </Icon>
);

export const KeyIcon = (props) => (
    <Icon {...props}>
        <circle cx="7.5" cy="15.5" r="4.5" />
        <path d="M10.7 12.3 21 2M16 7l3 3M14 9l2 2" />
    </Icon>
);

export const LogoutIcon = (props) => (
    <Icon {...props}>
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <path d="m16 17 5-5-5-5M21 12H9" />
    </Icon>
);

export const MenuIcon = (props) => (
    <Icon {...props}>
        <path d="M3 6h18M3 12h18M3 18h18" />
    </Icon>
);

export const PencilIcon = (props) => (
    <Icon {...props}>
        <path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
    </Icon>
);

export const TrashIcon = (props) => (
    <Icon {...props}>
        <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6" />
    </Icon>
);

export const XIcon = (props) => (
    <Icon {...props}>
        <path d="M18 6 6 18M6 6l12 12" />
    </Icon>
);

export const UndoIcon = (props) => (
    <Icon {...props}>
        <path d="M3 7v6h6" />
        <path d="M21 17a9 9 0 0 0-15-6.7L3 13" />
    </Icon>
);
