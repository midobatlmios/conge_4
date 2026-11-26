import ar from '@/lang/ar.json';
import en from '@/lang/en.json';
import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Interface translations. The French text is the key: t('Mes pointages')
 * returns the Arabic or English text from resources/js/lang/*.json, or the
 * French one when there is no translation yet. ":name" placeholders are
 * filled from params: t('Arrivée pointée à :time.', { time }).
 * PDFs and server messages stay in French (later phases).
 */
export type Locale = 'fr' | 'ar' | 'en';

export const LOCALES: { value: Locale; label: string }[] = [
    { value: 'fr', label: 'Français' },
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English' },
];

const dictionaries: Record<Exclude<Locale, 'fr'>, Record<string, string>> = { ar, en };

let current: Locale = 'fr';

type Params = Record<string, string | number | null | undefined>;

export function translate(locale: Locale, text: string, params?: Params): string {
    const found = locale === 'fr' ? text : (dictionaries[locale][text] ?? text);
    return params ? found.replace(/:(\w+)/g, (match, key: string) => (key in params ? String(params[key] ?? '') : match)) : found;
}

/** For code outside components (helpers, dialogs): uses the language of the current page. */
export function t(text: string, params?: Params): string {
    return translate(current, text, params);
}

/**
 * A label map (TYPE_CONGE_LABELS…) whose values are translated when read,
 * so the many places that show them need no change.
 */
export function translated<T extends Record<string, string>>(map: T): T {
    return new Proxy(map, {
        get: (target, key) => {
            const value = Reflect.get(target, key);
            return typeof value === 'string' ? t(value) : value;
        },
    });
}

/** Called on every page: remembers the language and sets the page direction (Arabic: right to left). */
export function applyLocale(locale: Locale | undefined): void {
    current = locale ?? 'fr';
    if (typeof document !== 'undefined') {
        document.documentElement.lang = current;
        document.documentElement.dir = current === 'ar' ? 'rtl' : 'ltr';
    }
}

export function useLocale(): Locale {
    return (usePage().props.locale as Locale | undefined) ?? 'fr';
}

/** The translate function for the current page's language. */
export function useT() {
    const locale = useLocale();
    return useCallback((text: string, params?: Params) => translate(locale, text, params), [locale]);
}

/** The locale for dates and numbers (toLocaleDateString…). */
export function intlLocale(locale: Locale = current): string {
    return { fr: 'fr-FR', ar: 'ar-MA', en: 'en-GB' }[locale];
}
