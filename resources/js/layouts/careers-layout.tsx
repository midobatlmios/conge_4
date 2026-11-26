import AppLogoIcon from '@/components/app-logo-icon';
import AppToaster from '@/components/app-toaster';
import { useBrand } from '@/lib/brand';
import { applyLocale } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { type CSSProperties, type PropsWithChildren } from 'react';

/** The public careers pages: the company's name and color, no menu, no sign-in. */
export default function CareersLayout({ children }: PropsWithChildren) {
    const brand = useBrand();
    // Public pages stay in French.
    applyLocale('fr');

    return (
        <div className="bg-background min-h-svh">
            <div className="h-1" style={{ backgroundColor: brand.color }} aria-hidden="true" />
            <header className="border-b">
                <div className="mx-auto flex max-w-4xl items-center gap-3 px-4 py-4">
                    <Link href="/carrieres" className="flex items-center gap-3">
                        <AppLogoIcon className="h-10 w-10 text-sm" />
                        <span className="font-semibold text-(--brand) dark:text-white" style={{ '--brand': brand.color } as CSSProperties}>
                            {brand.name ? `${brand.name} · Carrières` : 'Carrières'}
                        </span>
                    </Link>
                </div>
            </header>
            <main className="mx-auto max-w-4xl px-4 py-10">{children}</main>
            <AppToaster />
        </div>
    );
}
