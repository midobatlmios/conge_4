import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import EditUtilisateurModal from '@/components/EditUtilisateurModal';
import ResetPasswordModal from '@/components/ResetPasswordModal';
import { Button } from '@/components/ui/button';
import AddUtilisateurModal from '@/components/users/AddUtilisateurModal';
import { columns, User } from '@/components/users/columns';
import { DataTable } from '@/components/users/data-table';
import { Option } from '@/components/users/EmployeeRecordFields';
import AppLayout from '@/layouts/app-layout';
import { RoleOption, usePermissions } from '@/lib/roles';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface UsersProps {
    users: User[];
    departments: Option[];
    managers: Option[];
    /** Roles the signed-in user may give. */
    roles: RoleOption[];
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

const Users = ({ users: initialUsers, departments, managers, roles }: UsersProps) => {
    const can = usePermissions();
    const [users, setUsers] = useState<User[]>([]);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [resetPasswordModalOpen, setResetPasswordModalOpen] = useState(false);
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
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

    const handleToggleActive = async (user: User) => {
        const deactivating = !user.deactivated_at;
        const ok = await confirmAction(
            deactivating
                ? {
                      title: `Désactiver le compte de ${user.name} ?`,
                      description:
                          'Il ne pourra plus se connecter. Son historique de congés est conservé et le compte peut être réactivé à tout moment.',
                      confirmLabel: 'Désactiver',
                      destructive: true,
                  }
                : { title: `Réactiver le compte de ${user.name} ?`, description: 'Il pourra de nouveau se connecter.', confirmLabel: 'Réactiver' },
        );
        if (!ok) return;

        router.put(`/admin/users/${user.id}/${deactivating ? 'deactivate' : 'reactivate'}`, {}, { preserveScroll: true, onError: toastErrors });
    };

    /** Welcome e-mail with a link to choose a password (valid 7 days). */
    const handleInvite = async (user: User) => {
        const ok = await confirmAction({
            title: `Envoyer une invitation à ${user.name} ?`,
            description: `Un e-mail est envoyé à ${user.email} avec un lien pour choisir son mot de passe (valable 7 jours).`,
            confirmLabel: 'Envoyer',
        });
        if (ok) router.post('/admin/users/invite', { ids: [user.id] }, { preserveScroll: true, onError: toastErrors });
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
                    {can.manage_hr && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/import">
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Importer depuis Excel
                                </Link>
                            </Button>
                            <Button onClick={() => setAddModalOpen(true)} className="bg-green-700 text-white hover:bg-green-800">
                                <Plus className="mr-2 h-4 w-4" />
                                Ajouter un employé
                            </Button>
                        </div>
                    )}
                </div>
                <DataTable
                    columns={columns(
                        refreshData,
                        (open: boolean) => setEditModalOpen(open),
                        setSelectedUser,
                        handleResetPassword,
                        handleToggleActive,
                        auth.user?.id,
                        can,
                        handleInvite,
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
                        departments={departments}
                        managers={managers}
                        roles={roles}
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
                    departments={departments}
                    managers={managers}
                    roles={roles}
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
