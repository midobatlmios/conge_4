import { intlLocale, translated } from '@/lib/i18n';
/** Personal and administrative data of an employee (HR only; the employee reads their own). */
export type EmployeeProfile = {
    gender: 'homme' | 'femme' | null;
    birth_date: string | null; // YYYY-MM-DD
    birth_place: string | null;
    cin: string | null;
    cnss_number: string | null;
    marital_status: 'celibataire' | 'marie' | 'divorce' | 'veuf' | null;
    children_count: number | null;
    address: string | null;
    city: string | null;
    personal_phone: string | null;
    personal_email: string | null;
    bank_name: string | null;
    rib: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relation: string | null;
    emergency_contact_phone: string | null;
    updated_at?: string;
    editor?: { id: number; name: string } | null;
};

export type ProfileField = Exclude<keyof EmployeeProfile, 'updated_at' | 'editor'>;

export const GENDER_LABELS: Record<NonNullable<EmployeeProfile['gender']>, string> = translated({
    homme: 'Homme',
    femme: 'Femme',
});

export const MARITAL_STATUS_LABELS: Record<NonNullable<EmployeeProfile['marital_status']>, string> = translated({
    celibataire: 'Célibataire',
    marie: 'Marié(e)',
    divorce: 'Divorcé(e)',
    veuf: 'Veuf / veuve',
});

/** Suggestions only: HR can type any bank. */
export const BANKS = [
    'Al Barid Bank',
    'Attijariwafa bank',
    'Bank of Africa',
    'Banque Populaire',
    'BMCI',
    'CFG Bank',
    'CIH Bank',
    'Crédit Agricole du Maroc',
    'Crédit du Maroc',
    'Société Générale Maroc',
];

/** "123 456 1234567890123456 78": bank, city, account, key. */
export function formatRib(rib: string | null): string {
    if (!rib || rib.length !== 24) return rib ?? '';
    return `${rib.slice(0, 3)} ${rib.slice(3, 6)} ${rib.slice(6, 22)} ${rib.slice(22)}`;
}

export function formatDate(date: string | null | undefined): string {
    if (!date) return '';
    return new Date(date.length === 10 ? `${date}T00:00:00` : date).toLocaleDateString(intlLocale());
}

/** Age in full years on today's date. */
export function age(birthDate: string | null): number | null {
    if (!birthDate) return null;
    const birth = new Date(`${birthDate}T00:00:00`);
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) years--;
    return years;
}

/** The sections and labels shown on both the HR form and the employee's read-only view. */
export const SECTIONS: { title: string; fields: { field: ProfileField; label: string }[] }[] = [
    {
        title: 'Identité',
        fields: [
            { field: 'gender', label: 'Genre' },
            { field: 'birth_date', label: 'Date de naissance' },
            { field: 'birth_place', label: 'Lieu de naissance' },
            { field: 'cin', label: 'CIN' },
            { field: 'cnss_number', label: 'N° CNSS' },
        ],
    },
    {
        title: 'Situation familiale',
        fields: [
            { field: 'marital_status', label: 'Situation familiale' },
            { field: 'children_count', label: "Nombre d'enfants à charge" },
        ],
    },
    {
        title: 'Coordonnées',
        fields: [
            { field: 'address', label: 'Adresse' },
            { field: 'city', label: 'Ville' },
            { field: 'personal_phone', label: 'Téléphone personnel' },
            { field: 'personal_email', label: 'E-mail personnel' },
        ],
    },
    {
        title: 'Banque',
        fields: [
            { field: 'bank_name', label: 'Banque' },
            { field: 'rib', label: 'RIB (24 chiffres)' },
        ],
    },
    {
        title: "Contact d'urgence",
        fields: [
            { field: 'emergency_contact_name', label: 'Nom' },
            { field: 'emergency_contact_relation', label: 'Lien de parenté' },
            { field: 'emergency_contact_phone', label: 'Téléphone' },
        ],
    },
];

/** How a stored value reads on screen. */
export function displayValue(profile: EmployeeProfile, field: ProfileField): string {
    const value = profile[field];
    if (value === null || value === '') return '';
    switch (field) {
        case 'gender':
            return GENDER_LABELS[profile.gender!];
        case 'marital_status':
            return MARITAL_STATUS_LABELS[profile.marital_status!];
        case 'birth_date': {
            const years = age(profile.birth_date);
            return `${formatDate(profile.birth_date)}${years !== null ? ` (${years} ans)` : ''}`;
        }
        case 'rib':
            return formatRib(profile.rib);
        default:
            return String(value);
    }
}
