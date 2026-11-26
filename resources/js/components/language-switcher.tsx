import { LOCALES, Locale, useLocale, useT } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';

/** FR / العربية / EN. Saved on the account (or the session on the sign-in pages). */
export default function LanguageSwitcher({ className = '' }: { className?: string }) {
    const locale = useLocale();
    const t = useT();

    return (
        <label className={`inline-flex items-center gap-2 text-sm ${className}`}>
            <Languages className="text-muted-foreground h-4 w-4" aria-hidden />
            <span className="sr-only">{t('Langue')}</span>
            <select
                className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                value={locale}
                onChange={(e) => router.put('/locale', { locale: e.target.value as Locale }, { preserveScroll: true, preserveState: false })}
            >
                {LOCALES.map((l) => (
                    <option key={l.value} value={l.value} lang={l.value}>
                        {l.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
