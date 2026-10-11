import { useEffect, useState } from 'react';
import api from '../../services/api';

export default function Dashboard() {
    const [apiStatus, setApiStatus] = useState('vérification…');

    useEffect(() => {
        api.get('/health')
            .then(({ data }) => setApiStatus(data.status === 'ok' ? 'connectée' : 'réponse inattendue'))
            .catch(() => setApiStatus('injoignable'));
    }, []);

    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6">
            <h2 className="mb-2 text-base font-semibold">Tableau de bord</h2>
            <p className="text-sm text-slate-600">API : {apiStatus}</p>
        </section>
    );
}
