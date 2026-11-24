// columns.tsx
'use client';

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
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: 'admin' | 'user';
    /** Set when the employee has left: cannot sign in, history kept. */
    deactivated_at: string | null;
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
): ColumnDef<User>[] => [
    {
        accessorKey: 'name',
        header: 'Nom',
    },
    {
        accessorKey: 'email',
        header: 'E-mail',
    },
    {
        accessorKey: 'role',
        header: 'Rôle',
        cell: ({ row }) => {
            const role = row.getValue('role') as string;
            return (
                <div className={`font-medium ${role === 'admin' ? 'text-blue-600' : ''}`}>{role === 'admin' ? 'Administrateur' : 'Utilisateur'}</div>
            );
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
                        Actif
                    </Badge>
                );
            }

            return (
                <Badge variant="secondary" title={`Désactivé le ${new Date(deactivatedAt).toLocaleDateString('fr-FR')}`}>
                    Désactivé
                </Badge>
            );
        },
    },
    {
        accessorKey: 'email_verified_at',
        header: 'Vérifié',
        cell: ({ row }) => {
            const verified = row.getValue('email_verified_at');
            return <div>{verified ? 'Oui' : 'Non'}</div>;
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Créé le',
        cell: ({ row }) => {
            const date = new Date(row.getValue('created_at'));
            return date.toLocaleDateString('fr-FR');
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
                            <span className="sr-only">Ouvrir le menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => navigator.clipboard.writeText(user.id.toString())}>Copier l'ID</DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                setSelectedUser(user);
                                setEditModelOpen(true);
                            }}
                        >
                            Modifier l'utilisateur
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => handleResetPassword(user)} className="text-yellow-600">
                            Réinitialiser le mot de passe
                        </DropdownMenuItem>
                        {user.id !== currentUserId && (
                            <>
                                <DropdownMenuSeparator />
                                {user.deactivated_at ? (
                                    <DropdownMenuItem onClick={() => handleToggleActive(user)} className="text-green-700">
                                        Réactiver le compte
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem onClick={() => handleToggleActive(user)} className="text-red-600">
                                        Désactiver le compte
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
