import { AddDemandeModal } from '@/components/AddDemandeModal';
import { toastErrors } from '@/components/app-toaster';
import { TodayState } from '@/components/attendance/attendance';
import ClockCard from '@/components/attendance/ClockCard';
import { confirmAction } from '@/components/confirm-dialog';
import { canRequestCancellation, requestCancellation } from '@/components/demandes/cancellation';
import DemandeHistoryModal from '@/components/demandes/DemandeHistoryModal';
import DocumentsModal, { DocumentsButton } from '@/components/demandes/DocumentsModal';
import EditDemandeModal from '@/components/EditDemandeModal';
import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { STATUS_COLORS, STATUS_LABELS, TYPE_CONGE_LABELS, formatDays, halfDayNote, parseISODate, useLeaveRules } from '@/lib/leave';
import type { Demande } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { useState } from 'react';

interface DashboardProps extends PageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            remaining_days: number;
            role: string;
        };
    };
    demandes: Demande[];
    attendance: TodayState;
}

export default function Dashboard({ auth, demandes = [], attendance }: DashboardProps) {
    const rules = useLeaveRules();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [selectedDemande, setSelectedDemande] = useState<Demande | null>(null);
    const [historyId, setHistoryId] = useState<number | null>(null);
    const [documentsId, setDocumentsId] = useState<number | null>(null);
    const documentsDemande = demandes.find((d) => d.id === documentsId) ?? null;

    // Calculate status counts
    const statusCounts = {
        'en attente': demandes.filter((d) => d.etat === 'en attente').length,
        acceptée: demandes.filter((d) => d.etat === 'acceptée').length,
        refusée: demandes.filter((d) => d.etat === 'refusée').length,
    };

    const formatDate = (dateString: string) => {
        try {
            return new Date(dateString).toLocaleDateString(intlLocale(), { day: '2-digit', month: 'long', year: 'numeric' });
        } catch (error) {
            console.error('Error formatting date:', dateString, error);
            return dateString;
        }
    };

    const handleDelete = async (id: number) => {
        const ok = await confirmAction({
            title: t('Supprimer cette demande ?'),
            description: t('Elle sera définitivement supprimée.'),
            confirmLabel: t('Supprimer'),
            destructive: true,
        });
        if (ok) router.delete(`/demandes/${id}`, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <AppLayout>
            <Head title={t('Tableau de bord')} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {attendance && <ClockCard state={attendance} />}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-xl font-bold">
                            {t('Bienvenue,')} {auth.user.name}
                        </h2>
                        <p className="text-4xl font-bold">{formatDays(rules.balance?.remainingDays ?? auth.user.remaining_days)}</p>
                        <p className="text-muted-foreground text-sm">{t("jours de congé annuel disponibles aujourd'hui")}</p>
                        {rules.balance && (
                            <p className="text-muted-foreground mt-2 text-xs">
                                {t('Acquis à ce jour :')} {formatDays(rules.balance.earnedDays)} {t('sur')} {formatDays(rules.balance.annualDays)}{' '}
                                {t('en')} {new Date().getFullYear()} {t('· pris ou prévus :')} {formatDays(rules.balance.usedDays)}
                                {rules.balance.annualDays > rules.maxDaysPerYear &&
                                    t(" · dont :days d'ancienneté", { days: formatDays(rules.balance.annualDays - rules.maxDaysPerYear) })}
                            </p>
                        )}
                        {rules.balance && rules.balance.carryLeftDays > 0 && (
                            <p className="mt-2 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-xs text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300">
                                {t('Dont')} {formatDays(rules.balance.carryLeftDays)} {t('jour(s) reporté(s) de')} {new Date().getFullYear() - 1}
                                {t(', à prendre avant le')} {parseISODate(rules.balance.carryExpires)?.toLocaleDateString(intlLocale())}.
                            </p>
                        )}
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-xl font-bold">{t('Statut des demandes')}</h2>
                        <div className="space-y-2">
                            <p
                                className={`text-lg ${statusCounts['en attente'] > 0 ? 'text-yellow-700 dark:text-yellow-400' : 'text-muted-foreground'}`}
                            >
                                {t('En attente:')} <span className="font-bold">{statusCounts['en attente']}</span>
                            </p>
                            <p className={`text-lg ${statusCounts['acceptée'] > 0 ? 'text-green-700 dark:text-green-400' : 'text-muted-foreground'}`}>
                                {t('Acceptées:')} <span className="font-bold">{statusCounts['acceptée']}</span>
                            </p>
                            <p className={`text-lg ${statusCounts['refusée'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-muted-foreground'}`}>
                                {t('Refusées:')} <span className="font-bold">{statusCounts['refusée']}</span>
                            </p>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col justify-between gap-4 rounded-xl border p-4">
                        <div>
                            <h2 className="mb-2 text-xl font-bold">{t("Besoin d'un congé ?")}</h2>
                            <p className="text-muted-foreground text-sm">
                                {t("Soumettez une demande ; vous serez notifié dès qu'elle sera traitée.")}
                            </p>
                        </div>
                        <Button className="w-full bg-green-700 font-medium text-white hover:bg-green-800" onClick={() => setIsModalOpen(true)}>
                            {t('Nouvelle demande')}
                        </Button>
                    </div>
                </div>

                {/* Table des demandes */}
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-4 text-xl font-bold">{t('Mes demandes de congé')}</h2>
                    {demandes.length === 0 ? (
                        <EmptyState
                            icon={CalendarPlus}
                            title={t("Aucune demande pour l'instant")}
                            description={t(
                                'Vos demandes de congé apparaîtront ici, avec leur état et leur historique. Utilisez « Nouvelle demande » pour commencer.',
                            )}
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Date de demande')}</TableHead>
                                    <TableHead>{t('Type de congé')}</TableHead>
                                    <TableHead>{t('Date de début')}</TableHead>
                                    <TableHead>{t('Date de fin')}</TableHead>
                                    <TableHead>{t('Nombre de jours')}</TableHead>
                                    <TableHead>{t('État')}</TableHead>
                                    <TableHead>{t('Commentaire')}</TableHead>
                                    <TableHead>{t('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {demandes.map((demande) => (
                                    <TableRow key={demande.id}>
                                        <TableCell>{formatDate(demande.date_demande)}</TableCell>
                                        <TableCell>{TYPE_CONGE_LABELS[demande.type_conge] ?? demande.type_conge}</TableCell>
                                        <TableCell>{formatDate(demande.date_debut) + halfDayNote(demande.start_half, 'start')}</TableCell>
                                        <TableCell>{formatDate(demande.date_fin) + halfDayNote(demande.end_half, 'end')}</TableCell>
                                        <TableCell>{formatDays(Number(demande.nbr_jours))}</TableCell>
                                        <TableCell>
                                            <span className={STATUS_COLORS[demande.etat]}>{STATUS_LABELS[demande.etat]}</span>
                                        </TableCell>
                                        <TableCell>
                                            {demande.comment || '-'}
                                            {demande.manager_comment && (
                                                <div className="text-xs text-red-700 dark:text-red-400">
                                                    {t('Motif du responsable :')} {demande.manager_comment}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button variant="outline" size="sm" onClick={() => setHistoryId(demande.id)}>
                                                    {t('Historique')}
                                                </Button>
                                                <DocumentsButton demande={demande} onClick={() => setDocumentsId(demande.id)} />
                                                {demande.etat === 'en attente' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedDemande(demande);
                                                            setIsEditModalOpen(true);
                                                        }}
                                                    >
                                                        {t('Modifier')}
                                                    </Button>
                                                )}
                                                {demande.etat === 'en attente' && (
                                                    <Button variant="destructive" size="sm" onClick={() => handleDelete(demande.id)}>
                                                        {t('Supprimer')}
                                                    </Button>
                                                )}
                                                {canRequestCancellation(demande) && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-orange-700 hover:bg-orange-50 hover:text-orange-700 dark:text-orange-400"
                                                        onClick={() => requestCancellation(demande)}
                                                    >
                                                        {t("Demander l'annulation")}
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DemandeHistoryModal demandeId={historyId} onClose={() => setHistoryId(null)} />

                <DocumentsModal
                    demande={documentsDemande}
                    canManage={['en attente', 'validée manager'].includes(documentsDemande?.etat ?? '')}
                    onClose={() => setDocumentsId(null)}
                />

                <AddDemandeModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />

                <EditDemandeModal
                    isOpen={isEditModalOpen}
                    onClose={() => {
                        setIsEditModalOpen(false);
                        setSelectedDemande(null);
                    }}
                    demande={selectedDemande}
                    onUpdate={() => {
                        setIsEditModalOpen(false);
                        setSelectedDemande(null);
                    }}
                />
            </div>
        </AppLayout>
    );
}
