import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { intlLocale, t } from '@/lib/i18n';
import { todayISO } from '@/lib/leave';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export type Delegation = { id: number; delegate: string | null; delegate_id: number; starts_on: string; ends_on: string; current: boolean };

interface DelegationPanelProps {
    /** Delegations given and not over yet. */
    delegations: Delegation[];
    /** People who can be chosen (active employees except the manager). */
    options: { id: number; name: string }[];
    /** Where the form posts: the manager's own, or HR's for a manager. */
    url: string;
    /** Pre-fills the dates with the manager's next leave. */
    nextLeave: { start: string; end: string } | null;
    /** "vos" (the manager) or the manager's name (HR). */
    whose?: string;
}

const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale());
const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

/** Hand a manager's approvals to someone else for a period, and end it early. */
export default function DelegationPanel({ delegations, options, url, nextLeave, whose }: DelegationPanelProps) {
    const today = todayISO();
    const form = useForm({
        delegate_id: '',
        starts_on: nextLeave && nextLeave.start > today ? nextLeave.start : today,
        ends_on: nextLeave?.end ?? today,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset('delegate_id') });
    };

    const end = async (delegation: Delegation) => {
        const ok = await confirmAction({
            title: t('Terminer cette délégation ?'),
            description: t(":name ne validera plus les demandes à partir d'aujourd'hui.", { name: delegation.delegate }),
            confirmLabel: t('Terminer'),
        });
        if (ok) router.delete(`/delegations/${delegation.id}`, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <div className="space-y-4">
            {delegations.length > 0 && (
                <ul className="divide-y rounded-md border">
                    {delegations.map((delegation) => (
                        <li key={delegation.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                            <span>
                                <span className="font-medium">{delegation.delegate}</span> {t('valide du')} {frDate(delegation.starts_on)} {t('au')}{' '}
                                {frDate(delegation.ends_on)}{' '}
                                {delegation.current ? (
                                    <Badge className="ms-1">{t('En cours')}</Badge>
                                ) : (
                                    <Badge variant="outline" className="ms-1">
                                        {t('Prévue')}
                                    </Badge>
                                )}
                            </span>
                            <Button variant="outline" size="sm" onClick={() => end(delegation)}>
                                {t('Terminer')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="space-y-3 rounded-md border border-dashed p-4">
                <p className="text-muted-foreground text-sm">
                    {t('Pendant la période, le remplaçant reçoit les demandes et les rappels et peut les valider ou les refuser')}
                    {whose ? t(' à la place de :name', { name: whose }) : ''} {t('; la décision reste aussi possible pour le responsable.')}
                </p>
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="space-y-1">
                        <Label htmlFor="delegate_id">{t('Remplaçant')}</Label>
                        <select
                            id="delegate_id"
                            className={selectClass}
                            value={form.data.delegate_id}
                            onChange={(e) => form.setData('delegate_id', e.target.value)}
                            required
                        >
                            <option value="">{t('Choisir…')}</option>
                            {options.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.delegate_id} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="starts_on">{t('Du')}</Label>
                        <Input
                            id="starts_on"
                            type="date"
                            min={today}
                            value={form.data.starts_on}
                            onChange={(e) => form.setData('starts_on', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.starts_on} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="ends_on">{t('Au')}</Label>
                        <Input
                            id="ends_on"
                            type="date"
                            min={form.data.starts_on || today}
                            value={form.data.ends_on}
                            onChange={(e) => form.setData('ends_on', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.ends_on} />
                    </div>
                </div>
                {nextLeave && (
                    <p className="text-muted-foreground text-xs">
                        {t('Dates proposées : prochain congé du')} {frDate(nextLeave.start)} {t('au')} {frDate(nextLeave.end)}.
                    </p>
                )}
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Déléguer')}
                    </Button>
                </div>
            </form>
        </div>
    );
}
