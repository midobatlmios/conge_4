/** Today's clocking, as AttendanceController::todayState() sends it. */
export type TodayState = {
    tracking: boolean;
    working_day: boolean;
    clock_in: string | null;
    clock_out: string | null;
};

export type Schedule = {
    start: string;
    lunch_start: string;
    lunch_end: string;
    end: string;
    tolerance: number;
    since: string | null;
    /** Expected minutes on a full working day. */
    expected: number;
};

export type DayStatus = 'present' | 'in_progress' | 'missing_out' | 'absent' | 'not_yet' | 'leave' | 'rest' | 'future' | 'not_tracked';

/** One day, as AttendanceCalculator::day() sends it (times in minutes). */
export type AttendanceDay = {
    date: string;
    working_day: boolean;
    leave: string | null;
    leave_part: 'full' | 'morning_off' | 'afternoon_off' | null;
    clock_in: string | null;
    clock_out: string | null;
    source: 'app' | 'correction' | null;
    expected: number;
    worked: number;
    late: number;
    overtime: { '25': number; '50': number; '100': number };
    missing_out: boolean;
    status: DayStatus;
};

export type Totals = {
    expected: number;
    worked: number;
    present_days: number;
    absent_days: number;
    late_days: number;
    late_minutes: number;
    missing_out: number;
    overtime_25: number;
    overtime_50: number;
    overtime_100: number;
};

export type Correction = {
    id: number;
    user_id: number;
    name: string | null;
    date: string;
    clock_in: string;
    clock_out: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'refused';
    status_label: string;
    refusal_reason: string | null;
    decided_by: string | null;
    can_decide: boolean;
};

export const STATUS_LABELS: Record<DayStatus, string> = {
    present: 'Présent',
    in_progress: 'En poste',
    missing_out: 'Départ non pointé',
    absent: 'Absence non justifiée',
    not_yet: 'Pas encore pointé',
    leave: 'Congé',
    rest: 'Repos',
    future: '',
    not_tracked: '',
};

export const STATUS_CLASSES: Partial<Record<DayStatus, string>> = {
    absent: 'text-red-700 dark:text-red-400',
    missing_out: 'text-orange-700 dark:text-orange-400',
    in_progress: 'text-green-700 dark:text-green-400',
};

/** 450 → "7 h 30", 0 → "—". */
export function hours(minutes: number): string {
    if (!minutes) return '—';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m ? `${h} h ${String(m).padStart(2, '0')}` : `${h} h`;
}

export const overtimeTotal = (t: Pick<Totals, 'overtime_25' | 'overtime_50' | 'overtime_100'>) => t.overtime_25 + t.overtime_50 + t.overtime_100;

export const shortDay = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
