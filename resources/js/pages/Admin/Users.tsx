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
        setUsers(prevUsers => prevUsers.map(user => user.id === updatedUser.id ? updatedUser : user));
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
                <h1 className="text-2xl font-bold mb-6">Gestion des Utilisateurs</h1>
                <DataTable 
                    columns={columns(
                        refreshData,
                        setEditModalOpen,
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
            </div>
        </AppLayout>
    );
};

export default Users;