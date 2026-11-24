import Notifications from '@/components/Notifications';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type BreadcrumbItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ChevronDown, ChevronRight, FileText, LayoutDashboard, Menu, Settings, X } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

interface AppLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function AppLayout({ children, breadcrumbs }: AppLayoutProps) {
    // @ts-expect-error: InertiaJS props typing is not strict, so we access user from props.auth.user
    const user = usePage().props.auth?.user || { name: 'Utilisateur' };
    const initials = user.name
        ? user.name
              .split(' ')
              .map((n: string) => n[0])
              .join('')
              .toUpperCase()
        : 'U';
    const [logoutDialogOpen, setLogoutDialogOpen] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const handleLogout = () => {
        setLogoutDialogOpen(false);
        router.post('/logout');
    };

    return (
        <div className="bg-background flex min-h-screen">
            {/* Mobile menu button */}
            <button className="bg-background fixed top-4 left-4 z-50 rounded-md border p-2 lg:hidden" onClick={() => setSidebarOpen(!sidebarOpen)}>
                {sidebarOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-40 w-64 transform transition-transform duration-200 ease-in-out lg:static lg:inset-auto lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } bg-background flex h-screen flex-col justify-between border-r`}
            >
                <div>
                    {/* Logo/title */}
                    <div className="flex h-20 items-center gap-4 border-b bg-white px-6 text-xl font-extrabold shadow-sm dark:bg-[#181818]">
                        <img src="/images/logo-omnitrade-rvb.svg" alt="Logo" className="h-12 w-12" />
                        <span className="flex flex-col leading-tight">
                            <span className="text-lg tracking-wide text-[#C71618] uppercase">Gestion des</span>
                            <span className="text-lg tracking-wide text-[#C71618] uppercase">congés</span>
                        </span>
                    </div>
                    {/* Main navigation */}
                    <nav className="mt-4 px-4">
                        <div className="text-muted-foreground mb-2 pl-2 text-xs">Menu</div>
                        <ul className="space-y-1">
                            <li>
                                <Link
                                    href="/dashboard"
                                    className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <LayoutDashboard className="h-4 w-4" /> Espace employé
                                </Link>
                            </li>
                            {user.role === 'admin' && (
                                <>
                                    <li>
                                        <Link
                                            href="/demandes"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <FileText className="h-4 w-4" /> Gérer les demandes
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/admin/users"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <Settings className="h-4 w-4" /> Gérer les employés
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/admin/holidays"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <CalendarDays className="h-4 w-4" /> Jours fériés
                                        </Link>
                                    </li>
                                </>
                            )}
                        </ul>
                    </nav>
                </div>
                {/* Bottom section */}
                <div className="mb-4 px-4">
                    {/* User profile dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <div className="hover:bg-accent flex cursor-pointer items-center gap-3 rounded-lg p-2 select-none">
                                <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full text-lg font-bold">{initials}</div>
                                <span className="truncate text-sm font-medium">{user.name || 'Utilisateur'}</span>
                                <ChevronDown className="text-muted-foreground ml-auto h-4 w-4" />
                            </div>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="mb-2 w-56" align="end">
                            <DropdownMenuItem asChild>
                                <Link href="/settings/profile" onClick={() => setSidebarOpen(false)}>
                                    Mon profil
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/password" onClick={() => setSidebarOpen(false)}>
                                    Mot de passe
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/appearance" onClick={() => setSidebarOpen(false)}>
                                    Apparence
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setLogoutDialogOpen(true)}>Déconnexion</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex w-full flex-1 flex-col">
                {/* Header */}
                <header className="flex h-16 items-center justify-between border-b pr-4 pl-16 lg:pl-4">
                    <div className="flex items-center gap-2">
                        {breadcrumbs && breadcrumbs.length > 0 && (
                            <nav className="flex items-center space-x-1 overflow-x-auto text-sm">
                                {breadcrumbs.map((item, index) => (
                                    <div key={item.href} className="flex items-center">
                                        {index > 0 && <ChevronRight className="text-muted-foreground mx-1 h-4 w-4" />}
                                        <Link
                                            href={item.href}
                                            className={`text-sm whitespace-nowrap ${
                                                index === breadcrumbs.length - 1
                                                    ? 'text-foreground font-medium'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            {item.title}
                                        </Link>
                                    </div>
                                ))}
                            </nav>
                        )}
                    </div>
                    <div className="flex items-center gap-4">
                        <Notifications />
                        <Link href="/logout" method="post" className="text-muted-foreground hover:text-foreground text-sm">
                            Déconnexion
                        </Link>
                    </div>
                </header>
                {/* Page content */}
                <div className="flex-1 overflow-auto p-4">{children}</div>
            </main>

            {/* Logout confirmation dialog */}
            <Dialog open={logoutDialogOpen} onOpenChange={setLogoutDialogOpen}>
                <DialogContent>
                    <DialogHeader>Êtes-vous sûr de vouloir vous déconnecter&nbsp;?</DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setLogoutDialogOpen(false)}>
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={handleLogout}>
                            Oui, déconnexion
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
