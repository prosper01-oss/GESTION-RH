import { Outlet } from 'react-router-dom';

export default function AuthLayout() {
    return (
        <div className="bg-noir-dore flex min-h-screen items-center justify-center px-4 py-12">
            <div className="w-full max-w-sm">
                <div className="mb-8 text-center">
                    <p className="text-or text-3xl font-semibold tracking-[0.3em]">OMIT</p>
                    <h1 className="mt-2 text-lg font-medium text-stone-100">Gestion du personnel</h1>
                    <p className="mt-1 text-sm text-stone-400">Organisation Médicale Interentreprise de Toliara</p>
                </div>
                <div className="rounded-lg border-t-4 border-gold bg-ivory p-6 shadow-2xl shadow-black/60">
                    <Outlet />
                </div>
            </div>
        </div>
    );
}
