import { Contract, ContractOptions, suggestedTrialEnd } from '@/components/employees/contracts';
import { formatDate } from '@/components/employees/personal-data';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

interface ContractFormProps {
    employeeId: number;
    /** Editing this contract; a new one otherwise. */
    contract: Contract | null;
    options: ContractOptions;
    onDone: () => void;
}

/** HR form for a contract. Suggests the legal trial period from the type and category. */
export default function ContractForm({ employeeId, contract, options, onDone }: ContractFormProps) {
    const form = useForm({
        type: (contract?.type ?? 'cdi') as string,
        category: (contract?.category ?? '') as string,
        job_title: contract?.job_title ?? '',
        gross_salary: contract?.gross_salary == null ? '' : String(contract.gross_salary),
        start_date: contract?.start_date ?? '',
        end_date: contract?.end_date ?? '',
        trial_end: contract?.trial_end ?? '',
        trial_renewed: contract?.trial_renewed ?? false,
        notes: contract?.notes ?? '',
    });
    const { data, setData, errors, processing } = form;
    const suggestion = suggestedTrialEnd(data.type, data.category, data.start_date, data.end_date);
    const needsEnd = data.type !== 'cdi';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (contract) form.put(`/admin/contracts/${contract.id}`, options);
        else form.post(`/admin/users/${employeeId}/contracts`, options);
    };

    const error = (field: keyof typeof errors) => errors[field] && <p className="text-sm text-red-700 dark:text-red-400">{errors[field]}</p>;

    return (
        <form onSubmit={submit} className="space-y-4 rounded-md border border-dashed p-4">
            <h3 className="text-sm font-semibold">{contract ? t('Modifier le contrat') : t('Nouveau contrat')}</h3>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="contract-type">{t('Type de contrat')}</Label>
                    <select id="contract-type" className={selectClass} value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {options.types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    {error('type')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-category">{t('Catégorie')}</Label>
                    <select
                        id="contract-category"
                        className={selectClass}
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                    >
                        <option value="">—</option>
                        {options.categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </select>
                    {error('category')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-start">{t('Date de début')}</Label>
                    <Input id="contract-start" type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
                    {error('start_date')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-end">
                        {t('Date de fin')}
                        {needsEnd ? '' : t(' (si le CDI a pris fin)')}
                    </Label>
                    <Input
                        id="contract-end"
                        type="date"
                        value={data.end_date}
                        onChange={(e) => setData('end_date', e.target.value)}
                        required={needsEnd}
                    />
                    {error('end_date')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-trial">{t("Fin de période d'essai")}</Label>
                    <Input id="contract-trial" type="date" value={data.trial_end} onChange={(e) => setData('trial_end', e.target.value)} />
                    {suggestion && suggestion !== data.trial_end && (
                        <button
                            type="button"
                            className="text-start text-xs text-blue-700 underline dark:text-blue-400"
                            onClick={() => setData('trial_end', suggestion)}
                        >
                            {t("Durée légale : jusqu'au")} {formatDate(suggestion)} {t('— appliquer')}
                        </button>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.trial_renewed} onChange={(e) => setData('trial_renewed', e.target.checked)} />
                        {t("Période d'essai renouvelée (une fois)")}
                    </label>
                    {error('trial_end')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-job">{t('Poste (facultatif)')}</Label>
                    <Input id="contract-job" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                    {error('job_title')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="contract-salary">{t('Salaire brut mensuel (DH)')}</Label>
                    <Input
                        id="contract-salary"
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.gross_salary}
                        onChange={(e) => setData('gross_salary', e.target.value)}
                    />
                    <p className="text-muted-foreground text-xs">{t("Chiffré en base ; utilisé pour l'attestation de salaire.")}</p>
                    {error('gross_salary')}
                </div>
                <div className="space-y-1 sm:col-span-2">
                    <Label htmlFor="contract-notes">{t('Remarques (facultatif)')}</Label>
                    <Textarea id="contract-notes" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    {error('notes')}
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone} disabled={processing}>
                    {t('Annuler')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? t('Enregistrement…') : t('Enregistrer')}
                </Button>
            </div>
        </form>
    );
}
