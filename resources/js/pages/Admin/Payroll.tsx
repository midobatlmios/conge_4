import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDays } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';

interface PayrollRow {
    id: number;
    matricule: string | null;
    name: string;
    department: string | null;
    job_title: string | null;
    annual: number;
    exceptional: number;
    unpaid: number;
    recovery: number;
    sick: number;
    total: number;
    balance: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Export paie', href: '/admin/payroll' },
];

const DAY_COLUMNS: { key: keyof PayrollRow; label: string }[] = [
    { key: 'annual', label: 'Congé annuel' },
    { key: 'exceptional', label: 'Exceptionnels payés' },
    { key: 'unpaid', label: 'Sans solde (à déduire)' },
    { key: 'recovery', label: 'Récupération' },
    { key: 'sick', label: 'Maladie' },
    { key: 'total', label: 'Total' },
];

const dash = <span className="text-muted-foreground">—</span>;

export default function Payroll({ month, rows }: { month: string; rows: PayrollRow[] }) {
    const [year, monthNumber] = month.split('-').map(Number);
    const title = new Date(year, monthNumber - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    const withLeave = rows.filter((row) => row.total > 0).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Export paie" />
            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-10">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Export paie</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Jours ouvrés de congé accepté par employé pour le mois choisi. Les jours « sans solde » sont à déduire du salaire.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="month">Mois</Label>
                            <Input
                                id="month"
                                type="month"
                                value={month}
                                onChange={(e) => e.target.value && router.get('/admin/payroll', { month: e.target.value }, { preserveScroll: true })}
                                className="w-44"
                            />
                        </div>
                        <Button asChild className="bg-green-700 text-white hover:bg-green-800">
                            {/* A plain link: the browser downloads the file. */}
                            <a href={`/admin/payroll/export?month=${month}`}>
                                <Download className="me-1 h-4 w-4" /> Télécharger Excel
                            </a>
                        </Button>
                    </div>
                </div>

                <p className="text-sm">
                    <span className="font-medium capitalize">{title}</span> : {rows.length} employé{rows.length > 1 ? 's' : ''}, dont {withLeave} avec
                    des absences.
                </p>

                {rows.length === 0 ? (
                    <EmptyState
                        icon={FileSpreadsheet}
                        title="Aucun employé pour ce mois"
                        description="Aucun employé n'était en poste ce mois-là. Choisissez un autre mois."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-md border" tabIndex={0} role="region" aria-label="Tableau de paie">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Matricule</TableHead>
                                    <TableHead>Nom</TableHead>
                                    <TableHead>Département</TableHead>
                                    {DAY_COLUMNS.map((column) => (
                                        <TableHead key={column.key} className="text-end">
                                            {column.label}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-end">Solde fin de mois</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.matricule ?? dash}</TableCell>
                                        <TableCell>
                                            <div>{row.name}</div>
                                            {row.job_title && <div className="text-muted-foreground text-xs">{row.job_title}</div>}
                                        </TableCell>
                                        <TableCell>{row.department ?? dash}</TableCell>
                                        {DAY_COLUMNS.map((column) => (
                                            <TableCell
                                                key={column.key}
                                                className={`text-end tabular-nums ${column.key === 'unpaid' && row.unpaid > 0 ? 'font-bold text-red-700 dark:text-red-400' : ''} ${column.key === 'total' ? 'font-medium' : ''}`}
                                            >
                                                {row[column.key] ? formatDays(row[column.key] as number) : dash}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-end tabular-nums">{formatDays(row.balance)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell colSpan={3} className="font-bold">
                                        Total
                                    </TableCell>
                                    {DAY_COLUMNS.map((column) => (
                                        <TableCell key={column.key} className="text-end font-bold tabular-nums">
                                            {formatDays(rows.reduce((sum, row) => sum + (row[column.key] as number), 0))}
                                        </TableCell>
                                    ))}
                                    <TableCell />
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
