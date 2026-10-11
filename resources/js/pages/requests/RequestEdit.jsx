import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import api, { errorMessage } from '../../services/api';
import ExitPassForm from '../exits/ExitPassForm';
import LeaveRequestForm from '../leaves/LeaveRequestForm';
import PermissionRequestForm from '../permissions/PermissionRequestForm';

const forms = { conge: LeaveRequestForm, permission: PermissionRequestForm, sortie: ExitPassForm };

/**
 * Ouvre le formulaire correspondant au type de la demande à modifier.
 */
export default function RequestEdit() {
    const { id } = useParams();
    const [type, setType] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get(`/requests/${id}`)
            .then(({ data }) => setType(data.data.request_type))
            .catch((err) => setError(errorMessage(err)));
    }, [id]);

    if (error) {
        return <Alert>{error}</Alert>;
    }
    if (!type) {
        return <p className="text-sm text-slate-500">Chargement…</p>;
    }

    const Form = forms[type];

    return <Form />;
}
