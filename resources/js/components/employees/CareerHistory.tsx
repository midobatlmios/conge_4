import { CareerChange, money, raisePercent, Step } from '@/components/employees/career';
import { formatDate } from '@/components/employees/personal-data';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { Trash2, TrendingUp } from 'lucide-react';

interface CareerHistoryProps {
    changes: CareerChange[];
    /** When the employee was hired: the first step of the history. */
    hireDate?: string | null;
    /** HR only: remove the latest change (an input mistake). */
    onDelete?: (change: CareerChange) => void;
}

function Line({ label, step, format = (value) => String(value) }: { label: string; step: Step<string | number>; format?: (value: never) => string }) {
    const show = (value: string | number | null) => (value === null || value === '' ? 'non renseigné' : format(value as never));
    return (
        <p>
            <span className="text-muted-foreground">{label} : </span>
            {step.from !== null && <span className="text-muted-foreground line-through decoration-1">{show(step.from)}</span>}
            {step.from !== null && ' → '}
            <span className="font-medium">{show(step.to)}</span>
        </p>
    );
}

/** Promotions, raises and transfers, newest first, ending with the hire. */
export default function CareerHistory({ changes, hireDate, onDelete }: CareerHistoryProps) {
    if (changes.length === 0) {
        return (
            <EmptyState
                icon={TrendingUp}
                title={t('Aucune évolution enregistrée')}
                description={`${t('Les promotions, augmentations et mutations apparaîtront ici')}${hireDate ? t(", depuis l'embauche le :date", { date: formatDate(hireDate) }) : ''}.`}
            />
        );
    }

    return (
        <ol className="divide-y rounded-md border">
            {changes.map((change, index) => {
                const raise = change.gross_salary && raisePercent(change.gross_salary);
                return (
                    <li key={change.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">{formatDate(change.effective_date)}</span>
                                <Badge variant="outline">{t(change.kind_label)}</Badge>
                                {raise && <span className="text-xs text-green-700 dark:text-green-400">{raise}</span>}
                            </div>
                            {change.job_title && <Line label={t('Poste')} step={change.job_title} />}
                            {change.department && <Line label={t('Département')} step={change.department} />}
                            {change.category && <Line label={t('Catégorie')} step={change.category} />}
                            {change.gross_salary && <Line label={t('Salaire brut mensuel')} step={change.gross_salary} format={money} />}
                            {change.notes && <p className="text-muted-foreground text-xs whitespace-pre-line">{change.notes}</p>}
                            {change.author && (
                                <p className="text-muted-foreground text-xs">
                                    {t('Saisi par')} {change.author}
                                </p>
                            )}
                        </div>
                        {onDelete && index === 0 && (
                            <Button variant="ghost" size="sm" className="self-start" onClick={() => onDelete(change)}>
                                <Trash2 className="me-1 h-4 w-4" /> {t('Supprimer')}
                            </Button>
                        )}
                    </li>
                );
            })}
            {hireDate && (
                <li className="text-muted-foreground px-4 py-3 text-sm">
                    <span className="text-foreground font-medium">{formatDate(hireDate)}</span> {t('· Embauche')}
                </li>
            )}
        </ol>
    );
}
