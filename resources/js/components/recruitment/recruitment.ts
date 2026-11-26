import { intlLocale } from '@/lib/i18n';
export type Option = { value: string; label: string };
export type Person = { id: number; name: string };

/** An offer as shown on the public careers page. */
export type OfferCard = {
    title: string;
    slug: string;
    department: string | null;
    location: string | null;
    contract: string;
    published_at: string | null;
    closes_on: string | null;
};

export const STAGE_CLASS: Record<string, string> = {
    new: 'border-blue-500 text-blue-700 dark:text-blue-400',
    screening: 'border-slate-500',
    interview: 'border-violet-500 text-violet-700 dark:text-violet-400',
    offer: 'border-orange-500 text-orange-700 dark:text-orange-400',
    hired: 'border-green-600 text-green-700 dark:text-green-400',
    rejected: 'border-red-500 text-red-700 dark:text-red-400',
};

export const frDate = (iso: string) => new Date(`${iso.slice(0, 10)}T00:00:00`).toLocaleDateString(intlLocale());
