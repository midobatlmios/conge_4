// columns.tsx
'use client';

import { Contract } from '@/components/employees/contracts';
import { formatDate } from '@/components/employees/personal-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { intlLocale, t } from '@/lib/i18n';
import { canManageAccount, Permissions, Role, ROLE_LABELS } from '@/lib/roles';
import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: Role;
    /** Set when the employee has left: cannot sign in, history kept. */
    deactivated_at: string | null;
    /** Last welcome e-mail (link to choose a password), if any. */
    invited_at?: string | null;
    matricule: string | null;
    department_id: number | null;
    site_id?: number | null;
    site?: { id: number; name: string } | null;
    job_title: string | null;
    hire_date: string | null; // YYYY-MM-DD
    manager_id: number | null;
    department: { id: number; name: string } | null;
    manager: { id: number; name: string } | null;
    /** The contract in force, if any. */
    contract?: Contract | null;
    created_at: string;
    updated_at: string;
};

export const columns = (
    refreshData: () => void,
    setEditModelOpen: (open: boolean) => void,
    setSelectedUser: (user: User) => void,
    handleResetPassword: (user: User) => void,
    handleToggleActive: (user: User) => void,
    currentUserId: number | undefined,
    can: Permissions,
    handleInvite: (user: User) => void,
): ColumnDef<User>[] => [
    {
        accessorKey: 'matricule',
        header: 'Matricule',
        cell: ({ row }) => row.original.matricule ?? <span className="text-muted-foreground">—</span>,
    },
    {
        accessorKey: 'name',
        header: 'Nom',
        cell: ({ row }) => (
            <div>
                <Link href={`/admin/users/${row.original.id}`} className="font-medium hover:underline">
                    {row.original.name}
                </Link>
                {row.original.job_title && <div className="text-muted-foreground text-xs">{row.original.job_title}</div>}
            </div>
        ),
    },
    {
        accessorKey: 'email',
        header: 'E-mail',
    },
    {
        id: 'department',
        accessorFn: (user) => user.department?.name ?? '',
        header: 'Département',
        cell: ({ row }) => row.original.department?.name ?? <span className="text-muted-foreground">—</span>,
    },
    {
        id: 'manager',
        accessorFn: (user) => user.manager?.name ?? '',
        header: 'Responsable',
        cell: ({ row }) => row.original.manager?.name ?? <span className="text-muted-foreground">—</span>,
    },
    {
        id: 'contract',
        accessorFn: (user) => user.contract?.type_label ?? '',
        header: 'Contrat',
        cell: ({ row }) => {
            const contract = row.original.contract;
            if (!contract) return <span className="text-muted-foreground">—</span>;
            return (
                <div>
                    <div>{t(contract.type_label)}</div>
                    <div className="text-muted-foreground text-xs">
                        {contract.status === 'upcoming'
                            ? t('à partir du :p0', { p0: formatDate(contract.start_date) })
                            : contract.end_date
                              ? `${contract.status === 'ended' ? 'terminé le' : "jusqu'au"} ${formatDate(contract.end_date)}`
                              : contract.in_trial
                                ? t("en période d'essai")
                                : ''}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'hire_date',
        header: 'Embauché le',
        cell: ({ row }) => {
            const hireDate = row.original.hire_date;
            // Accounts created before employee records existed have no hire date yet.
            return hireDate ? (
                new Date(`${hireDate}T00:00:00`).toLocaleDateString(intlLocale())
            ) : (
                <Badge variant="outline" className="border-orange-500 text-orange-700 dark:text-orange-400">
                    {t('À compléter')}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'role',
        header: 'Rôle',
        cell: ({ row }) => {
            const role = row.getValue('role') as Role;
            return <div className={`font-medium ${role !== 'user' ? 'text-blue-600 dark:text-blue-400' : ''}`}>{ROLE_LABELS[role]}</div>;
        },
    },
    {
        accessorKey: 'deactivated_at',
        header: 'Statut',
        cell: ({ row }) => {
            const deactivatedAt = row.original.deactivated_at;

            if (!deactivatedAt) {
                return (
                    <Badge variant="outline" className="border-green-600 text-green-700 dark:text-green-400">
                        {t('Actif')}
                    </Badge>
                );
            }

            return (
                <Badge variant="secondary" title={t('Désactivé le :p0', { p0: new Date(deactivatedAt).toLocaleDateString(intlLocale()) })}>
                    {t('Désactivé')}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'email_verified_at',
        header: 'Vérifié',
        cell: ({ row }) => {
            const verified = row.getValue('email_verified_at');
            return <div>{verified ? t('Oui') : t('Non')}</div>;
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const user = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">{t('Ouvrir le menu')}</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>{t('Actions')}</DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link href={`/admin/users/${user.id}`}>{t('Voir la fiche')}</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => navigator.clipboard.writeText(user.id.toString())}>{t("Copier l'ID")}</DropdownMenuItem>
                        {canManageAccount(can, user.role) && (
                            <>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelectedUser(user);
                                        setEditModelOpen(true);
                                    }}
                                >
                                    {t("Modifier l'utilisateur")}
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleResetPassword(user)} className="text-yellow-700 dark:text-yellow-400">
                                    {t('Réinitialiser le mot de passe')}
                                </DropdownMenuItem>
                                {user.id !== currentUserId && !user.deactivated_at && (
                                    <DropdownMenuItem onClick={() => handleInvite(user)}>
                                        {user.invited_at ? t("Renvoyer l'invitation") : t('Envoyer une invitation')}
                                    </DropdownMenuItem>
                                )}
                                {user.id !== currentUserId && (
                                    <>
                                        <DropdownMenuSeparator />
                                        {user.deactivated_at ? (
                                            <DropdownMenuItem onClick={() => handleToggleActive(user)} className="text-green-700 dark:text-green-400">
                                                {t('Réactiver le compte')}
                                            </DropdownMenuItem>
                                        ) : (
                                            <DropdownMenuItem onClick={() => handleToggleActive(user)} className="text-red-700 dark:text-red-400">
                                                {t('Désactiver le compte')}
                                            </DropdownMenuItem>
                                        )}
                                    </>
                                )}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
