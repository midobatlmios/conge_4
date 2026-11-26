import {
    AttendanceDay,
    Correction,
    hours,
    overtimeTotal,
    Schedule,
    shortDay,
    STATUS_CLASSES,
    STATUS_LABELS,
    TodayState,
    Totals,
} from '@/components/attendance/attendance';
import ClockCard from '@/components/attendance/ClockCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { TYPE_CONGE_LABELS, TypeConge } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface AttendanceProps {
    month: string;
    today: TodayState;
    days: AttendanceDay[];
    totals: Totals;
    schedule: Schedule;
    corrections: Correction[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Mes pointages', href: '/pointage' },
];

const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR');

function Stat({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
    return (
        <Card className="gap-1 py-4">
            <CardContent className="px-4">
                <p className="text-muted-foreground text-sm">{label}</p>
                <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
                {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
            </CardContent>
        </Card>
    );
}

/** Correction request for a day: forgotten or wrong clocking. */
function CorrectionForm({ day, onDone }: { day: AttendanceDay; onDone: () => void }) {
    const form = useForm({ date: day.date, clock_in: day.clock_in ?? '', clock_out: day.clock_out ?? '', reason: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/pointage/corrections', { preserveScroll: true, onSuccess: onDone });
    };
    const error = (field: keyof typeof form.errors) =>
        form.errors[field] && <p className="text-sm text-red-700 dark:text-red-400">{form.errors[field]}</p>;

    return (
        <form onSubmit={submit} className="space-y-3 rounded-md border border-dashed p-4">
            <h3 className="text-sm font-semibold">Corriger le {frDate(day.date)}</h3>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="fix-in">Arrivée</Label>
                    <Input id="fix-in" type="time" value={form.data.clock_in} onChange={(e) => form.setData('clock_in', e.target.value)} required />
                    {error('clock_in')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="fix-out">Départ</Label>
                    <Input id="fix-out" type="time" value={form.data.clock_out} onChange={(e) => form.setData('clock_out', e.target.value)} />
                    {error('clock_out')}
                </div>
                <div className="space-y-1 sm:col-span-2">
                    <Label htmlFor="fix-reason">Motif</Label>
                    <Textarea
                        id="fix-reason"
                        rows={2}
                        placeholder="Oubli de pointage, réunion chez un client…"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        required
                    />
                    {error('reason')}
                    {error('date')}
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone} disabled={form.processing}>
                    Annuler
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Envoyer la correction
                </Button>
            </div>
        </form>
    );
}

/** The employee's own clocking: today, the month day by day, and corrections. */
export default function Attendance({ month, today, days, totals, schedule, corrections }: AttendanceProps) {
    const [fixing, setFixing] = useState<AttendanceDay | null>(null);
    const overtime = overtimeTotal(totals);
    // Days before tracking started (or after today) are left out.
    const visibleDays = days.filter((d) => d.status !== 'not_tracked' && d.status !== 'future');
    const pendingDates = new Set(corrections.filter((c) => c.status === 'pending').map((c) => c.date));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mes pointages" />
            <div className="container mx-auto max-w-5xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Mes pointages</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Horaires : {schedule.start}–{schedule.lunch_start} et {schedule.lunch_end}–{schedule.end}. Retard compté après{' '}
                            {schedule.tolerance} min.
                        </p>
                    </div>
                    <Input
                        type="month"
                        aria-label="Mois"
                        className="w-auto"
                        value={month}
                        onChange={(e) => e.target.value && router.get('/pointage', { month: e.target.value }, { preserveScroll: true })}
                    />
                </div>

                {today.tracking ? (
                    <ClockCard state={today} />
                ) : (
                    <p className="text-muted-foreground rounded-md border px-4 py-3 text-sm">
                        Le pointage n'est pas encore activé par le service RH
                        {schedule.since ? ` (il commence le ${frDate(schedule.since)})` : ''}.
                    </p>
                )}

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label="Heures travaillées" value={hours(totals.worked)} hint={`sur ${hours(totals.expected)} prévues`} />
                    <Stat label="Retards" value={totals.late_days} hint={totals.late_minutes ? `${totals.late_minutes} min au total` : undefined} />
                    <Stat
                        label="Absences non justifiées"
                        value={totals.absent_days}
                        hint={totals.missing_out ? `${totals.missing_out} départ(s) non pointé(s)` : undefined}
                    />
                    <Stat
                        label="Heures supplémentaires"
                        value={hours(overtime)}
                        hint={
                            overtime
                                ? `+25 % : ${hours(totals.overtime_25)} · +50 % : ${hours(totals.overtime_50)} · +100 % : ${hours(totals.overtime_100)}`
                                : undefined
                        }
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Jour par jour</CardTitle>
                        <CardDescription>
                            Un oubli ou une erreur ? Demandez une correction : votre responsable ou le service RH la valide.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {fixing && <CorrectionForm key={fixing.date} day={fixing} onDone={() => setFixing(null)} />}
                        {visibleDays.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Aucun jour à afficher pour ce mois.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Jour</TableHead>
                                            <TableHead>Arrivée</TableHead>
                                            <TableHead>Départ</TableHead>
                                            <TableHead className="text-right">Travaillé</TableHead>
                                            <TableHead>Remarque</TableHead>
                                            <TableHead className="sr-only">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {visibleDays.map((day) => {
                                            const ot = day.overtime['25'] + day.overtime['50'] + day.overtime['100'];
                                            const notes = [
                                                day.status === 'leave' || day.leave_part
                                                    ? `${TYPE_CONGE_LABELS[day.leave as TypeConge] ?? 'Congé'}${day.leave_part && day.leave_part !== 'full' ? ' (demi-journée)' : ''}`
                                                    : null,
                                                day.late ? `Retard ${day.late} min` : null,
                                                ot ? `H. sup. ${hours(ot)}` : null,
                                                day.source === 'correction' ? 'Corrigé' : null,
                                            ].filter(Boolean);
                                            const canFix =
                                                !pendingDates.has(day.date) && ['absent', 'missing_out', 'present', 'not_yet'].includes(day.status);
                                            return (
                                                <TableRow key={day.date} className={day.working_day ? '' : 'text-muted-foreground'}>
                                                    <TableCell className="whitespace-nowrap first-letter:uppercase">{shortDay(day.date)}</TableCell>
                                                    <TableCell className="tabular-nums">{day.clock_in ?? '—'}</TableCell>
                                                    <TableCell className="tabular-nums">{day.clock_out ?? '—'}</TableCell>
                                                    <TableCell className="text-right tabular-nums">{hours(day.worked)}</TableCell>
                                                    <TableCell className="text-sm">
                                                        {STATUS_LABELS[day.status] && day.status !== 'present' && day.status !== 'leave' && (
                                                            <span className={`mr-2 ${STATUS_CLASSES[day.status] ?? ''}`}>
                                                                {STATUS_LABELS[day.status]}
                                                            </span>
                                                        )}
                                                        <span className="text-muted-foreground">{notes.join(' · ')}</span>
                                                        {pendingDates.has(day.date) && (
                                                            <Badge variant="outline" className="ml-2">
                                                                Correction en attente
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {canFix && (
                                                            <Button variant="ghost" size="sm" onClick={() => setFixing(day)}>
                                                                Corriger
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {corrections.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Mes demandes de correction</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {corrections.map((c) => (
                                    <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                        <span>
                                            <span className="font-medium">{frDate(c.date)}</span> · {c.clock_in}
                                            {c.clock_out ? `–${c.clock_out}` : ''} · <span className="text-muted-foreground">{c.reason}</span>
                                            {c.refusal_reason && (
                                                <span className="block text-xs text-red-700 dark:text-red-400">
                                                    Motif du refus : {c.refusal_reason}
                                                </span>
                                            )}
                                        </span>
                                        <Badge variant={c.status === 'pending' ? 'outline' : 'secondary'}>
                                            {c.status_label}
                                            {c.decided_by ? ` · ${c.decided_by}` : ''}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
