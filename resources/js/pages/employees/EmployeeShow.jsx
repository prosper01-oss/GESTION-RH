import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Alert from '../../components/Alert';
import BalanceAdjustmentForm from '../../components/BalanceAdjustmentForm';
import Button from '../../components/Button';
import EmployeeDetails from '../../components/EmployeeDetails';
import LeaveBalances from '../../components/LeaveBalances';
import { useAuth } from '../../hooks/useAuth';
import api, { errorMessage, validationErrors } from '../../services/api';

export default function EmployeeShow() {
    const { id } = useParams();
    const { can } = useAuth();
    const [employee, setEmployee] = useState(null);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [busy, setBusy] = useState(false);
    const [photoVersion, setPhotoVersion] = useState('');
    const [balancesVersion, setBalancesVersion] = useState(0);

    useEffect(() => {
        api.get(`/employees/${id}`)
            .then(({ data }) => setEmployee(data.data))
            .catch((err) => setError(errorMessage(err)));
    }, [id]);

    async function toggleStatus() {
        const next = employee.status === 'actif' ? 'inactif' : 'actif';
        const question =
            next === 'inactif'
                ? `Désactiver ${employee.prenom} ${employee.nom} ? Son compte de connexion sera aussi désactivé.`
                : `Réactiver ${employee.prenom} ${employee.nom} ?`;
        if (!window.confirm(question)) {
            return;
        }

        setBusy(true);
        setError('');
        try {
            const { data } = await api.patch(`/employees/${id}/status`, { status: next });
            setEmployee(data.data);
            setSuccess(next === 'actif' ? 'Fiche réactivée.' : 'Fiche désactivée.');
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    async function uploadPhoto(event) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) {
            return;
        }

        const body = new FormData();
        body.append('photo', file);
        setBusy(true);
        setError('');
        try {
            await api.post(`/employees/${id}/photo`, body);
            setEmployee({ ...employee, has_photo: true });
            setPhotoVersion(String(Date.now()));
            setSuccess('Photo enregistrée.');
        } catch (err) {
            setError(validationErrors(err).photo ?? errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    if (!employee) {
        return error ? <Alert>{error}</Alert> : <p className="text-sm text-slate-500">Chargement…</p>;
    }

    return (
        <div className="flex flex-col gap-4">
            <Link to="/personnel" className="text-sm text-sky-700 hover:underline">
                ← Retour à la liste
            </Link>
            <Alert>{error}</Alert>
            <Alert type="success">{success}</Alert>
            <section className="rounded-lg border border-slate-200 bg-white p-6">
                <EmployeeDetails employee={employee} photoVersion={photoVersion} />
            </section>
            {can('employees.manage') && (
                <div className="flex flex-wrap gap-3">
                    <Link to={`/personnel/${id}/modifier`} className="rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">
                        Modifier
                    </Link>
                    <label className={`cursor-pointer rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 ${busy ? 'pointer-events-none opacity-50' : ''}`}>
                        {employee.has_photo ? 'Changer la photo' : 'Ajouter une photo'}
                        <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={uploadPhoto} disabled={busy} />
                    </label>
                    <Button variant={employee.status === 'actif' ? 'danger' : 'secondary'} onClick={toggleStatus} disabled={busy}>
                        {employee.status === 'actif' ? 'Désactiver' : 'Réactiver'}
                    </Button>
                </div>
            )}
            {can('leave_balances.view') && (
                <section className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-6">
                    <h2 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Soldes de congés</h2>
                    <LeaveBalances url={`/leave-balances/${id}`} reloadKey={balancesVersion} />
                    {can('leave_balances.adjust') && <BalanceAdjustmentForm employeeId={id} onSaved={() => setBalancesVersion((version) => version + 1)} />}
                </section>
            )}
        </div>
    );
}
