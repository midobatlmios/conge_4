import { hours, overtimeTotal, Schedule, Totals } from '@/components/attendance/attendance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEvent } from 'react';

type Row = Totals & { id: number; name: string; department: string | null };
type Person = { id: number; name: string; clock_in: string | null; late: number };

interface AttendanceProps {
    month: string;
    departmentId: number | null;
    departmentOptions: { id: number; name: string }[];
    schedule: Schedule;
    rows: Row[];
    /** Null while tracking has not started. */
    today: { present: number; late: number; on_leave: number; expected: number; not_yet: Person[]; late_list: Person[] } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Pointage', href: '/admin/attendance' },
];
const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

/** Company schedule and the day tracking starts (Admin and RH). */
function ScheduleForm({ schedule }: { schedule: Schedule }) {
    const form = useForm({
        work_start: schedule.start,
        lunch_start: schedule.lunch_start,
        lunch_end: schedule.lunch_end,
        work_end: schedule.end,
        late_tolerance_minutes: String(schedule.tolerance),
        attendance_started_on: schedule.since ?? '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/admin/attendance/schedule', { preserveScroll: true });
    };
    const field = (key: keyof typeof form.data, label: string, type = 'time') => (
        <div className="space-y-1">
            <Label htmlFor={`schedule-${key}`}>{label}</Label>
            <Input id={`schedule-${key}`} type={type} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            {form.errors[key] && <p className="text-sm text-red-700 dark:text-red-400">{form.errors[key]}</p>}
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-6">
                {field('work_start', 'Début de journée')}
                {field('lunch_start', 'Début de pause')}
                {field('lunch_end', 'Fin de pause')}
                {field('work_end', 'Fin de journée')}
                {field('late_tolerance_minutes', 'Tolérance de retard (min)', 'number')}
                {field('attendance_started_on', 'Pointage à partir du', 'date')}
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-muted-foreground text-xs">
                    Jours travaillés : du lundi au vendredi, hors jours fériés. Les absences ne sont comptées qu'à partir de la date de début du
                    pointage ; sans date, le pointage est désactivé.
                </p>
                <Button type="submit" disabled={form.processing}>
                    Enregistrer
                </Button>
            </div>
        </form>
    );
}

/** HR attendance: today, the month per employee, Excel export and the schedule. */
export default function Attendance({ month, departmentId, departmentOptions, schedule, rows, today }: AttendanceProps) {
    const can = usePermissions();
    const filter = (params: { month?: string; department?: number | null }) => {
        const department = params.department === undefined ? departmentId : params.department;
        router.get('/admin/attendance', { month: params.month ?? month, ...(department ? { department } : {}) }, { preserveScroll: true });
    };
    const exportUrl = `/admin/attendance/export?month=${month}${departmentId ? `&department=${departmentId}` : ''}`;
    const sum = (key: keyof Totals) => rows.reduce((total, row) => total + row[key], 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pointage" />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Pointage</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Horaires : {schedule.start}–{schedule.lunch_start} et {schedule.lunch_end}–{schedule.end}, retard après{' '}
                            {schedule.tolerance} min. Heures supplémentaires à titre indicatif (taux du Code du travail).
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            type="month"
                            aria-label="Mois"
                            className="w-auto"
                            value={month}
                            onChange={(e) => e.target.value && filter({ month: e.target.value })}
                        />
                        <select
                            aria-label="Département"
                            className={selectClass}
                            value={departmentId ?? ''}
                            onChange={(e) => filter({ department: e.target.value ? Number(e.target.value) : null })}
                        >
                            <option value="">Tous les départements</option>
                            {departmentOptions.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download className="me-2 h-4 w-4" /> Excel
                            </a>
                        </Button>
                    </div>
                </div>

                {today === null ? (
                    <p className="rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300">
                        Le pointage n'est pas activé : choisissez la date à partir de laquelle il compte, dans « Horaires » ci-dessous. Les employés
                        verront alors le bouton « Pointer l'arrivée » sur leur espace.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card className="gap-1 py-4">
                            <CardContent className="px-4">
                                <p className="text-muted-foreground text-sm">Présents aujourd'hui</p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">
                                    {today.present} <span className="text-muted-foreground text-base font-normal">/ {today.expected} attendus</span>
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">{today.on_leave} en congé</p>
                            </CardContent>
                        </Card>
                        <Card className="gap-1 py-4">
                            <CardContent className="px-4">
                                <p className="text-muted-foreground text-sm">En retard aujourd'hui ({today.late})</p>
                                <p className="mt-1 text-sm">
                                    {today.late_list.length === 0
                                        ? 'Personne'
                                        : today.late_list.map((p) => `${p.name} (${p.clock_in}, +${p.late} min)`).join(', ')}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="gap-1 py-4">
                            <CardContent className="px-4">
                                <p className="text-muted-foreground text-sm">Pas encore pointé ({today.not_yet.length})</p>
                                <p className="mt-1 text-sm">
                                    {today.not_yet.length === 0 ? 'Tout le monde a pointé.' : today.not_yet.map((p) => p.name).join(', ')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Récapitulatif du mois</CardTitle>
                        <CardDescription>
                            Heures prévues et travaillées, retards, absences non justifiées et heures supplémentaires par taux.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {rows.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Aucun pointage à afficher pour ce mois.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employé</TableHead>
                                        <TableHead className="text-end">Prévu</TableHead>
                                        <TableHead className="text-end">Travaillé</TableHead>
                                        <TableHead className="text-end">Absences</TableHead>
                                        <TableHead className="text-end">Retards</TableHead>
                                        <TableHead className="text-end">Départs non pointés</TableHead>
                                        <TableHead className="text-end">H. sup. +25 %</TableHead>
                                        <TableHead className="text-end">+50 %</TableHead>
                                        <TableHead className="text-end">+100 %</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link href={`/admin/users/${row.id}`} className="font-medium hover:underline">
                                                    {row.name}
                                                </Link>
                                                {row.department && <span className="text-muted-foreground block text-xs">{row.department}</span>}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">{hours(row.expected)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{hours(row.worked)}</TableCell>
                                            <TableCell className="text-end tabular-nums">
                                                {row.absent_days > 0 ? <Badge variant="destructive">{row.absent_days} j</Badge> : '—'}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">
                                                {row.late_days > 0 ? `${row.late_days} (${row.late_minutes} min)` : '—'}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">{row.missing_out || '—'}</TableCell>
                                            <TableCell className="text-end tabular-nums">{hours(row.overtime_25)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{hours(row.overtime_50)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{hours(row.overtime_100)}</TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow className="font-semibold">
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-end tabular-nums">{hours(sum('expected'))}</TableCell>
                                        <TableCell className="text-end tabular-nums">{hours(sum('worked'))}</TableCell>
                                        <TableCell className="text-end tabular-nums">{sum('absent_days')} j</TableCell>
                                        <TableCell className="text-end tabular-nums">{sum('late_days')}</TableCell>
                                        <TableCell className="text-end tabular-nums">{sum('missing_out')}</TableCell>
                                        <TableCell className="text-end tabular-nums" colSpan={3}>
                                            {hours(
                                                overtimeTotal({
                                                    overtime_25: sum('overtime_25'),
                                                    overtime_50: sum('overtime_50'),
                                                    overtime_100: sum('overtime_100'),
                                                }),
                                            )}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {can.manage_hr && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Horaires</CardTitle>
                            <CardDescription>
                                Un horaire pour toute l'entreprise : il sert à compter les retards et les heures supplémentaires.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScheduleForm schedule={schedule} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
