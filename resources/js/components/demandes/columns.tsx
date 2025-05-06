// columns.tsx
"use client";

import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react" ;
import { Checkbox } from "@/components/ui/checkbox"
import { Button } from "@/components/ui/button";
import {router} from "@inertiajs/react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
 
export type Demande = {
  id: number;
  user_id: number;
  user: {
    name: string;
    email: string;
    remaining_days?: number;
  };
  date_demande: string;
  date_debut: string;
  date_fin: string;
  nbr_jours: number;
  annee: number;
  etat: "en attente" | "acceptée" | "refusée";
  valide_par: number | null;
  comment: string | null;
};

const handleDelete = (id: string) =>{
    router.delete(`/demandes/${id}`, {
        preserveScroll:true  , 
    });
};

export const columns = (setIsModalOpen:(open:boolean)=>void,
setEditModelOpen:(open:boolean)=>void,
setSelectedDemande : (demande:Demande | null ) =>void
): ColumnDef<Demande>[] => [
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
        accessorKey: "user.name",
        header: "Employé",
    },
    {
        id: "remaining_days",
        header: "Jours restants",
        cell: ({ row }) => {
            const remainingDays = row.original.user.remaining_days ?? 18;
            return (
                <div className={`font-medium ${remainingDays <= 5 ? 'text-red-500' : remainingDays <= 10 ? 'text-yellow-500' : 'text-green-500'}`}>
                    {remainingDays} jours
                </div>
            );
        }
    },
    {
        accessorKey: "etat",
        header: "État",
    },
    {
        accessorKey: "date_demande",
        header: "Date de demande",
    },
    {
        accessorKey: "date_debut",
        header: "Début",
    },
    {
        accessorKey: "date_fin",
        header: "Fin",
    },
    {
        accessorKey: "nbr_jours",
        header: "Nombre de jours",
    },
    {
        accessorKey: "annee",
        header: "Année",
    },
    {
        accessorKey: "comment",
        header: "Commentaire",
        cell: ({ row }) => {
            const comment = row.original.comment;
            return (
                <div className="max-w-xs truncate" title={comment || ''}>
                    {comment || '-'}
                </div>
            );
        }
    },
    {
        id: "formatted_nbr_jours",
        header: () => <div className="text-right">nombre des jours</div>,
        cell: ({ row }) => {
            const nbr_jours = parseFloat(row.getValue("nbr_jours"))
            const formatted = `${nbr_jours} day${nbr_jours !== 1 ? 's' : ''}`;
            return <div className="text-right font-medium">{formatted}</div>
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const demande = row.original
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem
                            onClick={() => handleDelete(demande.id.toString())}
                            className="text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                            Supprimer
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() =>{ setSelectedDemande(demande); setEditModelOpen(true);}}
                            className="text-yellow-600 hover:text-yellow-700 hover:bg-yellow-50"
                        >
                            Modifier
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                    </DropdownMenuContent>
                </DropdownMenu>
            )
        },
    },
] 