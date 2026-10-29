import { Link } from 'react-router-dom';
import { PencilIcon, TrashIcon, UndoIcon, XIcon } from './Icons';

const tones = {
    edit: 'text-slate-500 hover:bg-sky-50 hover:text-sky-700 focus-visible:ring-sky-300',
    danger: 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-300',
};

/**
 * Bouton réduit à une icône, avec infobulle et libellé pour les lecteurs d'écran.
 * `bordered` en fait un bouton de page (cadre visible) ; `to` le transforme en lien.
 */
function IconButton({ icon: IconComponent, label, tone, to, bordered = false, className = '', ...props }) {
    const classes = `inline-flex shrink-0 items-center justify-center rounded-md transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 ${
        bordered ? 'h-10 w-10 border border-slate-300 bg-white' : 'h-8 w-8'
    } ${tones[tone]} ${className}`;
    const content = <IconComponent className="h-[18px] w-[18px]" />;

    return to ? (
        <Link to={to} title={label} aria-label={label} className={classes} {...props}>
            {content}
        </Link>
    ) : (
        <button type="button" title={label} aria-label={label} className={classes} {...props}>
            {content}
        </button>
    );
}

export const EditButton = ({ label = 'Modifier', ...props }) => <IconButton icon={PencilIcon} label={label} tone="edit" {...props} />;

export const DeleteButton = ({ label = 'Supprimer', ...props }) => <IconButton icon={TrashIcon} label={label} tone="danger" {...props} />;

/** Retrait d'une ligne (croix) : panier de la caisse. */
export const RemoveButton = ({ label = 'Retirer', ...props }) => <IconButton icon={XIcon} label={label} tone="danger" {...props} />;

/** Annulation avec restitution (flèche de retour) : lignes de sortie de stock. */
export const UndoButton = ({ label = 'Annuler', ...props }) => <IconButton icon={UndoIcon} label={label} tone="danger" {...props} />;
