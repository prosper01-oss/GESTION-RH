import { Outlet } from 'react-router-dom';

export default function AuthLayout() {
    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-12">
            <div className="w-full max-w-sm">
                <div className="mb-8 text-center">
                    <p className="text-sm font-semibold tracking-wide text-sky-700">OMIT</p>
                    <h1 className="text-xl font-semibold">Gestion du personnel</h1>
                    <p className="mt-1 text-sm text-slate-500">Organisation Médicale Interentreprise de Toliara</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <Outlet />
                </div>
            </div>
        </div>
    );
}
