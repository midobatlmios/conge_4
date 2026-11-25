import { usePage } from '@inertiajs/react';

export type TypeConge = 'annuel' | 'mariage' | 'naissance' | 'deces' | 'sans_solde' | 'recuperation' | 'maladie';

export const TYPE_CONGE_LABELS: Record<TypeConge, string> = {
    annuel: 'Congé annuel',
    mariage: 'Congé de Mariage',
    naissance: 'Congé de Naissance',
    deces: 'Congé de Décès',
    sans_solde: 'Congé sans solde',
    recuperation: 'Récupération',
    maladie: 'Congé maladie',
};

/** Mirrors App\Enums\LeaveStatus (the values the database stores). */
export type LeaveStatus = 'en attente' | 'validée manager' | 'acceptée' | 'refusée' | 'annulation demandée' | 'annulée';

export const STATUS_LABELS: Record<LeaveStatus, string> = {
    'en attente': 'En attente',
    'validée manager': 'Validée par le responsable',
    acceptée: 'Acceptée',
    refusée: 'Refusée',
    'annulation demandée': 'Annulation demandée',
    annulée: 'Annulée',
};

export const STATUS_COLORS: Record<LeaveStatus, string> = {
    'en attente': 'text-yellow-500',
    'validée manager': 'text-blue-500',
    acceptée: 'text-green-500',
    refusée: 'text-red-500',
    'annulation demandée': 'text-orange-500',
    annulée: 'text-gray-500',
};

/** The signed-in user's annual leave for the current year. Computed by LeaveCalculator::balance(). */
export interface LeaveBalance {
    annualDays: number; // earned over the whole year, seniority bonus included
    earnedDays: number; // earned so far (1/12 of annualDays per full month worked)
    usedDays: number; // taken or planned in accepted requests
    remainingDays: number; // earned + carried days still usable today - usedDays
    carriedDays: number; // unused days brought from last year
    carryUsedDays: number; // of those, used by leave days up to carryExpires
    carryLeftDays: number; // of those, still unused (0 once expired)
    carryExpires: string; // YYYY-MM-DD, last day carried days can be used
    hireDate: string | null; // YYYY-MM-DD
    eligibleFrom: string | null; // YYYY-MM-DD, 6 months after hiring
}

/** Leave rules shared by the server from config/conge.php (holidays from the admin-managed holidays table). */
export interface LeaveRules {
    maxDaysPerYear: number;
    seniorityBonusDays: number;
    seniorityYears: number;
    countedTypes: TypeConge[];
    fixedDeductions: Partial<Record<TypeConge, number>>;
    balance: LeaveBalance | null;
    typeLimits: Partial<Record<TypeConge, number>>;
    halfDayTypes: TypeConge[]; // types that can start in the afternoon / end at noon
    documentTypes: TypeConge[]; // types that need a supporting document before HR accepts
    sickLeaveBackdateDays: number; // sick leave can be declared this many days after it started
    documentMimes: string[]; // accepted file extensions
    documentMaxKb: number;
    workingDays: number[]; // ISO weekdays, 1 = Monday … 7 = Sunday
    holidays: string[]; // "MM-DD" (every year) or "YYYY-MM-DD"
}

/**
 * Days usable by a leave starting on a YYYY-MM-DD date this year: earned by then,
 * plus carried-over days (all of them before they expire, only those used after).
 */
export function availableBy(date: string, balance: LeaveBalance): number {
    const carried = date <= balance.carryExpires ? balance.carriedDays : balance.carryUsedDays;
    return earnedBy(date, balance) + carried;
}

/** Formats a number of days the French way: 16,5. */
export const formatDays = (days: number) => days.toLocaleString('fr-FR', { maximumFractionDigits: 1 });

/**
 * Days earned this year by a YYYY-MM-DD date in the current year. Mirrors
 * LeaveCalculator::earnedDays(): one twelfth of the year's days per full month
 * worked, counted from 1 January (or the hire date), rounded down to the half day.
 */
export function earnedBy(date: string, balance: LeaveBalance): number {
    const year = new Date().getFullYear();
    const hire = balance.hireDate ? parseISODate(balance.hireDate) : null;
    const yearStart = new Date(year, 0, 1);
    const from = hire && hire > yearStart ? hire : yearStart;
    const asOf = parseISODate(date);
    if (!asOf) return 0;

    // The day after, so a month ending on that date counts as completed.
    const until = new Date(Math.min(asOf.getTime(), new Date(year, 11, 31).getTime()));
    until.setDate(until.getDate() + 1);

    let months = (until.getFullYear() - from.getFullYear()) * 12 + until.getMonth() - from.getMonth();
    if (until.getDate() < from.getDate()) months--;
    if (months <= 0) return 0;

    return Math.min(balance.annualDays, Math.floor(((months * balance.annualDays) / 12) * 2) / 2);
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

/**
 * Days of leave between two YYYY-MM-DD dates, minus half a day when it starts in the
 * afternoon or ends at noon (on a working day). Mirrors LeaveCalculator::leaveDays().
 */
export function leaveDays(start: string, end: string, startHalf: boolean, endHalf: boolean, rules: LeaveRules): number {
    let days = countWorkingDays(start, end, rules);
    const first = parseISODate(start);
    const last = parseISODate(end);
    if (startHalf && first && isWorkingDay(first, rules)) days -= 0.5;
    if (endHalf && last && isWorkingDay(last, rules)) days -= 0.5;
    return Math.max(0, days);
}

/** " (après-midi)" / " (midi)" after a start or end date that is a half day. */
export const halfDayNote = (half: boolean, side: 'start' | 'end') => (half ? (side === 'start' ? ' (après-midi)' : ' (midi)') : '');

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
