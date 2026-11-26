import { AddDemandeModal } from '@/components/AddDemandeModal';
import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { t } from '@/lib/i18n';
import {
    ColumnDef,
    ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
    VisibilityState,
} from '@tanstack/react-table';
import { Inbox } from 'lucide-react';
import * as React from 'react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    isAdmin?: boolean;
    users?: { id: number; name: string }[];
}

const ALL_STATUSES = 'all';

export function DataTable<TData, TValue>({ columns, data, isAdmin = false, users = [] }: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [isAddOpen, setIsAddOpen] = React.useState(false);

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
    });

    const statusFilter = (table.getColumn('etat')?.getFilterValue() as string) ?? ALL_STATUSES;

    return (
        <div>
            <div className="flex flex-wrap items-center gap-2 py-4">
                <Select
                    value={statusFilter}
                    onValueChange={(value) => table.getColumn('etat')?.setFilterValue(value === ALL_STATUSES ? undefined : value)}
                >
                    <SelectTrigger className="w-48" aria-label={t('Filtrer par état')}>
                        <SelectValue placeholder={t('Filtrer par état')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_STATUSES}>{t('Tous les états')}</SelectItem>
                        <SelectItem value="en attente">{t('En attente')}</SelectItem>
                        <SelectItem value="validée manager">{t('Validées par le responsable')}</SelectItem>
                        <SelectItem value="acceptée">{t('Acceptées')}</SelectItem>
                        <SelectItem value="refusée">{t('Refusées')}</SelectItem>
                        <SelectItem value="annulation demandée">{t('Annulation demandée')}</SelectItem>
                        <SelectItem value="annulée">{t('Annulées')}</SelectItem>
                    </SelectContent>
                </Select>
                <Button onClick={() => setIsAddOpen(true)}>{t('Nouvelle demande')}</Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="ms-auto">
                            {t('Colonnes')}
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
                                    {typeof column.columnDef.header === 'string' ? column.columnDef.header : column.id}
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
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
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
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="p-4">
                                    <EmptyState
                                        icon={Inbox}
                                        title={
                                            statusFilter && statusFilter !== ALL_STATUSES ? t('Aucune demande dans cet état') : t('Aucune demande')
                                        }
                                        description={
                                            statusFilter && statusFilter !== ALL_STATUSES
                                                ? t('Choisissez « Tous les états » dans le filtre pour tout afficher.')
                                                : t("Les demandes de congé apparaîtront ici dès qu'elles seront déposées.")
                                        }
                                        className="border-0"
                                    />
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="flex items-center justify-end gap-2 py-4">
                <span className="text-muted-foreground me-2 text-sm">
                    {t('Page')} {table.getState().pagination.pageIndex + 1} {t('sur')} {Math.max(table.getPageCount(), 1)}
                </span>
                <Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>
                    {t('Précédent')}
                </Button>
                <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                    {t('Suivant')}
                </Button>
            </div>
            <AddDemandeModal isOpen={isAddOpen} onClose={() => setIsAddOpen(false)} isAdmin={isAdmin} users={users} />
        </div>
    );
}
