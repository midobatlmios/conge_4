import { PropsWithChildren, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
  LayoutDashboard,
  FileText,
  Settings,
  ChevronDown,
  ChevronRight,
  Menu,
  X
} from 'lucide-react';
import Notifications from '@/components/Notifications';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';

interface AppLayoutProps extends PropsWithChildren {
  breadcrumbs?: BreadcrumbItem[];
}

export default function AppLayout({ children, breadcrumbs }: AppLayoutProps) {
  // @ts-expect-error: InertiaJS props typing is not strict, so we access user from props.auth.user
  const user = usePage().props.auth?.user || { name: 'Utilisateur' };
  const initials = user.name ? user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase() : 'U';
  const [logoutDialogOpen, setLogoutDialogOpen] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const handleLogout = () => {
    setLogoutDialogOpen(false);
    router.post('/logout');
  };

  return (
    <div className="flex min-h-screen bg-background">
      {/* Mobile menu button */}
      <button
        className="fixed top-4 left-4 z-50 p-2 rounded-md lg:hidden bg-background border"
        onClick={() => setSidebarOpen(!sidebarOpen)}
      >
        {sidebarOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
      </button>

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-40 w-64 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto ${
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      } bg-background border-r flex flex-col h-screen justify-between`}>
        <div>
          {/* Logo/title */}
          <div className="flex items-center h-20 px-6 font-extrabold text-xl gap-4 border-b bg-white dark:bg-[#181818] shadow-sm">
            <img src="/images/logo-omnitrade-rvb.svg" alt="Logo" className="w-12 h-12" />
            <span className="flex flex-col leading-tight">
              <span className="tracking-wide uppercase text-[#C71618] text-lg">Gestion des</span>
              <span className="tracking-wide uppercase text-[#C71618] text-lg">congés</span>
            </span>
          </div>
          {/* Main navigation */}
          <nav className="mt-4 px-4">
            <div className="text-xs text-muted-foreground mb-2 pl-2">Platform</div>
            <ul className="space-y-1">
              <li>
                <Link 
                  href="/dashboard" 
                  className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                  onClick={() => setSidebarOpen(false)}
                >
                  <LayoutDashboard className="w-4 h-4" /> Espace employé
                </Link>
              </li>
              {user.role === 'admin' && (
                <>
                  <li>
                    <Link 
                      href="/demandes" 
                      className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                      onClick={() => setSidebarOpen(false)}
                    >
                      <FileText className="w-4 h-4" /> Gérer les demandes
                    </Link>
                  </li>
                  <li>
                    <Link 
                      href="/admin/users" 
                      className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                      onClick={() => setSidebarOpen(false)}
                    >
                      <Settings className="w-4 h-4" /> Gérer les employés
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
              <div className="flex items-center gap-3 p-2 rounded-lg hover:bg-accent cursor-pointer select-none">
                <div className="w-8 h-8 rounded-full bg-muted flex items-center justify-center font-bold text-lg">
                  {initials}
                </div>
                <span className="font-medium text-sm">{user.name?.toLowerCase() || 'user'}</span>
                <ChevronDown className="w-4 h-4 ml-auto text-muted-foreground" />
              </div>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56 mb-2" align="end">
              <DropdownMenuItem asChild>
                <Link href="/settings/profile" onClick={() => setSidebarOpen(false)}>Mon profil</Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild>
                <Link href="/settings/password" onClick={() => setSidebarOpen(false)}>Mot de passe</Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild>
                <Link href="/settings/appearance" onClick={() => setSidebarOpen(false)}>Apparence</Link>
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setLogoutDialogOpen(true)}>
                Déconnexion
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 flex flex-col w-full">
        {/* Header */}
        <header className="flex h-16 items-center justify-between border-b px-4">
          <div className="flex items-center gap-2">
            {breadcrumbs && breadcrumbs.length > 0 && (
              <nav className="flex items-center space-x-1 text-sm overflow-x-auto">
                {breadcrumbs.map((item, index) => (
                  <div key={item.href} className="flex items-center">
                    {index > 0 && <ChevronRight className="w-4 h-4 mx-1 text-muted-foreground" />}
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
            <Link
              href="/logout"
              method="post"
              className="text-sm text-muted-foreground hover:text-foreground"
            >
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
