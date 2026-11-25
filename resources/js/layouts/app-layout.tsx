import AppLogoIcon from '@/components/app-logo-icon';
import Notifications from '@/components/Notifications';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useBrand } from '@/lib/brand';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CalendarRange,
    ChartColumn,
    ChevronDown,
    ChevronRight,
    FileBadge,
    FileSpreadsheet,
    FileText,
    Gauge,
    Landmark,
    LayoutDashboard,
    Menu,
    Network,
    Settings,
    UsersRound,
    X,
} from 'lucide-react';
import { CSSProperties, PropsWithChildren, useState } from 'react';

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
    const can = usePermissions();
    const brand = useBrand();
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
                    <div className="flex h-20 items-center gap-3 border-b bg-white px-6 shadow-sm dark:bg-[#181818]">
                        <AppLogoIcon className="h-11 w-11 text-base" />
                        {brand.name ? (
                            <span className="flex min-w-0 flex-col leading-tight">
                                <span
                                    className="truncate text-base font-extrabold text-(--brand) dark:text-white"
                                    style={{ '--brand': brand.color } as CSSProperties}
                                    title={brand.name}
                                >
                                    {brand.name}
                                </span>
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Congés & RH</span>
                            </span>
                        ) : (
                            <span
                                className="flex flex-col text-lg leading-tight font-extrabold tracking-wide text-(--brand) uppercase"
                                style={{ '--brand': brand.color } as CSSProperties}
                            >
                                <span>Gestion des</span>
                                <span>congés</span>
                            </span>
                        )}
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
                            <li>
                                <Link
                                    href="/calendrier"
                                    className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <CalendarRange className="h-4 w-4" /> Calendrier
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/organigramme"
                                    className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <Network className="h-4 w-4" /> Organigramme
                                </Link>
                            </li>
                            {user.is_manager && (
                                <li>
                                    <Link
                                        href="/equipe"
                                        className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        <UsersRound className="h-4 w-4" /> Mon équipe
                                    </Link>
                                </li>
                            )}
                            {can.view_hr && (
                                <>
                                    <li>
                                        <Link
                                            href="/admin/overview"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <Gauge className="h-4 w-4" /> Vue d'ensemble
                                        </Link>
                                    </li>
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
                                    {can.manage_hr && (
                                        <li>
                                            <Link
                                                href="/admin/departments"
                                                className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                                onClick={() => setSidebarOpen(false)}
                                            >
                                                <Building2 className="h-4 w-4" /> Départements
                                            </Link>
                                        </li>
                                    )}
                                    <li>
                                        <Link
                                            href="/admin/attestations"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <FileBadge className="h-4 w-4" /> Attestations
                                        </Link>
                                    </li>
                                    {can.manage_hr && (
                                        <li>
                                            <Link
                                                href="/admin/company"
                                                className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                                onClick={() => setSidebarOpen(false)}
                                            >
                                                <Landmark className="h-4 w-4" /> Entreprise
                                            </Link>
                                        </li>
                                    )}
                                    <li>
                                        <Link
                                            href="/admin/indicators"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <ChartColumn className="h-4 w-4" /> Indicateurs RH
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/admin/payroll"
                                            className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            <FileSpreadsheet className="h-4 w-4" /> Export paie
                                        </Link>
                                    </li>
                                    {can.manage_hr && (
                                        <li>
                                            <Link
                                                href="/admin/holidays"
                                                className="hover:bg-accent hover:text-accent-foreground flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                                onClick={() => setSidebarOpen(false)}
                                            >
                                                <CalendarDays className="h-4 w-4" /> Jours fériés
                                            </Link>
                                        </li>
                                    )}
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
                                <Link href="/settings/personal-data" onClick={() => setSidebarOpen(false)}>
                                    Données personnelles
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/attestations" onClick={() => setSidebarOpen(false)}>
                                    Mes attestations
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
            <main className="flex w-full min-w-0 flex-1 flex-col">
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
