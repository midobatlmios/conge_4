import { translated } from '@/lib/i18n';

export type Option = { value: string; label: string };

export type Objective = {
    id: number;
    year: number;
    title: string;
    target: string | null;
    weight: number;
    rating: string | null;
    rating_label: string | null;
    rating_comment: string | null;
};

export type ReviewStatus = 'self' | 'manager' | 'comment' | 'hr' | 'closed';

/** A review as Review::present() sends it to this viewer. */
export type Review = {
    id: number;
    year: number;
    due_on: string | null;
    status: ReviewStatus;
    status_label: string;
    employee: { id: number; name: string; job_title: string | null };
    manager: string | null;
    can_act: boolean;
    is_employee: boolean;
    is_reviewer: boolean;
    self: { rating: string | null; assessment: string | null; wishes: string | null } | null;
    evaluation: {
        rating: string | null;
        rating_label: string | null;
        feedback: string | null;
        strengths: string | null;
        improvements: string | null;
        training_needs: string | null;
        interview_on: string | null;
        reviewed_by: string | null;
    } | null;
    employee_comment: string | null;
    recommendation: {
        kind: 'promotion' | 'raise' | null;
        kind_label: string | null;
        job_title: string | null;
        salary: number | null;
        reason: string | null;
        status: 'pending' | 'applied' | 'declined' | null;
    } | null;
    closed_at: string | null;
};

export type ReviewSummary = {
    id: number;
    year: number;
    due_on: string | null;
    status: ReviewStatus;
    status_label: string;
    name: string;
    job_title: string | null;
    can_act: boolean;
    rating_label: string | null;
};

export const STEPS: ReviewStatus[] = ['self', 'manager', 'comment', 'hr', 'closed'];

export const STEP_LABELS = translated({
    self: 'Auto-évaluation',
    manager: 'Évaluation du responsable',
    comment: 'Commentaire de l’employé',
    hr: 'Clôture RH',
    closed: 'Clôturé',
});

export const RATING_CLASS: Record<string, string> = {
    insuffisant: 'border-red-500 text-red-700 dark:text-red-400',
    a_ameliorer: 'border-orange-500 text-orange-700 dark:text-orange-400',
    atteint: 'border-green-600 text-green-700 dark:text-green-400',
    depasse: 'border-blue-500 text-blue-700 dark:text-blue-400',
};
