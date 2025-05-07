import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from '@/types';
import { columns } from "@/components/demandes/columns";
import { Demande } from "@/types";
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
        }
      });
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Demandes" />
      <div className="container mx-auto py-10">
        <DataTable 
          columns={columns(handleDelete, setEditModelOpen, setSelectedDemande, isAdmin)} 
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