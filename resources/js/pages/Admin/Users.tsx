// columns.tsx
'use client';

import EditUtilisateurModal from '@/components/EditUtilisateurModal';
import ResetPasswordModal from '@/components/ResetPasswordModal';
import { Button } from '@/components/ui/button';
import AddUtilisateurModal from '@/components/users/AddUtilisateurModal';
import { columns, User } from '@/components/users/columns';
import { DataTable } from '@/components/users/data-table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface UsersProps {
    users: User[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
    },
    {
        title: 'Gestion des employés',
        href: '/admin/users',
    },
];

const Users = ({ users: initialUsers }: UsersProps) => {
    const [users, setUsers] = useState<User[]>([]);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [resetPasswordModalOpen, setResetPasswordModalOpen] = useState(false);
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const { auth } = usePage<SharedData>().props;

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

    const handleResetPassword = (user: User) => {
        setSelectedUser(user);
        setResetPasswordModalOpen(true);
    };

    const handleToggleActive = (user: User) => {
        const deactivating = !user.deactivated_at;
        const question = deactivating
            ? `Désactiver le compte de ${user.name} ?

Il ne pourra plus se connecter. Son historique de congés est conservé et le compte peut être réactivé à tout moment.`
            : `Réactiver le compte de ${user.name} ? Il pourra de nouveau se connecter.`;

        if (!window.confirm(question)) {
            return;
        }

        setFeedback(null);
        router.put(
            `/admin/users/${user.id}/${deactivating ? 'deactivate' : 'reactivate'}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    setFeedback({ type: 'success', text: deactivating ? `${user.name} a été désactivé.` : `${user.name} a été réactivé.` }),
                onError: (errors) => setFeedback({ type: 'error', text: Object.values(errors).join(' ') }),
            },
        );
    };

    if (!users) {
        return <div>Chargement...</div>;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestion des employés" />
            <div className="container mx-auto py-10">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Gestion des employés</h1>
                    <Button onClick={() => setAddModalOpen(true)} className="bg-green-600 hover:bg-green-700">
                        <Plus className="mr-2 h-4 w-4" />
                        Ajouter un employé
                    </Button>
                </div>
                {feedback && (
                    <div
                        role="status"
                        className={`mb-4 rounded-md border px-4 py-3 text-sm ${
                            feedback.type === 'success'
                                ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                        }`}
                    >
                        {feedback.text}
                    </div>
                )}
                <DataTable
                    columns={columns(
                        refreshData,
                        (open: boolean) => setEditModalOpen(open),
                        setSelectedUser,
                        handleResetPassword,
                        handleToggleActive,
                        auth.user?.id,
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
