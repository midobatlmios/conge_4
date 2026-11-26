import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { parseISODate, STATUS_LABELS, TYPE_CONGE_LABELS, TypeConge } from '@/lib/leave';
import axios from 'axios';
import { Ban, CheckCircle2, CirclePlus, Paperclip, Pencil, RotateCcw, Trash2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

type Action =
    | 'created'
    | 'updated'
    | 'accepted'
    | 'refused'
    | 'reopened'
    | 'deleted'
    | 'cancellation_requested'
    | 'cancelled'
    | 'cancellation_refused'
    | 'manager_approved'
    | 'manager_refused'
    | 'document_added'
    | 'document_removed';
type Field =
    | 'user_id'
    | 'type_conge'
    | 'date_debut'
    | 'date_fin'
    | 'start_half'
    | 'end_half'
    | 'etat'
    | 'comment'
    | 'manager_comment'
    | 'cnss_filed_on';
type Value = string | number | boolean | null;

interface HistoryEvent {
    id: number;
    action: Action;
    by: string | null;
    at: string;
    // Updates: { field: { old, new } }. Created/deleted: a snapshot { field: value }.
    // skipped_manager: HR decided before the employee's manager had approved.
    changes: (Partial<Record<Field, { old: Value; new: Value } | Value>> & { skipped_manager?: boolean; document?: string }) | null;
    employee_names: Record<string, string>;
}

const ACTIONS: Record<Action, { label: string; icon: typeof Pencil; color: string }> = {
    created: { label: 'Demande créée', icon: CirclePlus, color: 'text-blue-600' },
    updated: { label: 'Demande modifiée', icon: Pencil, color: 'text-yellow-700 dark:text-yellow-400' },
    accepted: { label: 'Demande acceptée', icon: CheckCircle2, color: 'text-green-700 dark:text-green-400' },
    refused: { label: 'Demande refusée', icon: XCircle, color: 'text-red-700 dark:text-red-400' },
    reopened: { label: 'Remise en attente', icon: RotateCcw, color: 'text-orange-700 dark:text-orange-400' },
    deleted: { label: 'Demande supprimée', icon: Trash2, color: 'text-red-700 dark:text-red-400' },
    cancellation_requested: { label: 'Annulation demandée', icon: Ban, color: 'text-orange-700 dark:text-orange-400' },
    cancelled: { label: 'Congé annulé', icon: Ban, color: 'text-muted-foreground' },
    cancellation_refused: { label: 'Annulation refusée, congé maintenu', icon: CheckCircle2, color: 'text-green-700 dark:text-green-400' },
    manager_approved: { label: 'Validée par le responsable', icon: CheckCircle2, color: 'text-blue-600' },
    manager_refused: { label: 'Refusée par le responsable', icon: XCircle, color: 'text-red-700 dark:text-red-400' },
    document_added: { label: 'Justificatif ajouté', icon: Paperclip, color: 'text-blue-600' },
    document_removed: { label: 'Justificatif supprimé', icon: Paperclip, color: 'text-muted-foreground' },
};

const FIELDS: Record<Field, string> = {
    user_id: 'Employé',
    type_conge: 'Type',
    date_debut: 'Début',
    date_fin: 'Fin',
    etat: 'État',
    start_half: "Début l'après-midi",
    end_half: 'Fin à midi',
    comment: 'Commentaire',
    manager_comment: 'Motif du responsable',
    cnss_filed_on: 'Dossier CNSS déposé le',
};

const STATUS: Record<string, string> = STATUS_LABELS;

function formatValue(field: Field, value: Value, names: Record<string, string>): string {
    if (field === 'start_half' || field === 'end_half') {
        // Stored as 0/1 (or a boolean): show it as yes/no.
        return value === true || value === 1 || value === '1' ? 'Oui' : 'Non';
    }
    if (value === null || value === '') return '(vide)';
    switch (field) {
        case 'date_debut':
        case 'date_fin':
            return parseISODate(String(value))?.toLocaleDateString('fr-FR') ?? String(value);
        case 'type_conge':
            return TYPE_CONGE_LABELS[value as TypeConge] ?? String(value);
        case 'etat':
            return STATUS[String(value)] ?? String(value);
        case 'user_id':
            return names[String(value)] ?? `#${value}`;
        default:
            return String(value);
    }
}

const isChange = (v: unknown): v is { old: Value; new: Value } => typeof v === 'object' && v !== null && 'new' in v;

interface Props {
    demandeId: number | null;
    onClose: () => void;
}

/** Timeline of a request: who created, edited, accepted/refused or deleted it, and when. */
export default function DemandeHistoryModal({ demandeId, onClose }: Props) {
    const [events, setEvents] = useState<HistoryEvent[] | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (demandeId === null) return;
        setEvents(null);
        setError(null);
        axios
            .get<HistoryEvent[]>(`/demandes/${demandeId}/history`, { headers: { Accept: 'application/json' } })
            .then((response) => setEvents(response.data))
            .catch(() => setError("Impossible de charger l'historique."));
    }, [demandeId]);

    return (
        <Dialog open={demandeId !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Historique de la demande</DialogTitle>
                    <DialogDescription>Qui a créé, modifié ou traité cette demande, et quand.</DialogDescription>
                </DialogHeader>

                {error && <p className="text-sm text-red-700 dark:text-red-400">{error}</p>}
                {!error && events === null && (
                    <div className="space-y-3" aria-busy="true" aria-label="Chargement de l'historique">
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="flex gap-3">
                                <Skeleton className="h-8 w-8 rounded-full" />
                                <div className="flex-1 space-y-2">
                                    <Skeleton className="h-4 w-2/3" />
                                    <Skeleton className="h-3 w-1/3" />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {events?.length === 0 && (
                    <p className="text-muted-foreground text-sm">Aucun historique : cette demande date d'avant la mise en place du suivi.</p>
                )}

                {events && events.length > 0 && (
                    <ol className="relative ml-3 border-l pl-6">
                        {events.map((event) => {
                            const meta = ACTIONS[event.action] ?? ACTIONS.updated;
                            const Icon = meta.icon;
                            const changes = Object.entries(event.changes ?? {}).filter(
                                ([key]) => key !== 'skipped_manager' && key !== 'document',
                            ) as [Field, { old: Value; new: Value } | Value][];
                            const isUpdate = changes.some(([, v]) => isChange(v));
                            const date = new Date(event.at);

                            return (
                                <li key={event.id} className="mb-6 last:mb-0">
                                    <span className={`bg-background absolute -left-[11px] flex h-5 w-5 items-center justify-center ${meta.color}`}>
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <div className="font-medium">{meta.label}</div>
                                    <div className="text-muted-foreground text-sm">
                                        {date.toLocaleDateString('fr-FR')} à{' '}
                                        {date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                                        {' · par '}
                                        {event.by ?? 'le système'}
                                    </div>
                                    {event.changes?.document && <div className="mt-1 text-sm">{event.changes.document}</div>}
                                    {event.changes?.skipped_manager && (
                                        <div className="text-xs text-orange-700 dark:text-orange-400">
                                            Décision RH sans attendre la validation du responsable
                                        </div>
                                    )}
                                    {isUpdate ? (
                                        <ul className="mt-1.5 space-y-0.5 text-sm">
                                            {changes
                                                .filter(([, v]) => isChange(v))
                                                .map(([field, v]) => {
                                                    const change = v as { old: Value; new: Value };
                                                    return (
                                                        <li key={field}>
                                                            <span className="text-muted-foreground">{FIELDS[field] ?? field} :</span>{' '}
                                                            <span className="line-through opacity-70">
                                                                {formatValue(field, change.old, event.employee_names)}
                                                            </span>
                                                            {' → '}
                                                            <span className="font-medium">
                                                                {formatValue(field, change.new, event.employee_names)}
                                                            </span>
                                                        </li>
                                                    );
                                                })}
                                        </ul>
                                    ) : (
                                        event.changes && (
                                            <p className="text-muted-foreground mt-1.5 text-sm">
                                                {formatValue('type_conge', event.changes.type_conge as Value, {})}, du{' '}
                                                {formatValue('date_debut', event.changes.date_debut as Value, {})} au{' '}
                                                {formatValue('date_fin', event.changes.date_fin as Value, {})}
                                            </p>
                                        )
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                )}
            </DialogContent>
        </Dialog>
    );
}
