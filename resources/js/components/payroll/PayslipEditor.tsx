import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

export type Hours = { '25': number; '50': number; '100': number };
export type BonusLine = { label: string; amount: number | string; taxable: boolean };
export type AdvanceLine = { label: string; amount: number | string };
export type PayslipInputs = {
    overtime_hours: Hours;
    overtime_from_attendance: Hours;
    unpaid_days: number;
    unpaid_from_leave: number;
    absent_days: number;
    absent_from_attendance: number;
    bonuses: BonusLine[];
    advances: AdvanceLine[];
};

interface PayslipEditorProps {
    payslipId: number;
    name: string;
    inputs: PayslipInputs;
    presets: { label: string; taxable: boolean }[];
    onClose: () => void;
}

const sectionTitle = 'text-sm font-semibold';
const hint = 'text-muted-foreground text-xs';

/**
 * Paie adjusts one payslip before sending the month for validation: unpaid and
 * absent days and overtime (pre-filled from leave and Pointage), bonuses and
 * allowances (taxable or not), advances and loan instalments.
 */
export default function PayslipEditor({ payslipId, name, inputs, presets, onClose }: PayslipEditorProps) {
    const form = useForm({
        unpaid_days: String(inputs.unpaid_days),
        absent_days: String(inputs.absent_days),
        overtime_hours: {
            '25': String(inputs.overtime_hours['25']),
            '50': String(inputs.overtime_hours['50']),
            '100': String(inputs.overtime_hours['100']),
        },
        bonuses: inputs.bonuses.map((b) => ({ ...b, amount: String(b.amount) })),
        advances: inputs.advances.map((a) => ({ ...a, amount: String(a.amount) })),
    });
    const { data, setData, errors, processing } = form;
    const error = (key: string) => (errors as Record<string, string>)[key];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/admin/payslips/${payslipId}/inputs`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('Bulletin de')} {name}
                    </DialogTitle>
                    <DialogDescription>{t('Les jours et heures sont repris des congés et du pointage ; corrigez-les si besoin.')}</DialogDescription>
                </DialogHeader>
                <form id="payslip-editor" onSubmit={submit} className="space-y-6">
                    <section className="space-y-3">
                        <h3 className={sectionTitle}>{t('Jours non payés (salaire ÷ 26 par jour)')}</h3>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="unpaid">{t('Congé sans solde (jours)')}</Label>
                                <Input
                                    id="unpaid"
                                    type="number"
                                    min={0}
                                    step="0.5"
                                    value={data.unpaid_days}
                                    onChange={(e) => setData('unpaid_days', e.target.value)}
                                />
                                <p className={hint}>
                                    {t('Congés acceptés :')} {inputs.unpaid_from_leave} j
                                </p>
                                {error('unpaid_days') && <p className="text-sm text-red-700 dark:text-red-400">{error('unpaid_days')}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="absent">{t('Absences non justifiées (jours)')}</Label>
                                <Input
                                    id="absent"
                                    type="number"
                                    min={0}
                                    step="0.5"
                                    value={data.absent_days}
                                    onChange={(e) => setData('absent_days', e.target.value)}
                                />
                                <p className={hint}>
                                    {t('Pointage :')} {inputs.absent_from_attendance} {t('j. Mettez 0 pour une absence justifiée après coup.')}
                                </p>
                                {error('absent_days') && <p className="text-sm text-red-700 dark:text-red-400">{error('absent_days')}</p>}
                            </div>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h3 className={sectionTitle}>{t('Heures supplémentaires')}</h3>
                        <div className="grid grid-cols-3 gap-3">
                            {(['25', '50', '100'] as const).map((k) => (
                                <div key={k} className="space-y-1">
                                    <Label htmlFor={`ot-${k}`}>+{k} % (h)</Label>
                                    <Input
                                        id={`ot-${k}`}
                                        type="number"
                                        min={0}
                                        step="0.25"
                                        value={data.overtime_hours[k]}
                                        onChange={(e) => setData('overtime_hours', { ...data.overtime_hours, [k]: e.target.value })}
                                    />
                                    <p className={hint}>
                                        {t('Pointage :')} {inputs.overtime_from_attendance[k]} h
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className={sectionTitle}>{t('Primes et indemnités')}</h3>
                            <div className="flex flex-wrap gap-1">
                                {presets.map((preset) => (
                                    <Button
                                        key={preset.label}
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={data.bonuses.length >= 10}
                                        onClick={() =>
                                            setData('bonuses', [...data.bonuses, { label: preset.label, amount: '', taxable: preset.taxable }])
                                        }
                                    >
                                        <Plus className="me-1 h-3 w-3" /> {preset.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                        {data.bonuses.length === 0 && <p className={hint}>{t('Aucune prime ce mois-ci.')}</p>}
                        {data.bonuses.map((line, i) => (
                            <div key={i} className="grid grid-cols-[1fr_8rem_auto_auto] items-center gap-2">
                                <Input
                                    aria-label={t('Libellé')}
                                    value={line.label}
                                    onChange={(e) =>
                                        setData(
                                            'bonuses',
                                            data.bonuses.map((b, j) => (j === i ? { ...b, label: e.target.value } : b)),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('Montant (DH)')}
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    placeholder={t('DH')}
                                    value={line.amount}
                                    onChange={(e) =>
                                        setData(
                                            'bonuses',
                                            data.bonuses.map((b, j) => (j === i ? { ...b, amount: e.target.value } : b)),
                                        )
                                    }
                                />
                                <label className="flex items-center gap-1 text-xs whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        checked={line.taxable}
                                        onChange={(e) =>
                                            setData(
                                                'bonuses',
                                                data.bonuses.map((b, j) => (j === i ? { ...b, taxable: e.target.checked } : b)),
                                            )
                                        }
                                    />
                                    {t('Imposable')}
                                </label>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    aria-label={t('Retirer cette ligne')}
                                    onClick={() =>
                                        setData(
                                            'bonuses',
                                            data.bonuses.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                        <p className={hint}>
                            {t(
                                'Imposable : entre dans le brut (CNSS, AMO, IR). Non imposable : ajouté au net (transport, panier… dans les plafonds légaux ; la part au-dessus du plafond est à saisir comme imposable).',
                            )}
                        </p>
                    </section>

                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h3 className={sectionTitle}>{t('Avances et prêts')}</h3>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={data.advances.length >= 5}
                                onClick={() => setData('advances', [...data.advances, { label: t('Avance sur salaire'), amount: '' }])}
                            >
                                <Plus className="me-1 h-3 w-3" /> {t('Ajouter')}
                            </Button>
                        </div>
                        {data.advances.length === 0 && <p className={hint}>{t('Rien à retenir ce mois-ci.')}</p>}
                        {data.advances.map((line, i) => (
                            <div key={i} className="grid grid-cols-[1fr_8rem_auto] items-center gap-2">
                                <Input
                                    aria-label={t('Libellé')}
                                    value={line.label}
                                    placeholder={t('Avance sur salaire, échéance de prêt…')}
                                    onChange={(e) =>
                                        setData(
                                            'advances',
                                            data.advances.map((a, j) => (j === i ? { ...a, label: e.target.value } : a)),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('Montant retenu (DH)')}
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    placeholder={t('DH')}
                                    value={line.amount}
                                    onChange={(e) =>
                                        setData(
                                            'advances',
                                            data.advances.map((a, j) => (j === i ? { ...a, amount: e.target.value } : a)),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    aria-label={t('Retirer cette ligne')}
                                    onClick={() =>
                                        setData(
                                            'advances',
                                            data.advances.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                        <p className={hint}>{t('Retenu sur le net à payer, après impôt.')}</p>
                        {error('advances') && <p className="text-sm text-red-700 dark:text-red-400">{error('advances')}</p>}
                        {Object.keys(errors).some((k) => k.startsWith('bonuses.') || k.startsWith('advances.')) && (
                            <p className="text-sm text-red-700 dark:text-red-400">{t("Chaque ligne a besoin d'un libellé et d'un montant.")}</p>
                        )}
                    </section>
                </form>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                        {t('Annuler')}
                    </Button>
                    <Button type="submit" form="payslip-editor" disabled={processing}>
                        {processing ? t('Calcul…') : t('Enregistrer et recalculer')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
