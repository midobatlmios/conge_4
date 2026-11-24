import { columns } from '@/components/demandes/columns';
import { DataTable } from '@/components/demandes/data-table';
import DemandeHistoryModal from '@/components/demandes/DemandeHistoryModal';
import EditDemandeModal from '@/components/EditDemandeModal';
import AppLayout from '@/layouts/app-layout';
import { Demande, type BreadcrumbItem } from '@/types';
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
    users: { id: number; name: string }[];
}

export default function Demandes({ demandes, users }: DemandesProps) {
    const [editModelOpen, setEditModelOpen] = useState(false);
    const [selectedDemande, setSelectedDemande] = useState<Demande | null>(null);
    const [historyId, setHistoryId] = useState<number | null>(null);
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;
    const user = auth.user;
    const isAdmin = user.role === 'admin';

    const handleUpdate = () => {
        setEditModelOpen(false);
        router.reload({ preserveUrl: true });
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestion des demandes" />
            <div className="container mx-auto py-10">
                <DataTable
                    columns={columns(handleDelete, setEditModelOpen, setSelectedDemande, isAdmin, setHistoryId)}
                    data={demandes}
                    isAdmin={isAdmin}
                    users={users}
                />
            </div>
            <DemandeHistoryModal demandeId={historyId} onClose={() => setHistoryId(null)} />
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
