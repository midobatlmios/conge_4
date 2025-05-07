// columns.tsx
"use client";

import { ColumnDef } from "@tanstack/react-table";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Demande } from "@/types";

export const columns = (
    onDelete: (id: number) => void,
    onEdit: (isOpen: boolean) => void,
    setSelectedDemande: (demande: Demande) => void,
    isAdmin: boolean
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
        accessorKey: "date_demande",
        header: "Date de demande",
        cell: ({ row }) => {
            const date = new Date(row.getValue("date_demande"));
            return date.toLocaleDateString('fr-FR');
        }
    },
    {
        accessorKey: "date_debut",
        header: "Date de début",
        cell: ({ row }) => {
            const date = new Date(row.getValue("date_debut"));
            return date.toLocaleDateString('fr-FR');
        }
    },
    {
        accessorKey: "date_fin",
        header: "Date de fin",
        cell: ({ row }) => {
            const date = new Date(row.getValue("date_fin"));
            return date.toLocaleDateString('fr-FR');
        }
    },
    {
        accessorKey: "type_conge",
        header: "Type de congé",
        cell: ({ row }) => {
            const type = row.getValue("type_conge") as string;
            return (
                <div className="font-medium">
                    {type === "mariage" && "Congé de Mariage"}
                    {type === "naissance" && "Congé de Naissance"}
                    {type === "deces" && "Congé de Décès"}
                    {type === "sans_solde" && "Congé sans solde"}
                    {type === "recuperation" && "Récupération"}
                </div>
            );
        },
    },
    {
        accessorKey: "nbr_jours",
        header: "Nombre de jours",
        cell: ({ row }) => {
            const nbr_jours = parseFloat(row.getValue("nbr_jours"));
            return <div className="text-right font-medium">{nbr_jours} jour{nbr_jours !== 1 ? 's' : ''}</div>;
        }
    },
    {
        accessorKey: "etat",
        header: "État",
        cell: ({ row }) => {
            const etat = row.getValue("etat") as string;
            return (
                <div className={`font-medium ${
                    etat === "en attente" ? "text-yellow-500" :
                    etat === "acceptée" ? "text-green-500" :
                    "text-red-500"
                }`}>
                    {etat}
                </div>
            );
        },
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
        id: "actions",
        cell: ({ row }) => {
            const demande = row.original;
            // Hide edit button for certain types unless user is admin
            const hideEdit = !isAdmin && ["mariage", "naissance", "deces"].includes(demande.type_conge);
            return (
                <div className="flex gap-2">
                    {!hideEdit && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-yellow-600 hover:text-yellow-700 hover:bg-yellow-50"
                            onClick={() => {
                                setSelectedDemande(demande);
                                onEdit(true);
                            }}
                        >
                            Modifier
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        size="sm"
                        className="text-red-600 hover:text-red-700 hover:bg-red-50"
                        onClick={() => onDelete(demande.id)}
                    >
                        Supprimer
                    </Button>
                </div>
            );
        },
    },
]; 