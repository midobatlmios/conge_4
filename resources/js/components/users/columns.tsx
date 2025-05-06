// columns.tsx
"use client";

import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
 
export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  role: "admin" | "user";
  created_at: string;
  updated_at: string;
};

export const columns = (
  refreshData: () => void,
  setEditModelOpen: (open: boolean) => void,
  setSelectedUser: (user: User) => void,
  handleResetPassword: (user: User) => void
): ColumnDef<User>[] => [
  {
    id: "select",
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && "indeterminate")
        }
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label="Select all"
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label="Select row"
      />
    )
  },
  {
    accessorKey: "name",
    header: "Nom",
  },
  {
    accessorKey: "email",
    header: "Email",
  },
  {
    accessorKey: "role",
    header: "Rôle",
    cell: ({ row }) => {
      const role = row.getValue("role") as string;
      return (
        <div className={`font-medium ${role === "admin" ? "text-blue-600" : ""}`}>
          {role === "admin" ? "Administrateur" : "Utilisateur"}
        </div>
      );
    },
  },
  {
    accessorKey: "email_verified_at",
    header: "Vérifié",
    cell: ({ row }) => {
      const verified = row.getValue("email_verified_at");
      return (
        <div>
          {verified ? "Oui" : "Non"}
        </div>
      );
    },
  },
  {
    accessorKey: "created_at",
    header: "Créé le",
    cell: ({ row }) => {
      const date = new Date(row.getValue("created_at"));
      return date.toLocaleDateString('fr-FR');
    },
  },
  {
    id: "actions",
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
            <DropdownMenuItem
              onClick={() => navigator.clipboard.writeText(user.id.toString())}
            >
              Copier l'ID
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => {
                setSelectedUser(user);
                setEditModelOpen(true);
              }}
            >
              Modifier l'utilisateur
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => handleResetPassword(user)}
              className="text-yellow-600"
            >
              Réinitialiser le mot de passe
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="text-red-600">
              Supprimer
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      );
    },
  },
]; 