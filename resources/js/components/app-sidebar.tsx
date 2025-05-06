import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/lib/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, Settings, BookCheck, LayoutGrid } from 'lucide-react';
import AppLogo from './app-logo';
import { usePage } from '@inertiajs/react';

// Define the type for your page props that extends Inertia's PageProps
interface PageProps {
  auth: {
    user?: {
      role?: string;
    };
  };
  [key: string]: unknown; // Using unknown instead of any for better type safety
}

export function AppSidebar() {
  // Type the usePage().props with the PageProps interface
  const { auth } = usePage<PageProps>().props;
  console.log('Auth data:', auth);
  const userRole = auth?.user?.role || 'user';
  console.log('User role:', userRole);

  const mainNavItems: NavItem[] = [
    {
      title: 'Dashboard',
      href: '/dashboard',
      icon: LayoutGrid,
    },
    
  ];

  const adminNavItems: NavItem[] = [
    {
      title: 'Demandes',
      href: '/demandes',
      icon: BookCheck,
    },
    {
      title: 'Manage Users',
      href: '/Admin/Users',
      icon: Settings,
    },
  ];

  let roleBasedNavItems: NavItem[] = [...mainNavItems];
  if (userRole === 'admin') {
    roleBasedNavItems = [...roleBasedNavItems, ...adminNavItems];
  }

  const footerNavItems: NavItem[] = [
    {
      title: 'Repository',
      href: 'https://github.com/laravel/react-starter-kit',
      icon: Folder,
    },
    {
      title: 'Documentation',
      href: 'https://laravel.com/docs/starter-kits',
      icon: BookOpen,
    },
  ];

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/dashboard" prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={roleBasedNavItems} />
      </SidebarContent>
      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}