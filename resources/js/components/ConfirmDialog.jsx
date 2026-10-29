import Button from './Button';
import Modal from './Modal';

/**
 * Demande de confirmation avant une action destructive ou irréversible.
 */
export default function ConfirmDialog({ open, title = 'Confirmation', message, confirmLabel = 'Confirmer', variant = 'danger', busy = false, onConfirm, onCancel }) {
    return (
        <Modal
            open={open}
            title={title}
            onClose={onCancel}
            footer={
                <>
                    <Button variant="secondary" onClick={onCancel}>
                        Annuler
                    </Button>
                    <Button variant={variant} onClick={onConfirm} disabled={busy}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <p className="text-sm text-slate-700">{message}</p>
        </Modal>
    );
}
