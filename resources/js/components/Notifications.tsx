import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { t } from '@/lib/i18n';
import { TYPE_CONGE_LABELS, TypeConge } from '@/lib/leave';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Notification {
    id: string;
    data: {
        message: string;
        status: string;
        date_debut: string;
        date_fin: string;
        nbr_jours: number;
        comment: string;
        type_conge: string;
    };
    read_at: string | null;
    created_at: string;
}

export default function Notifications() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    // Until the first answer, show placeholders rather than "Aucune notification".
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        // Fetch notifications from the server
        const fetchNotifications = async () => {
            try {
                const response = await fetch('/notifications', {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Received notifications data:', data); // Debug log

                // Ensure data is an array
                const notificationsArray = Array.isArray(data) ? data : [];
                setNotifications(notificationsArray);
                setLoaded(true);
                setUnreadCount(notificationsArray.filter((n: Notification) => !n.read_at).length);
            } catch (error) {
                console.error('Error fetching notifications:', error);
                setNotifications([]); // Set empty array on error
                setLoaded(true);
                setUnreadCount(0);
            }
        };

        fetchNotifications();
        // Poll for new notifications every 30 seconds
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    const markAsRead = async (id: string) => {
        try {
            const response = await fetch(`/notifications/${id}/mark-as-read`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            setNotifications(notifications.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label={t('Notifications')}>
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -end-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs text-white">
                            {unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                {!loaded ? (
                    <div className="space-y-2 p-3" aria-busy="true" aria-label={t('Chargement des notifications')}>
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                        <Skeleton className="mt-3 h-4 w-2/3" />
                    </div>
                ) : !Array.isArray(notifications) || notifications.length === 0 ? (
                    <DropdownMenuItem className="text-muted-foreground text-sm">{t('Aucune notification')}</DropdownMenuItem>
                ) : (
                    notifications.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            className={`flex flex-col items-start gap-1 p-3 ${!notification.read_at ? 'bg-gray-50' : ''}`}
                            onClick={() => markAsRead(notification.id)}
                        >
                            <p className="font-medium">{notification.data.message}</p>
                            <p className="text-muted-foreground text-sm">{notification.created_at}</p>
                            {notification.data.comment && <p className="text-muted-foreground mt-1 text-sm">{notification.data.comment}</p>}
                            {notification.data.type_conge && (
                                <p className="text-muted-foreground text-sm">
                                    {t('Type :')} {TYPE_CONGE_LABELS[notification.data.type_conge as TypeConge] ?? notification.data.type_conge}
                                </p>
                            )}
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
