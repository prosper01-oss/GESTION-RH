import { Outlet } from 'react-router-dom';

export default function DashboardLayout() {
    return (
        <div className="min-h-screen">
            <header className="border-b border-slate-200 bg-white px-6 py-4">
                <h1 className="text-lg font-semibold">OMIT — Gestion du personnel</h1>
            </header>
            <main className="p-6">
                <Outlet />
            </main>
        </div>
    );
}
