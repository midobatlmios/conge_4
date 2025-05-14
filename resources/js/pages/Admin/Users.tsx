// columns.tsx
"use client";

import { useState, useEffect } from "react";
import { DataTable } from "@/components/users/data-table";
import { columns } from "@/components/users/columns";
import { User } from "@/components/users/columns";
import EditUtilisateurModal from "@/components/EditUtilisateurModal";
import ResetPasswordModal from "@/components/ResetPasswordModal";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Button } from "@/components/ui/button";
import { Plus } from "lucide-react";
import AddUtilisateurModal from "@/components/users/AddUtilisateurModal";

interface UsersProps {
    users: User[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard'
    },
    {
        title: 'Gestion des Utilisateurs',
        href: '/admin/users'
    }
];

const Users = ({ users: initialUsers }: UsersProps) => {
    const [users, setUsers] = useState<User[]>([]);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [resetPasswordModalOpen, setResetPasswordModalOpen] = useState(false);
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    useEffect(() => {
        if (initialUsers) {
            setUsers(initialUsers);
        }
    }, [initialUsers]);

    const refreshData = () => {
        if (initialUsers) {
            setUsers(initialUsers);
        }
    };

    const handleUpdateUser = (updatedUser: User) => {
        setUsers(prevUsers => prevUsers.map(user => 
            user.id === updatedUser.id ? updatedUser : user
        ));
        setEditModalOpen(false);
        setSelectedUser(null);
    };

    const handleResetPassword = (user: User) => {
        setSelectedUser(user);
        setResetPasswordModalOpen(true);
    };

    if (!users) {
        return <div>Chargement...</div>;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestion des Utilisateurs" />
            <div className="container mx-auto py-10">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">Gestion des employés</h1>
                    <Button 
                        onClick={() => setAddModalOpen(true)}
                        className="bg-green-600 hover:bg-green-700"
                    >
                        <Plus className="w-4 h-4 mr-2" />
                        Ajouter un employé
                    </Button>
                </div>
                <DataTable 
                    columns={columns(
                        refreshData,
                        (open: boolean) => setEditModalOpen(open),
                        setSelectedUser,
                        handleResetPassword
                    )} 
                    data={users} 
                />

                {editModalOpen && selectedUser && (
                    <EditUtilisateurModal
                        isOpen={editModalOpen}
                        onClose={() => {
                            setEditModalOpen(false);
                            setSelectedUser(null);
                        }}
                        user={selectedUser}
                        onUpdate={handleUpdateUser}
                    />
                )}

                {resetPasswordModalOpen && selectedUser && (
                    <ResetPasswordModal
                        isOpen={resetPasswordModalOpen}
                        onClose={() => {
                            setResetPasswordModalOpen(false);
                            setSelectedUser(null);
                        }}
                        user={selectedUser}
                        onSuccess={() => {
                            setResetPasswordModalOpen(false);
                            setSelectedUser(null);
                        }}
                    />
                )}

                <AddUtilisateurModal
                    isOpen={addModalOpen}
                    onClose={() => setAddModalOpen(false)}
                    onSuccess={() => {
                        setAddModalOpen(false);
                        refreshData();
                    }}
                />
            </div>
        </AppLayout>
    );
};

export default Users;