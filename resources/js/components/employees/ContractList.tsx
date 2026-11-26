import { Contract, STATUS_LABELS } from '@/components/employees/contracts';
import { formatDate } from '@/components/employees/personal-data';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { intlLocale, t } from '@/lib/i18n';
import { FileSignature, Pencil, Trash2 } from 'lucide-react';

const statusClass: Record<Contract['status'], string> = {
    active: 'border-green-600 text-green-700 dark:text-green-400',
    upcoming: 'border-blue-500 text-blue-700 dark:text-blue-400',
    ended: '',
};

interface ContractListProps {
    contracts: Contract[];
    /** HR only: edit / delete buttons. */
    onEdit?: (contract: Contract) => void;
    onDelete?: (contract: Contract) => void;
}

/** An employee's contracts, newest first. */
export default function ContractList({ contracts, onEdit, onDelete }: ContractListProps) {
    if (contracts.length === 0) {
        return (
            <EmptyState
                icon={FileSignature}
                title={t('Aucun contrat enregistré')}
                description={t("Le contrat de travail (type, dates, période d'essai, salaire) est saisi par le service RH.")}
            />
        );
    }

    return (
        <ul className="divide-y rounded-md border">
            {contracts.map((contract) => (
                <li key={contract.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{contract.type_label}</span>
                            {contract.category_label && <span className="text-muted-foreground">· {contract.category_label}</span>}
                            <Badge variant={contract.status === 'ended' ? 'secondary' : 'outline'} className={statusClass[contract.status]}>
                                {STATUS_LABELS[contract.status]}
                            </Badge>
                            {contract.in_trial && (
                                <Badge variant="outline" className="border-orange-500 text-orange-700 dark:text-orange-400">
                                    {t("Période d'essai")}
                                </Badge>
                            )}
                        </div>
                        <p>
                            {t('Du')} {formatDate(contract.start_date)}{' '}
                            {contract.end_date ? `au ${formatDate(contract.end_date)}` : t('(durée indéterminée)')}
                            {contract.job_title ? ` · ${contract.job_title}` : ''}
                        </p>
                        {contract.gross_salary !== null && (
                            <p className="text-muted-foreground text-xs">
                                {t('Salaire brut mensuel :')} {contract.gross_salary.toLocaleString(intlLocale(), { minimumFractionDigits: 2 })}{' '}
                                {t('DH')}
                            </p>
                        )}
                        {contract.trial_end && (
                            <p className="text-muted-foreground text-xs">
                                {t("Période d'essai jusqu'au")} {formatDate(contract.trial_end)}
                                {contract.trial_renewed ? t(' (renouvelée)') : ''}
                            </p>
                        )}
                        {contract.notes && <p className="text-muted-foreground text-xs whitespace-pre-line">{contract.notes}</p>}
                    </div>
                    {(onEdit || onDelete) && (
                        <div className="flex gap-1 self-start">
                            {onEdit && (
                                <Button variant="ghost" size="sm" onClick={() => onEdit(contract)}>
                                    <Pencil className="h-4 w-4" />
                                    <span className="sr-only">{t('Modifier')}</span>
                                </Button>
                            )}
                            {onDelete && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-red-700 hover:bg-red-50 hover:text-red-700 dark:text-red-400"
                                    onClick={() => onDelete(contract)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                    <span className="sr-only">{t('Supprimer')}</span>
                                </Button>
                            )}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}
