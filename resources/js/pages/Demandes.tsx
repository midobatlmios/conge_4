import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from '@/types';
import { Demande, columns } from "@/components/demandes/columns";
import { DataTable } from "@/components/demandes/data-table";
import { Head, router, usePage } from '@inertiajs/react';
import EditDemandeModal from "@/components/EditDemandeModal";
import React, { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Demandes',
    href: '/demandes'
  }
];

interface DemandesProps {
  demandes: Demande[];
}

export default function Demandes({ demandes }: DemandesProps) {
  const [editModelOpen, setEditModelOpen] = useState(false);
  const [selectedDemande, setSelectedDemande] = useState<Demande | null>(null);
  const user = usePage().props.auth?.user || {};
  const isAdmin = user.role === 'admin';

  const handleUpdate = () => {
    setEditModelOpen(false);
    router.reload({ preserveScroll: true });
  };

  const handleDelete = (id: number) => {
    if (window.confirm('Êtes-vous sûr de vouloir supprimer cette demande ?')) {
      router.delete(`/demandes/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ preserveScroll: true });
        }
      });
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Demandes" />
      <div className="container mx-auto py-10">
        <DataTable 
          columns={columns(handleDelete, setEditModelOpen, setSelectedDemande)} 
          data={demandes} 
        />
      </div>
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