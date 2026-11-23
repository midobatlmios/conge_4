import type { LucideIcon } from 'lucide-react';
import type { TypeConge } from '@/lib/leave';

export interface PageProps {
    auth: {
        user: User;
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface SharedData extends PageProps {
    name: string;
    quote?: { message: string; author: string };
    flash?: {
        message?: string;
        type?: 'success' | 'error' | 'warning' | 'info';
    };
    sidebarOpen?: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    avatar?: string;
    is_admin?: boolean;
    remaining_days?: number;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface Demande {
    id: number;
    user_id: number;
    user: {
        name: string;
        email: string;
        role: string;
        remaining_days?: number;
    };
    date_demande: string;
    date_debut: string;
    date_fin: string;
    nbr_jours: number;
    annee: number;
    type_conge: TypeConge;
    etat: "en attente" | "acceptée" | "refusée";
    valide_par: number | null;
    comment: string | null;
} 