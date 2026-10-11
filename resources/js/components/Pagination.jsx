import Button from './Button';

/**
 * Pagination d'une collection Laravel (champ meta).
 */
export default function Pagination({ meta, onPageChange }) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 text-sm text-slate-600">
            <span>
                {meta.from}–{meta.to} sur {meta.total}
            </span>
            <div className="flex gap-2">
                <Button variant="secondary" disabled={meta.current_page === 1} onClick={() => onPageChange(meta.current_page - 1)}>
                    Précédent
                </Button>
                <Button variant="secondary" disabled={meta.current_page === meta.last_page} onClick={() => onPageChange(meta.current_page + 1)}>
                    Suivant
                </Button>
            </div>
        </div>
    );
}
