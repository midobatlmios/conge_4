import * as React from "react"
import { Button } from "@/components/ui/button"
import { AddDemandeModal } from "@/components/AddDemandeModal"
import {
  ColumnDef,
  flexRender,
  SortingState,
  VisibilityState,
  getPaginationRowModel,
  ColumnFiltersState,
  getFilteredRowModel,
  getSortedRowModel,
  getCoreRowModel,
  useReactTable,
} from "@tanstack/react-table"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface DataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[]
  data: TData[]
  isAdmin?: boolean
  users?: { id: number; name: string }[]
}

const ALL_STATUSES = "all"

export function DataTable<TData, TValue>({
  columns,
  data,
  isAdmin = false,
  users = [],
}: DataTableProps<TData, TValue>) {
  const [sorting, setSorting] = React.useState<SortingState>([])
  const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([])
  const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({})
  const [isAddOpen, setIsAddOpen] = React.useState(false)

  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onSortingChange: setSorting,
    getSortedRowModel: getSortedRowModel(),
    onColumnFiltersChange: setColumnFilters,
    getFilteredRowModel: getFilteredRowModel(),
    onColumnVisibilityChange: setColumnVisibility,
    state: {
      sorting,
      columnFilters,
      columnVisibility,
    },
  })

  const statusFilter = (table.getColumn("etat")?.getFilterValue() as string) ?? ALL_STATUSES

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2 py-4">
        <Select
          value={statusFilter}
          onValueChange={(value) =>
            table.getColumn("etat")?.setFilterValue(value === ALL_STATUSES ? undefined : value)
          }
        >
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Filtrer par état" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL_STATUSES}>Tous les états</SelectItem>
            <SelectItem value="en attente">En attente</SelectItem>
            <SelectItem value="acceptée">Acceptées</SelectItem>
            <SelectItem value="refusée">Refusées</SelectItem>
          </SelectContent>
        </Select>
        <Button onClick={() => setIsAddOpen(true)}>Nouvelle demande</Button>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="ml-auto">
              Colonnes
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {table
              .getAllColumns()
              .filter((column) => column.getCanHide())
              .map((column) => (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) => column.toggleVisibility(!!value)}
                >
                  {typeof column.columnDef.header === "string" ? column.columnDef.header : column.id}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id}>
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  Aucune demande.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
      <div className="flex items-center justify-end gap-2 py-4">
        <span className="mr-2 text-sm text-muted-foreground">
          Page {table.getState().pagination.pageIndex + 1} sur {Math.max(table.getPageCount(), 1)}
        </span>
        <Button
          variant="outline"
          size="sm"
          onClick={() => table.previousPage()}
          disabled={!table.getCanPreviousPage()}
        >
          Précédent
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={() => table.nextPage()}
          disabled={!table.getCanNextPage()}
        >
          Suivant
        </Button>
      </div>
      <AddDemandeModal isOpen={isAddOpen} onClose={() => setIsAddOpen(false)} isAdmin={isAdmin} users={users} />
    </div>
  )
}
