import {
    BANKS,
    EmployeeProfile,
    formatDate,
    GENDER_LABELS,
    MARITAL_STATUS_LABELS,
    ProfileField,
    SECTIONS,
} from '@/components/employees/personal-data';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type FormData = Record<ProfileField, string>;

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

/** HR form for an employee's personal data. Every field is optional. */
export default function PersonalDataForm({ employeeId, profile }: { employeeId: number; profile: EmployeeProfile | null }) {
    const initial = Object.fromEntries(
        SECTIONS.flatMap((s) => s.fields).map(({ field }) => [field, profile?.[field] == null ? '' : String(profile[field])]),
    ) as FormData;
    const { data, setData, put, processing, errors, clearErrors } = useForm<FormData>(initial);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(`/admin/users/${employeeId}/personal-data`, { preserveScroll: true });
    };

    const change = (field: ProfileField, value: string) => {
        setData(field, value);
        clearErrors(field);
    };

    const input = (field: ProfileField) => {
        const common = { id: field, value: data[field], className: errors[field] ? 'border-red-500' : '' };
        switch (field) {
            case 'gender':
            case 'marital_status': {
                const labels = field === 'gender' ? GENDER_LABELS : MARITAL_STATUS_LABELS;
                return (
                    <select {...common} className={`${selectClass} ${common.className}`} onChange={(e) => change(field, e.target.value)}>
                        <option value="">—</option>
                        {Object.entries(labels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                );
            }
            case 'birth_date':
                return <Input {...common} type="date" onChange={(e) => change(field, e.target.value)} />;
            case 'children_count':
                return <Input {...common} type="number" min={0} max={20} onChange={(e) => change(field, e.target.value)} />;
            case 'personal_email':
                return <Input {...common} type="email" onChange={(e) => change(field, e.target.value)} />;
            case 'personal_phone':
            case 'emergency_contact_phone':
                return <Input {...common} type="tel" placeholder="06 12 34 56 78" onChange={(e) => change(field, e.target.value)} />;
            case 'bank_name':
                return <Input {...common} list="banks" onChange={(e) => change(field, e.target.value)} />;
            case 'cin':
                return <Input {...common} placeholder="AB123456" autoComplete="off" onChange={(e) => change(field, e.target.value)} />;
            case 'cnss_number':
                return (
                    <Input
                        {...common}
                        inputMode="numeric"
                        placeholder="9 chiffres"
                        autoComplete="off"
                        onChange={(e) => change(field, e.target.value)}
                    />
                );
            case 'rib':
                return (
                    <Input
                        {...common}
                        inputMode="numeric"
                        placeholder="000 000 0000000000000000 00"
                        autoComplete="off"
                        className={`font-mono ${common.className}`}
                        onChange={(e) => change(field, e.target.value)}
                    />
                );
            default:
                return <Input {...common} onChange={(e) => change(field, e.target.value)} />;
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <datalist id="banks">
                {BANKS.map((bank) => (
                    <option key={bank} value={bank} />
                ))}
            </datalist>

            {SECTIONS.map((section) => (
                <fieldset key={section.title} className="space-y-3">
                    <legend className="text-muted-foreground mb-2 text-sm font-semibold tracking-wide uppercase">{section.title}</legend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {section.fields.map(({ field, label }) => (
                            <div key={field} className={`space-y-1 ${field === 'address' || field === 'rib' ? 'sm:col-span-2' : ''}`}>
                                <Label htmlFor={field}>{label}</Label>
                                {input(field)}
                                {errors[field] && <p className="text-sm text-red-700 dark:text-red-400">{errors[field]}</p>}
                            </div>
                        ))}
                    </div>
                </fieldset>
            ))}

            <div className="flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-muted-foreground text-xs">
                    {profile?.updated_at
                        ? `Dernière modification le ${formatDate(profile.updated_at)}${profile.editor ? ` par ${profile.editor.name}` : ''}.`
                        : 'Aucune donnée enregistrée pour le moment.'}{' '}
                    CIN, N° CNSS, RIB, adresse et téléphones sont chiffrés en base.
                </p>
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Enregistrement…' : 'Enregistrer'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
