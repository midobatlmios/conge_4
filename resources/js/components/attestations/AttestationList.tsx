import { Attestation, pdfUrl, STATUS_CLASS } from '@/components/attestations/attestations';
import { formatDate } from '@/components/employees/personal-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-react';

/** Attestations of one employee: status, number and PDF download. */
export default function AttestationList({ attestations }: { attestations: Attestation[] }) {
    if (attestations.length === 0) {
        return <p className="text-muted-foreground text-sm">Aucune attestation pour le moment.</p>;
    }

    return (
        <ul className="divide-y rounded-md border">
            {attestations.map((attestation) => (
                <li key={attestation.id} className="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{attestation.type_label}</span>
                            <Badge variant="outline" className={STATUS_CLASS[attestation.status]}>
                                {attestation.status_label}
                            </Badge>
                            {attestation.number && <span className="text-muted-foreground text-xs">n° {attestation.number}</span>}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {attestation.status === 'delivree'
                                ? `Délivrée le ${formatDate(attestation.issued_at)}${attestation.handled_by ? ` par ${attestation.handled_by}` : ''}`
                                : `Demandée le ${formatDate(attestation.requested_at)}`}
                            {attestation.purpose ? ` · ${attestation.purpose}` : ''}
                        </p>
                        {attestation.refusal_reason && <p className="text-xs text-red-600">Motif du refus : {attestation.refusal_reason}</p>}
                    </div>
                    {attestation.status === 'delivree' && (
                        <Button variant="outline" size="sm" asChild className="self-start sm:self-center">
                            <a href={pdfUrl(attestation)} target="_blank" rel="noopener">
                                <Download className="mr-2 h-4 w-4" /> PDF
                            </a>
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}
