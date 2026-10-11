import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../services/api';
import { formatDateTime } from '../services/labels';

/**
 * Alertes internes (EF-60) : compteur actualisé chaque minute, liste déroulante.
 */
export default function NotificationBell() {
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unread, setUnread] = useState(0);
    const panel = useRef(null);

    const load = useCallback(() => {
        api.get('/me/notifications')
            .then(({ data }) => {
                setNotifications(data.data);
                setUnread(data.unread_count);
            })
            .catch(() => {});
    }, []);

    useEffect(() => {
        load();
        const timer = setInterval(load, 60000);

        return () => clearInterval(timer);
    }, [load]);

    useEffect(() => {
        function close(event) {
            if (panel.current && !panel.current.contains(event.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    async function openNotification(notification) {
        if (!notification.read_at) {
            await api.patch(`/me/notifications/${notification.id}/read`).catch(() => {});
            load();
        }
        setOpen(false);
        if (notification.link) {
            navigate(notification.link);
        }
    }

    async function readAll() {
        await api.post('/me/notifications/read-all').catch(() => {});
        load();
    }

    return (
        <div className="relative" ref={panel}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="relative rounded-md px-2 py-1 text-sm text-stone-300 hover:bg-white/10 hover:text-gold-light"
                aria-label={`Notifications${unread ? ` (${unread} non lues)` : ''}`}
                aria-expanded={open}
            >
                Notifications
                {unread > 0 && <span className="ml-1 rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white">{unread}</span>}
            </button>
            {open && (
                <div className="absolute right-0 z-20 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                        <p className="text-sm font-semibold">Notifications</p>
                        {unread > 0 && (
                            <button type="button" onClick={readAll} className="text-xs text-sky-700 hover:underline">
                                Tout marquer comme lu
                            </button>
                        )}
                    </div>
                    <ul className="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        {notifications.length === 0 && <li className="px-4 py-6 text-center text-sm text-slate-500">Aucune notification.</li>}
                        {notifications.map((notification) => (
                            <li key={notification.id}>
                                <button type="button" onClick={() => openNotification(notification)} className={`w-full px-4 py-3 text-left text-sm hover:bg-slate-50 ${notification.read_at ? 'text-slate-500' : ''}`}>
                                    <p className={notification.read_at ? '' : 'font-semibold'}>{notification.title}</p>
                                    <p className="mt-0.5">{notification.message}</p>
                                    <p className="mt-1 text-xs text-slate-400">{formatDateTime(notification.created_at)}</p>
                                </button>
                            </li>
                        ))}
                    </ul>
                    <div className="border-t border-slate-100 px-4 py-2 text-right">
                        <Link to="/preferences" onClick={() => setOpen(false)} className="text-xs text-sky-700 hover:underline">
                            Préférences de notification
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
