import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Toaster, toast } from 'sonner';

export { toast };

/** onError for router actions that have no form to show their errors: one toast with every message. */
export const toastErrors = (errors: Record<string, string>) => {
    const message = Object.values(errors).join(' ');
    if (message) toast.error(message);
};

type Flash = { success?: string | null; error?: string | null };

/** Follows the app's light/dark mode (the `dark` class on <html>). */
function useIsDark(): boolean {
    const [dark, setDark] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => setDark(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    return dark;
}

/**
 * Toasts for the whole app: shows the server's flash message ("Demande ajoutée
 * avec succès", "Contrat supprimé"…) after each visit, and anything a page
 * sends with toast.success() / toast.error().
 */
export default function AppToaster() {
    const { flash } = usePage<{ flash?: Flash }>().props;
    const dark = useIsDark();

    // Each visit brings a new props object: a message shows once per response.
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    return <Toaster position="top-right" richColors closeButton theme={dark ? 'dark' : 'light'} toastOptions={{ duration: 5000 }} />;
}
