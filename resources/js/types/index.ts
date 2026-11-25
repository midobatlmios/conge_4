import type { LeaveStatus, TypeConge } from '@/lib/leave';
import type { Permissions } from '@/lib/roles';
import type { LucideIcon } from 'lucide-react';

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
    /** Has employees reporting to them: approves their requests first. */
    is_manager?: boolean;
    /** What the signed-in user may do (shared on every page). */
    can?: Permissions;
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
    start_half: boolean; // starts in the afternoon
    end_half: boolean; // ends at noon
    nbr_jours: number;
    annee: number;
    type_conge: TypeConge;
    etat: LeaveStatus;
    valide_par: number | null;
    comment: string | null;
    manager_comment: string | null; // the manager's reason when refusing
    /** Sick leave: calendar days (how the CNSS counts) and when HR filed the CNSS dossier. */
    calendar_days: number;
    cnss_filed_on: string | null;
    documents?: DemandeDocument[]; // supporting documents (certificates)
}

export interface DemandeDocument {
    id: number;
    original_name: string;
    mime_type: string;
    size: number; // bytes
    created_at: string;
}
