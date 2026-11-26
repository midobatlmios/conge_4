// columns.tsx
'use client';

import { Button } from '@/components/ui/button';
import { STATUS_COLORS, STATUS_LABELS, TYPE_CONGE_LABELS, TypeConge, formatDays, halfDayNote } from '@/lib/leave';
import { Demande } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { DocumentsButton } from './DocumentsModal';
import { canRequestCancellation, cancelLeave, keepLeave, needsCnssFiling, recordCnss, requestCancellation } from './cancellation';

export const columns = (
    onDelete: (id: number) => void,
    onEdit: (isOpen: boolean) => void,
    setSelectedDemande: (demande: Demande) => void,
    isAdmin: boolean,
    onHistory: (id: number) => void,
    onDocuments: (id: number) => void,
    currentUserId: number,
    /** Sick leave from this many calendar days needs a CNSS dossier (HR only; null hides it). */
    cnssMinDays: number | null = null,
): ColumnDef<Demande>[] => [
    {
        accessorKey: 'user.name',
        header: 'Employé',
    },
    {
        id: 'remaining_days',
        header: 'Jours disponibles',
        cell: ({ row }) => {
            // Earned so far this year minus accepted days.
            const remainingDays = row.original.user.remaining_days ?? 0;
            return (
                <div
                    className={`font-medium ${remainingDays <= 5 ? 'text-red-700 dark:text-red-400' : remainingDays <= 10 ? 'text-yellow-700 dark:text-yellow-400' : 'text-green-700 dark:text-green-400'}`}
                >
                    {formatDays(remainingDays)} jours
                </div>
            );
        },
    },
    {
        accessorKey: 'date_demande',
        header: 'Date de demande',
        cell: ({ row }) => {
            const date = new Date(row.getValue('date_demande'));
            return date.toLocaleDateString('fr-FR');
        },
    },
    {
        accessorKey: 'date_debut',
        header: 'Date de début',
        cell: ({ row }) => {
            const date = new Date(row.getValue('date_debut'));
            return date.toLocaleDateString('fr-FR') + halfDayNote(row.original.start_half, 'start');
        },
    },
    {
        accessorKey: 'date_fin',
        header: 'Date de fin',
        cell: ({ row }) => {
            const date = new Date(row.getValue('date_fin'));
            return date.toLocaleDateString('fr-FR') + halfDayNote(row.original.end_half, 'end');
        },
    },
    {
        accessorKey: 'type_conge',
        header: 'Type de congé',
        cell: ({ row }) => {
            const demande = row.original;
            const type = demande.type_conge as TypeConge;
            return (
                <div>
                    <div className="font-medium">{TYPE_CONGE_LABELS[type] ?? type}</div>
                    {type === 'maladie' && (
                        <div className="text-muted-foreground text-xs">
                            {demande.calendar_days} jour{demande.calendar_days > 1 ? 's' : ''} calendaire{demande.calendar_days > 1 ? 's' : ''}
                            {demande.cnss_filed_on
                                ? ` · CNSS déposé le ${new Date(`${demande.cnss_filed_on}T00:00:00`).toLocaleDateString('fr-FR')}`
                                : ''}
                        </div>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'nbr_jours',
        header: 'Nombre de jours',
        cell: ({ row }) => {
            const nbr_jours = parseFloat(row.getValue('nbr_jours'));
            return (
                <div className="text-right font-medium">
                    {formatDays(nbr_jours)} jour{nbr_jours > 1 ? 's' : ''}
                </div>
            );
        },
    },
    {
        accessorKey: 'etat',
        header: 'État',
        filterFn: 'equals',
        cell: ({ row }) => {
            const etat = row.original.etat;
            return <div className={`font-medium ${STATUS_COLORS[etat]}`}>{STATUS_LABELS[etat]}</div>;
        },
    },
    {
        accessorKey: 'annee',
        header: 'Année',
    },
    {
        accessorKey: 'comment',
        header: 'Commentaire',
        cell: ({ row }) => {
            const comment = row.original.comment;
            return (
                <div className="max-w-xs truncate" title={comment || ''}>
                    {comment || '-'}
                </div>
            );
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const demande = row.original;
            // Hide edit button for certain types unless user is admin; a leave being cancelled
            // (or cancelled) only changes through the cancellation buttons.
            const cancelling = demande.etat === 'annulation demandée' || demande.etat === 'annulée';
            // Employees only change their own requests while pending (DemandePolicy).
            const own = demande.user_id === currentUserId;
            const ownPending = !isAdmin && own && demande.etat === 'en attente';
            const hideEdit = cancelling || (!isAdmin && (!ownPending || ['mariage', 'naissance', 'deces'].includes(demande.type_conge)));
            return (
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" onClick={() => onHistory(demande.id)}>
                        Historique
                    </Button>
                    <DocumentsButton demande={demande} onClick={() => onDocuments(demande.id)} />
                    {isAdmin && cnssMinDays !== null && needsCnssFiling(demande, cnssMinDays) && !['refusée', 'annulée'].includes(demande.etat) && (
                        <Button
                            variant="outline"
                            size="sm"
                            className={demande.cnss_filed_on ? '' : 'text-orange-700 hover:bg-orange-50 hover:text-orange-700 dark:text-orange-400'}
                            onClick={() => recordCnss(demande)}
                        >
                            {demande.cnss_filed_on ? 'Dossier CNSS ✓' : 'Dossier CNSS à déposer'}
                        </Button>
                    )}
                    {isAdmin && demande.etat === 'annulation demandée' && (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-orange-700 hover:bg-orange-50 hover:text-orange-700 dark:text-orange-400"
                                onClick={() => cancelLeave(demande, true)}
                            >
                                Approuver l'annulation
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => keepLeave(demande)}>
                                Maintenir le congé
                            </Button>
                        </>
                    )}
                    {!isAdmin && own && canRequestCancellation(demande) && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-orange-700 hover:bg-orange-50 hover:text-orange-700 dark:text-orange-400"
                            onClick={() => requestCancellation(demande)}
                        >
                            Demander l'annulation
                        </Button>
                    )}
                    {isAdmin && demande.etat === 'acceptée' && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-orange-700 hover:bg-orange-50 hover:text-orange-700 dark:text-orange-400"
                            onClick={() => cancelLeave(demande, false)}
                        >
                            Annuler le congé
                        </Button>
                    )}
                    {!hideEdit && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-yellow-700 hover:bg-yellow-50 hover:text-yellow-700 dark:text-yellow-400"
                            onClick={() => {
                                setSelectedDemande(demande);
                                onEdit(true);
                            }}
                        >
                            Modifier
                        </Button>
                    )}
                    {(isAdmin || ownPending) && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-red-700 hover:bg-red-50 hover:text-red-700 dark:text-red-400"
                            onClick={() => onDelete(demande.id)}
                        >
                            Supprimer
                        </Button>
                    )}
                </div>
            );
        },
    },
];
