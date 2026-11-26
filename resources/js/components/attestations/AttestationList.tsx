import { Attestation, pdfUrl, STATUS_CLASS } from '@/components/attestations/attestations';
import { formatDate } from '@/components/employees/personal-data';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { Download, FileBadge } from 'lucide-react';

/** Attestations of one employee: status, number and PDF download. */
export default function AttestationList({ attestations }: { attestations: Attestation[] }) {
    if (attestations.length === 0) {
        return (
            <EmptyState
                icon={FileBadge}
                title={t('Aucune attestation pour le moment')}
                description={t('Les attestations demandées et délivrées apparaîtront ici, prêtes à télécharger.')}
            />
        );
    }

    return (
        <ul className="divide-y rounded-md border">
            {attestations.map((attestation) => (
                <li key={attestation.id} className="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{t(attestation.type_label)}</span>
                            <Badge variant="outline" className={STATUS_CLASS[attestation.status]}>
                                {t(attestation.status_label)}
                            </Badge>
                            {attestation.number && <span className="text-muted-foreground text-xs">n° {attestation.number}</span>}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {attestation.status === 'delivree'
                                ? `${t('Délivrée le :date', { date: formatDate(attestation.issued_at) })}${attestation.handled_by ? t(' par :name', { name: attestation.handled_by }) : ''}`
                                : t('Demandée le :date', { date: formatDate(attestation.requested_at) })}
                            {attestation.purpose ? ` · ${attestation.purpose}` : ''}
                        </p>
                        {attestation.refusal_reason && (
                            <p className="text-xs text-red-700 dark:text-red-400">
                                {t('Motif du refus :')} {attestation.refusal_reason}
                            </p>
                        )}
                    </div>
                    {attestation.status === 'delivree' && (
                        <Button variant="outline" size="sm" asChild className="self-start sm:self-center">
                            <a href={pdfUrl(attestation)} target="_blank" rel="noopener">
                                <Download className="me-2 h-4 w-4" /> {t('PDF')}
                            </a>
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}
