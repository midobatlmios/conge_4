import { TypeOption } from '@/components/attestations/attestations';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

interface AttestationFormProps {
    /** Where to post: the employee's request, or HR issuing for an employee. */
    url: string;
    types: TypeOption[];
    submitLabel: string;
    /** HR issuing: an optional net salary for the attestation de salaire. */
    withNetSalary?: boolean;
}

/** Choose the attestation type and an optional purpose (visa, bank…). */
export default function AttestationForm({ url, types, submitLabel, withNetSalary = false }: AttestationFormProps) {
    const form = useForm<{ type: string; purpose: string; net_salary: string }>({ type: types[0]?.value ?? 'travail', purpose: '', net_salary: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform(({ net_salary, ...rest }) => (withNetSalary && net_salary ? { ...rest, net_salary } : rest));
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset('purpose', 'net_salary') });
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form onSubmit={submit} className="space-y-3 rounded-md border border-dashed p-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="attestation-type">{t('Attestation')}</Label>
                    <select
                        id="attestation-type"
                        className={selectClass}
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}
                    >
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    {errors.type && <p className="text-sm text-red-700 dark:text-red-400">{errors.type}</p>}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="attestation-purpose">{t('Motif (facultatif)')}</Label>
                    <Input
                        id="attestation-purpose"
                        value={form.data.purpose}
                        onChange={(e) => form.setData('purpose', e.target.value)}
                        placeholder={t('Ex. : demande de visa, dossier bancaire')}
                    />
                    {errors.purpose && <p className="text-sm text-red-700 dark:text-red-400">{errors.purpose}</p>}
                </div>
                {withNetSalary && form.data.type === 'salaire' && (
                    <div className="space-y-1">
                        <Label htmlFor="attestation-net">{t('Salaire net mensuel (facultatif, DH)')}</Label>
                        <Input
                            id="attestation-net"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.net_salary}
                            onChange={(e) => form.setData('net_salary', e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">{t('Le brut vient du contrat en cours.')}</p>
                        {errors.net_salary && <p className="text-sm text-red-700 dark:text-red-400">{errors.net_salary}</p>}
                    </div>
                )}
            </div>
            {errors.attestation && <p className="text-sm text-red-700 dark:text-red-400">{errors.attestation}</p>}
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('Envoi…') : submitLabel}
                </Button>
            </div>
        </form>
    );
}
