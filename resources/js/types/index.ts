export interface PageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            role: string;
        };
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface SharedData extends PageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            role: string;
            is_admin?: boolean;
            remaining_days?: number;
            email_verified_at?: string | null;
        };
    };
    flash?: {
        message?: string;
        type?: 'success' | 'error' | 'warning' | 'info';
    };
    sidebarOpen?: boolean;
    [key: string]: unknown;
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
    type_conge: "mariage" | "naissance" | "deces" | "sans_solde" | "recuperation";
    etat: "en attente" | "acceptée" | "refusée";
    valide_par: number | null;
    comment: string | null;
} 