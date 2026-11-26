import { formatDate } from '@/components/employees/personal-data';
import { t, translated } from '@/lib/i18n';

/** A contract as EmploymentContractController::present() sends it. */
export type Contract = {
    id: number;
    type: 'cdi' | 'cdd' | 'anapec' | 'stage';
    type_label: string;
    category: 'cadre' | 'employe' | 'ouvrier' | null;
    category_label: string | null;
    job_title: string | null;
    /** Monthly gross salary in DH (HR, Paie and the employee only). */
    gross_salary: number | null;
    start_date: string;
    end_date: string | null;
    trial_end: string | null;
    trial_renewed: boolean;
    in_trial: boolean;
    notes: string | null;
    status: 'upcoming' | 'active' | 'ended';
};

/** Something HR must act on soon (EmploymentContractController::alerts()). */
export type ContractAlert = {
    user_id: number;
    name: string;
    contract: string;
    kind: 'contract_end' | 'ended' | 'trial_end';
    date: string;
};

export type Option = { value: string; label: string };
export type ContractOptions = { types: Option[]; categories: Option[] };

export const STATUS_LABELS: Record<Contract['status'], string> = translated({
    upcoming: 'À venir',
    active: 'En cours',
    ended: 'Terminé',
});

export function alertText(alert: ContractAlert): string {
    switch (alert.kind) {
        case 'ended':
            return t(':contract terminé le :date, sans nouveau contrat', { contract: alert.contract, date: formatDate(alert.date) });
        case 'contract_end':
            return t(':contract se termine le :date', { contract: alert.contract, date: formatDate(alert.date) });
        case 'trial_end':
            return t("Fin de période d'essai le :date", { date: formatDate(alert.date) });
    }
}

const addDays = (iso: string, days: number) => {
    const date = new Date(`${iso}T00:00:00`);
    date.setDate(date.getDate() + days);
    return date;
};

const addMonths = (iso: string, months: number) => {
    const date = new Date(`${iso}T00:00:00`);
    const whole = Math.floor(months);
    date.setMonth(date.getMonth() + whole);
    if (months !== whole) date.setDate(date.getDate() + 15); // "un mois et demi"
    return date;
};

const toISO = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * Trial period the Labour Code allows, as a suggestion HR can change.
 * - CDI (art. 14): cadres 3 months, employés 1.5 months, ouvriers 15 days.
 * - CDD (art. 13): 1 day per week of contract, at most 2 weeks for a CDD up
 *   to 6 months; 1 month beyond.
 * The period ends the day before the computed date. ANAPEC and stages: none.
 */
export function suggestedTrialEnd(type: string, category: string, start: string, end: string): string | null {
    if (!start) return null;
    let until: Date | null = null;

    if (type === 'cdi') {
        if (category === 'cadre') until = addMonths(start, 3);
        else if (category === 'employe') until = addMonths(start, 1.5);
        else if (category === 'ouvrier') until = addDays(start, 15);
    } else if (type === 'cdd' && end) {
        const lengthDays = (new Date(`${end}T00:00:00`).getTime() - new Date(`${start}T00:00:00`).getTime()) / 86_400_000 + 1;
        until = lengthDays > 183 ? addMonths(start, 1) : addDays(start, Math.min(Math.floor(lengthDays / 7), 14));
        if (lengthDays < 7) return null;
    }

    if (!until) return null;
    until.setDate(until.getDate() - 1);
    return toISO(until);
}
