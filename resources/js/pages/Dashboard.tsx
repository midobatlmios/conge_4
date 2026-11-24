import { AddDemandeModal } from '@/components/AddDemandeModal';
import DemandeHistoryModal from '@/components/demandes/DemandeHistoryModal';
import EditDemandeModal from '@/components/EditDemandeModal';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { TYPE_CONGE_LABELS, useLeaveRules } from '@/lib/leave';
import type { Demande } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
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
}

export default function Dashboard({ auth, demandes = [] }: DashboardProps) {
    const rules = useLeaveRules();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [selectedDemande, setSelectedDemande] = useState<Demande | null>(null);
    const [historyId, setHistoryId] = useState<number | null>(null);

    // Calculate status counts
    const statusCounts = {
        'en attente': demandes.filter((d) => d.etat === 'en attente').length,
        acceptée: demandes.filter((d) => d.etat === 'acceptée').length,
        refusée: demandes.filter((d) => d.etat === 'refusée').length,
    };

    const formatDate = (dateString: string) => {
        try {
            return format(new Date(dateString), 'dd MMMM yyyy', { locale: fr });
        } catch (error) {
            console.error('Error formatting date:', dateString, error);
            return dateString;
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'en attente':
                return 'text-yellow-500';
            case 'acceptée':
                return 'text-green-500';
            case 'refusée':
                return 'text-red-500';
            default:
                return 'text-gray-500';
        }
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Êtes-vous sûr de vouloir supprimer cette demande ?')) {
            router.delete(`/demandes/${id}`, {
                preserveUrl: true,
                onSuccess: () => {
                    router.reload({ preserveUrl: true });
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Tableau de bord" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-xl font-bold">Bienvenue, {auth.user.name}</h2>
                        <p className="text-4xl font-bold">{auth.user.remaining_days}</p>
                        <p className="text-muted-foreground text-sm">
                            jours de congé annuel restants sur {rules.maxDaysPerYear} en {new Date().getFullYear()}
                        </p>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-xl font-bold">Statut des demandes</h2>
                        <div className="space-y-2">
                            <p className={`text-lg ${statusCounts['en attente'] > 0 ? 'text-yellow-500' : 'text-gray-500'}`}>
                                En attente: <span className="font-bold">{statusCounts['en attente']}</span>
                            </p>
                            <p className={`text-lg ${statusCounts['acceptée'] > 0 ? 'text-green-500' : 'text-gray-500'}`}>
                                Acceptées: <span className="font-bold">{statusCounts['acceptée']}</span>
                            </p>
                            <p className={`text-lg ${statusCounts['refusée'] > 0 ? 'text-red-500' : 'text-gray-500'}`}>
                                Refusées: <span className="font-bold">{statusCounts['refusée']}</span>
                            </p>
                        </div>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col justify-between gap-4 rounded-xl border p-4">
                        <div>
                            <h2 className="mb-2 text-xl font-bold">Besoin d'un congé ?</h2>
                            <p className="text-muted-foreground text-sm">Soumettez une demande ; vous serez notifié dès qu'elle sera traitée.</p>
                        </div>
                        <Button className="w-full bg-green-600 font-medium text-white hover:bg-green-700" onClick={() => setIsModalOpen(true)}>
                            Nouvelle demande
                        </Button>
                    </div>
                </div>

                {/* Table des demandes */}
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-4 text-xl font-bold">Mes demandes de congé</h2>
                    {demandes.length === 0 ? (
                        <div className="py-8 text-center text-gray-500">Aucune demande de congé trouvée</div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date de demande</TableHead>
                                    <TableHead>Type de congé</TableHead>
                                    <TableHead>Date de début</TableHead>
                                    <TableHead>Date de fin</TableHead>
                                    <TableHead>Nombre de jours</TableHead>
                                    <TableHead>État</TableHead>
                                    <TableHead>Commentaire</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {demandes.map((demande) => (
                                    <TableRow key={demande.id}>
                                        <TableCell>{formatDate(demande.date_demande)}</TableCell>
                                        <TableCell>{TYPE_CONGE_LABELS[demande.type_conge] ?? demande.type_conge}</TableCell>
                                        <TableCell>{formatDate(demande.date_debut)}</TableCell>
                                        <TableCell>{formatDate(demande.date_fin)}</TableCell>
                                        <TableCell>{demande.nbr_jours}</TableCell>
                                        <TableCell>
                                            <span className={getStatusColor(demande.etat)}>
                                                {demande.etat.charAt(0).toUpperCase() + demande.etat.slice(1)}
                                            </span>
                                        </TableCell>
                                        <TableCell>{demande.comment || '-'}</TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button variant="outline" size="sm" onClick={() => setHistoryId(demande.id)}>
                                                    Historique
                                                </Button>
                                                {demande.etat === 'en attente' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedDemande(demande);
                                                            setIsEditModalOpen(true);
                                                        }}
                                                    >
                                                        Modifier
                                                    </Button>
                                                )}
                                                {demande.etat === 'en attente' && (
                                                    <Button variant="destructive" size="sm" onClick={() => handleDelete(demande.id)}>
                                                        Supprimer
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
                        router.reload({ preserveUrl: true });
                    }}
                />
            </div>
        </AppLayout>
    );
}
