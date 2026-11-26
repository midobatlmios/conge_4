/** A before → after pair (the "before" is empty when nothing was set). */
export type Step<T> = { from: T | null; to: T | null };

/** A career change as CareerChange::present() sends it; a field that did not change is null. */
export type CareerChange = {
    id: number;
    effective_date: string;
    kind: 'promotion' | 'augmentation' | 'mutation' | 'reclassement' | 'autre';
    kind_label: string;
    job_title: Step<string> | null;
    department: Step<string> | null;
    category: Step<string> | null;
    /** Monthly gross salary in DH (HR, Paie and the employee only). */
    gross_salary: Step<number> | null;
    notes: string | null;
    /** Who recorded it (HR pages only). */
    author: string | null;
};

export const money = (amount: number) => `${amount.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DH`;

/** "+12,5 %" between two salaries, or null when there was none before. */
export function raisePercent(step: Step<number>): string | null {
    if (!step.from || step.to === null) return null;
    const percent = ((step.to - step.from) / step.from) * 100;
    return `${percent > 0 ? '+' : ''}${percent.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`;
}
