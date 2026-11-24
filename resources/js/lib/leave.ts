import { usePage } from '@inertiajs/react';

export type TypeConge = 'annuel' | 'mariage' | 'naissance' | 'deces' | 'sans_solde' | 'recuperation';

export const TYPE_CONGE_LABELS: Record<TypeConge, string> = {
    annuel: 'Congé annuel',
    mariage: 'Congé de Mariage',
    naissance: 'Congé de Naissance',
    deces: 'Congé de Décès',
    sans_solde: 'Congé sans solde',
    recuperation: 'Récupération',
};

/** Leave rules shared by the server from config/conge.php (holidays from the admin-managed holidays table). */
export interface LeaveRules {
    maxDaysPerYear: number;
    countedTypes: TypeConge[];
    fixedDeductions: Partial<Record<TypeConge, number>>;
    remainingDays: number | null; // signed-in user's remaining annual days this year
    typeLimits: Partial<Record<TypeConge, number>>;
    workingDays: number[]; // ISO weekdays, 1 = Monday … 7 = Sunday
    holidays: string[]; // "MM-DD" (every year) or "YYYY-MM-DD"
}

export function useLeaveRules(): LeaveRules {
    return usePage<{ leaveRules: LeaveRules }>().props.leaveRules;
}

const pad = (n: number) => String(n).padStart(2, '0');

/** Formats a date as YYYY-MM-DD in the browser's local time zone (toISOString() would use UTC). */
export function toISODate(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Parses YYYY-MM-DD as a local date (new Date("YYYY-MM-DD") would parse it as UTC). */
export function parseISODate(value: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
}

export function todayISO(): string {
    return toISODate(new Date());
}

export function isWorkingDay(date: Date, rules: LeaveRules): boolean {
    const isoWeekday = date.getDay() === 0 ? 7 : date.getDay();
    const iso = toISODate(date);
    return rules.workingDays.includes(isoWeekday) && !rules.holidays.includes(iso.slice(5)) && !rules.holidays.includes(iso);
}

/** Working days between two YYYY-MM-DD dates, both included. Mirrors Demande::countWorkingDays(). */
export function countWorkingDays(start: string, end: string, rules: LeaveRules): number {
    const day = parseISODate(start);
    const last = parseISODate(end);
    if (!day || !last) return 0;

    let count = 0;
    for (; day <= last; day.setDate(day.getDate() + 1)) {
        if (isWorkingDay(day, rules)) count++;
    }
    return count;
}

/** Days a request would take from the allowance. Mirrors Demande::allowanceDays() for a single year. */
export function allowanceDays(type: TypeConge, workingDays: number, rules: LeaveRules): number {
    if (rules.countedTypes.includes(type)) return workingDays;
    const deduction = rules.fixedDeductions[type];
    return deduction ? Math.min(deduction, workingDays) : 0;
}

/** First working day on or after the given date. */
export function nextWorkingDay(from: Date, rules: LeaveRules): Date {
    const day = new Date(from);
    while (!isWorkingDay(day, rules)) day.setDate(day.getDate() + 1);
    return day;
}

/** End date (YYYY-MM-DD) of a leave that starts on `start` and lasts `days` working days. */
export function endAfterWorkingDays(start: string, days: number, rules: LeaveRules): string {
    const first = parseISODate(start);
    if (!first) return '';

    const day = nextWorkingDay(first, rules);
    for (let counted = 1; counted < days; ) {
        day.setDate(day.getDate() + 1);
        if (isWorkingDay(day, rules)) counted++;
    }
    return toISODate(day);
}
