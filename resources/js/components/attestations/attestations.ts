/** An attestation as Attestation::present() sends it. */
export type Attestation = {
    id: number;
    user_id: number;
    name: string | null;
    type: 'travail' | 'salaire';
    type_label: string;
    purpose: string | null;
    status: 'demandee' | 'delivree' | 'refusee';
    status_label: string;
    number: string | null;
    requested_at: string;
    /** Asked by the employee (otherwise created by HR). */
    requested_by_employee: boolean;
    issued_at: string | null;
    handled_by: string | null;
    refusal_reason: string | null;
    /** HR list only: what blocks issuing it. */
    missing?: string[];
};

export type TypeOption = { value: Attestation['type']; label: string };

export const STATUS_CLASS: Record<Attestation['status'], string> = {
    demandee: 'border-orange-500 text-orange-700 dark:text-orange-400',
    delivree: 'border-green-600 text-green-700 dark:text-green-400',
    refusee: 'border-red-500 text-red-700 dark:text-red-400',
};

export const pdfUrl = (attestation: Attestation) => `/attestations/${attestation.id}/pdf`;
