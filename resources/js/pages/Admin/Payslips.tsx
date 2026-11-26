import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Download, Lock, RefreshCw, Send, Undo2, Wallet } from 'lucide-react';
import { useState } from 'react';

type Hours = { '25': number; '50': number; '100': number };
type Row = {
    id: number;
    user_id: number;
    name: string;
    overtime_hours: Hours;
    overtime_from_attendance: Hours;
    overtime_adjusted: boolean;
    base: number;
    seniority: number;
    overtime: number;
    gross: number;
    cnss: number;
    amo: number;
    ir: number;
    net: number;
    employer_total: number;
    cost: number;
    employed_days: number;
    month_days: number;
};
type Run = {
    id: number;
    status: 'draft' | 'ready' | 'locked';
    status_label: string;
    prepared_by: string | null;
    prepared_by_id: number | null;
    prepared_at: string | null;
    locked_by: string | null;
    locked_at: string | null;
};

interface PayslipsProps {
    month: string;
    run: Run | null;
    payslips: Row[];
    missing: { id: number; name: string }[];
    runs: { month: string; status_label: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Bulletins de paie', href: '/admin/payslips' },
];
const dh = (v: number) => v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const monthLabel = (ym: string) => new Date(`${ym}-01T00:00:00`).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
/** "de juin 2026", "d’octobre 2026". */
const ofMonth = (ym: string) => {
    const label = monthLabel(ym);
    return /^[aeiouy]/i.test(label) ? `d’${label}` : `de ${label}`;
};
const statusClass: Record<Run['status'], string> = {
    draft: 'border-blue-500 text-blue-700 dark:text-blue-400',
    ready: 'border-orange-500 text-orange-700 dark:text-orange-400',
    locked: 'border-green-600 text-green-700 dark:text-green-400',
};

/** Overtime hours of one payslip, editable while the month is in preparation. */
function OvertimeCell({ row, editable }: { row: Row; editable: boolean }) {
    const [hours, setHours] = useState<Hours>(row.overtime_hours);
    const changed = (['25', '50', '100'] as const).some((k) => Number(hours[k]) !== row.overtime_hours[k]);
    const total = row.overtime_hours['25'] + row.overtime_hours['50'] + row.overtime_hours['100'];

    if (!editable) return <span className="tabular-nums">{total ? `${dh(total)} h` : '—'}</span>;

    return (
        <div className="flex items-center gap-1">
            {(['25', '50', '100'] as const).map((k) => (
                <Input
                    key={k}
                    aria-label={`Heures à +${k} % pour ${row.name}`}
                    title={`+${k} % (pointage : ${row.overtime_from_attendance[k]} h)`}
                    type="number"
                    min={0}
                    step="0.25"
                    className="h-8 w-16 px-1 text-right"
                    value={hours[k]}
                    onChange={(e) => setHours({ ...hours, [k]: e.target.value === '' ? 0 : Number(e.target.value) })}
                />
            ))}
            {changed && (
                <Button
                    size="sm"
                    className="h-8"
                    onClick={() =>
                        router.put(`/admin/payslips/${row.id}/overtime`, { overtime_hours: hours }, { preserveScroll: true, onError: toastErrors })
                    }
                >
                    OK
                </Button>
            )}
            {row.overtime_adjusted && !changed && (
                <Badge variant="outline" title="Modifié par rapport au pointage">
                    modifié
                </Badge>
            )}
        </div>
    );
}

/** Monthly payroll: prepare, adjust overtime, send for validation, validate (RH / Admin), payslip PDFs. */
export default function Payslips({ month, run, payslips, missing, runs }: PayslipsProps) {
    const can = usePermissions();
    const { auth } = usePage<SharedData>().props;
    const draft = run?.status === 'draft';
    const total = (key: keyof Row) => payslips.reduce((sum, row) => sum + (row[key] as number), 0);
    const put = (url: string) => router.put(url, {}, { preserveScroll: true, onError: toastErrors });

    const lock = async () => {
        const ok = await confirmAction({
            title: `Valider la paie ${ofMonth(month)} ?`,
            description: `${payslips.length} bulletins, net total ${dh(total('net'))} DH. Les bulletins deviennent définitifs et visibles par les employés.`,
            confirmLabel: 'Valider la paie',
        });
        if (ok) put(`/admin/payroll-runs/${run!.id}/lock`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bulletins de paie" />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Bulletins de paie</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Paie prépare le mois, RH ou l'administrateur le valide. Taux CNSS, AMO et IR 2025 : à faire vérifier par votre comptable.
                        </p>
                    </div>
                    <Input
                        type="month"
                        aria-label="Mois"
                        className="w-auto"
                        value={month}
                        onChange={(e) => e.target.value && router.get('/admin/payslips', { month: e.target.value })}
                    />
                </div>

                {runs.length > 0 && (
                    <div className="flex flex-wrap gap-2 text-sm">
                        {runs.map((r) => (
                            <Link
                                key={r.month}
                                href={`/admin/payslips?month=${r.month}`}
                                className={`rounded-md border px-2 py-1 ${r.month === month ? 'bg-muted font-medium' : ''}`}
                            >
                                <span className="first-letter:uppercase">{monthLabel(r.month)}</span> · {r.status_label}
                            </Link>
                        ))}
                    </div>
                )}

                {run === null ? (
                    <Card>
                        <CardContent className="py-6">
                            <EmptyState
                                icon={Wallet}
                                title={`Paie ${ofMonth(month)} non préparée`}
                                description="Un bulletin est créé pour chaque employé ayant un salaire sur son contrat, avec la prime d'ancienneté et les heures supplémentaires du pointage."
                            />
                            <div className="mt-4 flex justify-center">
                                <Button onClick={() => router.post('/admin/payslips/prepare', { month }, { onError: toastErrors })}>
                                    Préparer la paie {ofMonth(month)}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1 text-sm">
                                    <Badge variant="outline" className={statusClass[run.status]}>
                                        {run.status_label}
                                    </Badge>
                                    <p className="text-muted-foreground">
                                        {run.prepared_by && `Préparée par ${run.prepared_by}`}
                                        {run.locked_by &&
                                            ` · validée par ${run.locked_by} le ${new Date(run.locked_at!).toLocaleDateString('fr-FR')}`}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {draft && (
                                        <>
                                            <Button variant="outline" onClick={() => put(`/admin/payroll-runs/${run.id}/recalculate`)}>
                                                <RefreshCw className="mr-2 h-4 w-4" /> Recalculer
                                            </Button>
                                            <Button onClick={() => put(`/admin/payroll-runs/${run.id}/submit`)}>
                                                <Send className="mr-2 h-4 w-4" /> Envoyer pour validation
                                            </Button>
                                        </>
                                    )}
                                    {run.status === 'ready' && (
                                        <>
                                            <Button variant="outline" onClick={() => put(`/admin/payroll-runs/${run.id}/reopen`)}>
                                                <Undo2 className="mr-2 h-4 w-4" /> Rouvrir
                                            </Button>
                                            {can.manage_hr && run.prepared_by_id !== auth.user.id && (
                                                <Button onClick={lock}>
                                                    <Lock className="mr-2 h-4 w-4" /> Valider la paie
                                                </Button>
                                            )}
                                        </>
                                    )}
                                    {run.status === 'locked' && (
                                        <span className="flex items-center gap-1 text-sm text-green-700 dark:text-green-400">
                                            <CheckCircle2 className="h-4 w-4" /> Bulletins définitifs, visibles par les employés
                                        </span>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {run.status === 'ready' && can.manage_hr && run.prepared_by_id === auth.user.id && (
                            <p className="text-muted-foreground text-sm">
                                Vous avez préparé cette paie : une autre personne RH ou l'administrateur doit la valider.
                            </p>
                        )}
                        {run.status === 'ready' && !can.manage_hr && (
                            <p className="text-muted-foreground text-sm">En attente de validation par RH ou l'administrateur.</p>
                        )}

                        {missing.length > 0 && (
                            <div
                                role="status"
                                className="flex items-start gap-3 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300"
                            >
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <p>
                                    Sans salaire sur leur contrat, donc sans bulletin :{' '}
                                    {missing.map((m, i) => (
                                        <span key={m.id}>
                                            {i > 0 && ', '}
                                            <Link href={`/admin/users/${m.id}`} className="underline">
                                                {m.name}
                                            </Link>
                                        </span>
                                    ))}
                                    . Complétez le contrat puis « Recalculer ».
                                </p>
                            </div>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {payslips.length} bulletin{payslips.length > 1 ? 's' : ''}
                                </CardTitle>
                                <CardDescription>
                                    Net total {dh(total('net'))} DH · brut {dh(total('gross'))} DH · coût employeur {dh(total('cost'))} DH.
                                    {draft && ' Heures supplémentaires (+25 / +50 / +100 %) reprises du pointage, modifiables.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employé</TableHead>
                                            <TableHead className="text-right">Base</TableHead>
                                            <TableHead className="text-right">Ancienneté</TableHead>
                                            <TableHead>H. sup.</TableHead>
                                            <TableHead className="text-right">Brut</TableHead>
                                            <TableHead className="text-right">CNSS + AMO</TableHead>
                                            <TableHead className="text-right">IR</TableHead>
                                            <TableHead className="text-right">Net</TableHead>
                                            <TableHead className="sr-only">PDF</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payslips.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell>
                                                    <Link href={`/admin/users/${row.user_id}`} className="font-medium hover:underline">
                                                        {row.name}
                                                    </Link>
                                                    {row.employed_days < row.month_days && (
                                                        <span className="text-muted-foreground block text-xs">
                                                            {row.employed_days} / {row.month_days} jours
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">{dh(row.base)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{row.seniority ? dh(row.seniority) : '—'}</TableCell>
                                                <TableCell>
                                                    <OvertimeCell key={JSON.stringify(row.overtime_hours)} row={row} editable={draft} />
                                                    {row.overtime > 0 && (
                                                        <span className="text-muted-foreground block text-xs">{dh(row.overtime)} DH</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">{dh(row.gross)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{dh(row.cnss + row.amo)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{dh(row.ir)}</TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">{dh(row.net)}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <a href={`/bulletins/${row.id}/pdf`} aria-label={`Bulletin de ${row.name} (PDF)`}>
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
