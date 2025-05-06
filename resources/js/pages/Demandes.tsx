import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from '@/types';
import { Demande, columns } from "@/components/demandes/columns";
import { DataTable } from "@/components/demandes/data-table";
import { Head } from '@inertiajs/react';
import EditDemandeModal from "@/components/EditDemandeModal";
import React, { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Demandes',
    href: '/demandes'
  }
];

export default function Posts({ demandes }: { demandes: Demande[] }) {
  const [editModelOpen, setEditModelOpen] = useState(false);
  const [selectedDemande, setSelectedDemande] = useState<Demande|null>(null);

  const handleUpdate = () => {
    // The parent component will handle the update through Inertia
    setEditModelOpen(false);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Demandes" />
      <div className="container mx-auto py-10">
        <DataTable columns={columns(()=>{},setEditModelOpen, setSelectedDemande)} data={demandes} />
      </div>
      <EditDemandeModal 
        isOpen={editModelOpen} 
        onClose={() => setEditModelOpen(false)}
        demande={selectedDemande} 
        onUpdate={handleUpdate} 
      />
    </AppLayout>
  );
}