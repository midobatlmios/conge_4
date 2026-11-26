import AppLogoIcon from '@/components/app-logo-icon';
import LanguageSwitcher from '@/components/language-switcher';
import { useBrand } from '@/lib/brand';
import { applyLocale, t, useLocale } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { type CSSProperties, type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

/** Sign-in and password pages, under the company's logo and name. */
export default function AuthSimpleLayout({ children, title, description }: PropsWithChildren<AuthLayoutProps>) {
    const brand = useBrand();
    applyLocale(useLocale());

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            {/* Accent line in the company color. */}
            <div className="fixed inset-x-0 top-0 h-1" style={{ backgroundColor: brand.color }} aria-hidden="true" />
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-3 font-medium">
                            <AppLogoIcon className="h-14 max-w-40 min-w-14 text-xl" />
                            <span
                                className="text-center text-sm font-semibold text-(--brand) dark:text-white"
                                style={{ '--brand': brand.color } as CSSProperties}
                            >
                                {brand.name ? `${brand.name} · ${t('Congés & RH')}` : t('Gestion des congés')}
                            </span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title && t(title)}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description && t(description)}</p>
                        </div>
                    </div>
                    {children}
                    <LanguageSwitcher className="justify-center" />
                </div>
            </div>
        </div>
    );
}
