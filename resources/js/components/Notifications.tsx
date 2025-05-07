import { useState, useEffect } from 'react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

    useEffect(() => {
        // Fetch notifications from the server
        const fetchNotifications = async () => {
            try {
                const response = await fetch('/notifications', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Received notifications data:', data); // Debug log
                
                // Ensure data is an array
                const notificationsArray = Array.isArray(data) ? data : [];
                setNotifications(notificationsArray);
                setUnreadCount(notificationsArray.filter((n: Notification) => !n.read_at).length);
            } catch (error) {
                console.error('Error fetching notifications:', error);
                setNotifications([]); // Set empty array on error
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
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            setNotifications(notifications.map(n => 
                n.id === id ? { ...n, read_at: new Date().toISOString() } : n
            ));
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs text-white">
                            {unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                {!Array.isArray(notifications) || notifications.length === 0 ? (
                    <DropdownMenuItem className="text-sm text-gray-500">
                        Aucune notification
                    </DropdownMenuItem>
                ) : (
                    notifications.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            className={`flex flex-col items-start gap-1 p-3 ${
                                !notification.read_at ? 'bg-gray-50' : ''
                            }`}
                            onClick={() => markAsRead(notification.id)}
                        >
                            <p className="font-medium">{notification.data.message}</p>
                            <p className="text-sm text-gray-500">
                                {new Date(notification.created_at).toLocaleDateString()}
                            </p>
                            {notification.data.comment && (
                                <p className="text-sm text-gray-600 mt-1">
                                    {notification.data.comment}
                                </p>
                            )}
                            <p className="text-sm text-gray-500">
                                Type: {notification.data.type_conge}
                            </p>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
} 