import { useEffect } from 'react';

const sizes = {
    md: 'max-w-lg',
    lg: 'max-w-3xl',
    xl: 'max-w-5xl',
};

/**
 * Fenêtre modale : fermeture par Échap ou clic sur le fond. Le pied reçoit les boutons d'action.
 */
export default function Modal({ open, title, subtitle, onClose, footer, size = 'md', children }) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onKeyDown = (event) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 print:hidden" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div role="dialog" aria-modal="true" aria-label={title} className={`flex max-h-[90vh] w-full flex-col rounded-lg border border-slate-200 bg-white text-slate-900 shadow-xl ${sizes[size]}`}>
                <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 className="text-base font-semibold">{title}</h2>
                        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fermer" className="text-xl leading-none text-slate-400 hover:text-slate-700">
                        ×
                    </button>
                </div>
                <div className="overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">{footer}</div>}
            </div>
        </div>
    );
}
