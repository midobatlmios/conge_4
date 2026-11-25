import { usePage } from '@inertiajs/react';

/** The company's branding, shared on every page (CompanySetting::brand()). */
export type Brand = {
    /** Null until HR fills in the Entreprise page: the app then keeps its own name. */
    name: string | null;
    /** Accent color, #RRGGBB. */
    color: string;
    initials: string;
    /** Logo URL, or null: the initials badge is shown instead. */
    logo: string | null;
};

const FALLBACK: Brand = { name: null, color: '#C71618', initials: '', logo: null };

export function useBrand(): Brand {
    return (usePage().props as { brand?: Brand }).brand ?? FALLBACK;
}
