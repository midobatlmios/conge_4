import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import { columns } from '@/components/demandes/columns';
import { DataTable } from '@/components/demandes/data-table';
import DemandeHistoryModal from '@/components/demandes/DemandeHistoryModal';
import DocumentsModal from '@/components/demandes/DocumentsModal';
import EditDemandeModal from '@/components/EditDemandeModal';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/roles';
import { Demande, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Gestion des demandes',
        href: '/demandes',
    },
];

interface DemandesProps {
    demandes: Demande[];
    /** Sick leave from this many calendar days needs a CNSS dossier. */
    cnssMinDays: number;
    users: { id: number; name: string }[];
}

export default function Demandes({ demandes, users, cnssMinDays }: DemandesProps) {
    const [editModelOpen, setEditModelOpen] = useState(false);
    const [selectedDemande, setSelectedDemande] = useState<Demande | null>(null);
    const [historyId, setHistoryId] = useState<number | null>(null);
    // Looked up in the props on each render so the list stays current after an upload.
    const [documentsId, setDocumentsId] = useState<number | null>(null);
    const documentsDemande = demandes.find((d) => d.id === documentsId) ?? null;
    const { auth } = usePage<SharedData>().props;
    const user = auth.user;
    // Admin and RH decide; Paie only reads (it still manages its own requests).
    const isAdmin = usePermissions().manage_hr;

    // The server redirects back after each change, so the list is already fresh.
    const handleUpdate = () => setEditModelOpen(false);

    const handleDelete = async (id: number) => {
        const ok = await confirmAction({
            title: 'Supprimer cette demande ?',
            description: 'Elle sera définitivement supprimée.',
            confirmLabel: 'Supprimer',
            destructive: true,
        });
        if (ok) router.delete(`/demandes/${id}`, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestion des demandes" />
            <div className="container mx-auto py-10">
                <DataTable
                    columns={columns(handleDelete, setEditModelOpen, setSelectedDemande, isAdmin, setHistoryId, setDocumentsId, user.id, cnssMinDays)}
                    data={demandes}
                    isAdmin={isAdmin}
                    users={users}
                />
            </div>
            <DemandeHistoryModal demandeId={historyId} onClose={() => setHistoryId(null)} />
            <DocumentsModal
                demande={documentsDemande}
                canManage={
                    isAdmin || (documentsDemande?.user_id === user.id && ['en attente', 'validée manager'].includes(documentsDemande?.etat ?? ''))
                }
                onClose={() => setDocumentsId(null)}
            />
            <EditDemandeModal
                isOpen={editModelOpen}
                onClose={() => setEditModelOpen(false)}
                demande={selectedDemande}
                onUpdate={handleUpdate}
                isAdmin={isAdmin}
            />
        </AppLayout>
    );
}
