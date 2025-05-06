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
        };
    };
    flash?: {
        message?: string;
        type?: 'success' | 'error' | 'warning' | 'info';
    };
    sidebarOpen?: boolean;
    [key: string]: unknown;
} 