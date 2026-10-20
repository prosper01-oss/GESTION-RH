import { useEffect, useRef, useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import api, { errorMessage } from '../../services/api';

/**
 * Gestion de la signature électronique du compte connecté.
 * L'image est insérée automatiquement sur les documents approuvés.
 */
export default function MySignature() {
    const [hasSignature, setHasSignature] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const fileRef = useRef(null);

    useEffect(() => {
        api.get('/me/signature').then(({ data }) => {
            setHasSignature(data.has_signature);
            if (data.has_signature) {
                setPreviewUrl(`/api/v1/me/signature/image?t=${Date.now()}`);
            }
        });
    }, []);

    async function handleUpload(event) {
        const file = event.target.files[0];
        if (!file) {
            return;
        }
        setBusy(true);
        setError('');
        setMessage('');
        const body = new FormData();
        body.append('signature', file);
        try {
            await api.post('/me/signature', body);
            setHasSignature(true);
            setPreviewUrl(`/api/v1/me/signature/image?t=${Date.now()}`);
            setMessage('Signature enregistrée. Elle apparaîtra automatiquement sur les documents approuvés.');
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    }

    async function handleDelete() {
        setBusy(true);
        setError('');
        setMessage('');
        try {
            await api.delete('/me/signature');
            setHasSignature(false);
            setPreviewUrl(null);
            setMessage('Signature supprimée.');
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-xl font-semibold">Ma signature électronique</h1>
            <p className="text-sm text-slate-600">
                Uploadez une image de votre signature (PNG ou JPG, fond blanc de préférence, max 2 Mo).
                Elle sera insérée automatiquement à l'emplacement prévu sur les documents que vous approuvez.
            </p>

            <Alert type="success">{message}</Alert>
            <Alert>{error}</Alert>

            {hasSignature && previewUrl && (
                <div className="flex flex-col gap-3">
                    <p className="text-sm font-medium text-slate-700">Signature actuelle</p>
                    <div className="inline-block rounded-lg border border-slate-200 bg-white p-4">
                        <img
                            src={previewUrl}
                            alt="Ma signature"
                            className="max-h-24 max-w-xs object-contain"
                        />
                    </div>
                </div>
            )}

            <div className="flex flex-wrap gap-3">
                <label className={`cursor-pointer rounded-md px-4 py-2 text-sm font-medium ring-1 transition ${busy ? 'cursor-not-allowed opacity-50' : ''} bg-ink text-gold-light ring-gold/60 hover:bg-black hover:text-white`}>
                    {hasSignature ? 'Remplacer la signature' : 'Uploader une signature'}
                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/png,image/jpeg"
                        className="sr-only"
                        onChange={handleUpload}
                        disabled={busy}
                    />
                </label>
                {hasSignature && (
                    <Button variant="danger" onClick={handleDelete} disabled={busy}>
                        Supprimer
                    </Button>
                )}
            </div>

            <p className="text-xs text-slate-500">
                Conseil : préparez une image avec fond transparent ou blanc, dimensions recommandées 400 × 150 px minimum.
            </p>
        </div>
    );
}
