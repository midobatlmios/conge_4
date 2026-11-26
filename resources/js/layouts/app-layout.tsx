import AppLogoIcon from '@/components/app-logo-icon';
import AppToaster from '@/components/app-toaster';
import ConfirmDialogHost from '@/components/confirm-dialog';
import LanguageSwitcher from '@/components/language-switcher';
import Notifications from '@/components/Notifications';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useBrand } from '@/lib/brand';
import { applyLocale, t, useLocale } from '@/lib/i18n';
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
    Clock,
    ClockAlert,
    FileBadge,
    FileSpreadsheet,
    FileText,
    Gauge,
    Landmark,
    LayoutDashboard,
    Menu,
    Network,
    Settings,
    ShieldCheck,
    UsersRound,
    Wallet,
    X,
} from 'lucide-react';
import { CSSProperties, PropsWithChildren, useState } from 'react';

interface AppLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

/** A sidebar link: highlighted, and announced as the current page, on its page and the pages under it. */
function NavLink({ href, onNavigate, children }: PropsWithChildren<{ href: string; onNavigate: () => void }>) {
    const path = usePage().url.split('?')[0];
    const current = path === href || path.startsWith(`${href}/`);

    return (
        <Link
            href={href}
            aria-current={current ? 'page' : undefined}
            className={`hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none ${
                current ? 'bg-accent text-accent-foreground' : ''
            }`}
            onClick={onNavigate}
        >
            {children}
        </Link>
    );
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
    // Language of this page for t() and the page direction (Arabic: right to left).
    applyLocale(useLocale());
    const brand = useBrand();
    const [logoutDialogOpen, setLogoutDialogOpen] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const handleLogout = () => {
        setLogoutDialogOpen(false);
        router.post('/logout');
    };

    return (
        <div className="bg-background flex min-h-screen">
            {/* First thing keyboard users reach: jump over the menu. */}
            <a
                href="#contenu"
                className="bg-background focus-visible:ring-ring sr-only z-[60] rounded-md border px-3 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:start-3 focus:top-3 focus-visible:ring-2 focus-visible:outline-none"
            >
                {t('Aller au contenu')}
            </a>

            {/* Mobile menu button */}
            <button
                type="button"
                aria-label={sidebarOpen ? t('Fermer le menu') : t('Ouvrir le menu')}
                aria-expanded={sidebarOpen}
                className="bg-background fixed start-4 top-4 z-50 rounded-md border p-2 lg:hidden"
                onClick={() => setSidebarOpen(!sidebarOpen)}
            >
                {sidebarOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 start-0 z-40 w-64 transform transition-transform duration-200 ease-in-out lg:static lg:inset-auto lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } bg-background flex h-screen flex-col justify-between border-e`}
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
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{t('Congés & RH')}</span>
                            </span>
                        ) : (
                            <span
                                className="flex flex-col text-lg leading-tight font-extrabold tracking-wide text-(--brand) uppercase dark:text-white"
                                style={{ '--brand': brand.color } as CSSProperties}
                            >
                                <span>{t('Gestion des')}</span>
                                <span>{t('congés')}</span>
                            </span>
                        )}
                    </div>
                    {/* Main navigation */}
                    <nav className="mt-4 px-4" aria-label={t('Menu principal')}>
                        <div className="text-muted-foreground mb-2 ps-2 text-xs">{t('Menu')}</div>
                        <ul className="space-y-1">
                            <li>
                                <NavLink href="/dashboard" onNavigate={() => setSidebarOpen(false)}>
                                    <LayoutDashboard className="h-4 w-4" /> {t('Espace employé')}
                                </NavLink>
                            </li>
                            <li>
                                <NavLink href="/calendrier" onNavigate={() => setSidebarOpen(false)}>
                                    <CalendarRange className="h-4 w-4" /> {t('Calendrier')}
                                </NavLink>
                            </li>
                            <li>
                                <NavLink href="/organigramme" onNavigate={() => setSidebarOpen(false)}>
                                    <Network className="h-4 w-4" /> {t('Organigramme')}
                                </NavLink>
                            </li>
                            <li>
                                <NavLink href="/pointage" onNavigate={() => setSidebarOpen(false)}>
                                    <Clock className="h-4 w-4" /> {t('Mes pointages')}
                                </NavLink>
                            </li>
                            {user.is_manager && (
                                <li>
                                    <NavLink href="/equipe" onNavigate={() => setSidebarOpen(false)}>
                                        <UsersRound className="h-4 w-4" /> {t('Mon équipe')}
                                    </NavLink>
                                </li>
                            )}
                            {(user.is_manager || can.manage_hr) && (
                                <li>
                                    <NavLink href="/pointage/corrections" onNavigate={() => setSidebarOpen(false)}>
                                        <ClockAlert className="h-4 w-4" /> {t('Corrections de pointage')}
                                    </NavLink>
                                </li>
                            )}
                            {can.view_hr && (
                                <>
                                    <li>
                                        <NavLink href="/admin/overview" onNavigate={() => setSidebarOpen(false)}>
                                            <Gauge className="h-4 w-4" /> {t("Vue d'ensemble")}
                                        </NavLink>
                                    </li>
                                    <li>
                                        <NavLink href="/demandes" onNavigate={() => setSidebarOpen(false)}>
                                            <FileText className="h-4 w-4" /> {t('Gérer les demandes')}
                                        </NavLink>
                                    </li>
                                    <li>
                                        <NavLink href="/admin/users" onNavigate={() => setSidebarOpen(false)}>
                                            <Settings className="h-4 w-4" /> {t('Gérer les employés')}
                                        </NavLink>
                                    </li>
                                    {can.manage_hr && (
                                        <li>
                                            <NavLink href="/admin/departments" onNavigate={() => setSidebarOpen(false)}>
                                                <Building2 className="h-4 w-4" /> {t('Départements')}
                                            </NavLink>
                                        </li>
                                    )}
                                    <li>
                                        <NavLink href="/admin/attestations" onNavigate={() => setSidebarOpen(false)}>
                                            <FileBadge className="h-4 w-4" /> {t('Attestations')}
                                        </NavLink>
                                    </li>
                                    {can.manage_hr && (
                                        <li>
                                            <NavLink href="/admin/company" onNavigate={() => setSidebarOpen(false)}>
                                                <Landmark className="h-4 w-4" /> {t('Entreprise')}
                                            </NavLink>
                                        </li>
                                    )}
                                    <li>
                                        <NavLink href="/admin/indicators" onNavigate={() => setSidebarOpen(false)}>
                                            <ChartColumn className="h-4 w-4" /> {t('Indicateurs RH')}
                                        </NavLink>
                                    </li>
                                    <li>
                                        <NavLink href="/admin/attendance" onNavigate={() => setSidebarOpen(false)}>
                                            <Clock className="h-4 w-4" /> {t('Pointage')}
                                        </NavLink>
                                    </li>
                                    <li>
                                        <NavLink href="/admin/payslips" onNavigate={() => setSidebarOpen(false)}>
                                            <Wallet className="h-4 w-4" /> {t('Bulletins de paie')}
                                        </NavLink>
                                    </li>
                                    <li>
                                        <NavLink href="/admin/payroll" onNavigate={() => setSidebarOpen(false)}>
                                            <FileSpreadsheet className="h-4 w-4" /> {t('Export paie')}
                                        </NavLink>
                                    </li>
                                    {can.manage_hr && (
                                        <li>
                                            <NavLink href="/admin/holidays" onNavigate={() => setSidebarOpen(false)}>
                                                <CalendarDays className="h-4 w-4" /> {t('Jours fériés')}
                                            </NavLink>
                                        </li>
                                    )}
                                    {can.manage_roles && (
                                        <li>
                                            <NavLink href="/admin/privacy" onNavigate={() => setSidebarOpen(false)}>
                                                <ShieldCheck className="h-4 w-4" /> {t('Protection des données')}
                                            </NavLink>
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
                            {/* A real button: reachable with the keyboard and announced as a menu. */}
                            <button
                                type="button"
                                aria-label={t('Menu du compte de :name', { name: user.name || t('Utilisateur') })}
                                className="hover:bg-accent focus-visible:ring-ring flex w-full cursor-pointer items-center gap-3 rounded-lg p-2 text-start select-none focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <span
                                    aria-hidden="true"
                                    className="bg-muted flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-lg font-bold"
                                >
                                    {initials}
                                </span>
                                <span className="truncate text-sm font-medium">{user.name || t('Utilisateur')}</span>
                                <ChevronDown aria-hidden="true" className="text-muted-foreground ms-auto h-4 w-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="mb-2 w-56" align="end">
                            <DropdownMenuItem asChild>
                                <Link href="/settings/profile" onClick={() => setSidebarOpen(false)}>
                                    {t('Mon profil')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/personal-data" onClick={() => setSidebarOpen(false)}>
                                    {t('Données personnelles')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/attestations" onClick={() => setSidebarOpen(false)}>
                                    {t('Mes attestations')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/protection-des-donnees" onClick={() => setSidebarOpen(false)}>
                                    {t('Protection des données')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/password" onClick={() => setSidebarOpen(false)}>
                                    {t('Mot de passe')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/settings/appearance" onClick={() => setSidebarOpen(false)}>
                                    {t('Apparence')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setLogoutDialogOpen(true)}>{t('Déconnexion')}</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex w-full min-w-0 flex-1 flex-col">
                {/* Header */}
                <header className="flex h-16 items-center justify-between border-b ps-16 pe-4 lg:ps-4">
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
                                            {t(item.title)}
                                        </Link>
                                    </div>
                                ))}
                            </nav>
                        )}
                    </div>
                    <div className="flex items-center gap-4">
                        <LanguageSwitcher className="hidden sm:inline-flex" />
                        <Notifications />
                        <Link href="/logout" method="post" className="text-muted-foreground hover:text-foreground text-sm">
                            {t('Déconnexion')}
                        </Link>
                    </div>
                </header>
                {/* Page content */}
                <div id="contenu" tabIndex={-1} className="flex-1 overflow-auto p-4 focus:outline-none">
                    {children}
                </div>
            </main>

            {/* Logout confirmation dialog */}
            {/* App-wide toasts and the confirm/prompt dialog. */}
            <AppToaster />
            <ConfirmDialogHost />

            <Dialog open={logoutDialogOpen} onOpenChange={setLogoutDialogOpen}>
                <DialogContent>
                    <DialogHeader>{t('Êtes-vous sûr de vouloir vous déconnecter ?')}</DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setLogoutDialogOpen(false)}>
                            {t('Annuler')}
                        </Button>
                        <Button variant="destructive" onClick={handleLogout}>
                            {t('Oui, déconnexion')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
