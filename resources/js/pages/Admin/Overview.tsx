import { alertText, ContractAlert } from '@/components/employees/contracts';
import { ExpiryBadge } from '@/components/employees/DocumentFolder';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDays, LeaveStatus, parseISODate, STATUS_LABELS, TYPE_CONGE_LABELS, TypeConge } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Cake } from 'lucide-react';
import { ReactNode } from 'react';

interface Absence {
    id: number;
    name: string;
    department: string | null;
    type: TypeConge;
    start: string;
    end: string;
}

interface Waiting {
    id: number;
    name: string | null;
    type: TypeConge;
    start: string;
    end: string;
    days: number;
    status: LeaveStatus;
}

interface DepartmentRow {
    name: string | null;
    employees: number;
    usedDays: number;
    averageDays: number;
    unpaidDays: number;
}

interface OverviewProps {
    today: string;
    year: number;
    counts: { employees: number; offToday: number; pending: number; cancellations: number; missingHireDate: number };
    offToday: Absence[];
    upcoming: Absence[];
    waiting: Waiting[];
    departments: DepartmentRow[];
    /** Sick leave of 4+ calendar days whose CNSS dossier is not filed yet. */
    cnssPending: {
        id: number;
        user_id: number;
        name: string;
        start: string;
        end: string;
        calendar_days: number;
        deadline: string;
        overdue: boolean;
    }[];
    /** Fixed-term contracts and trial periods ending within 30 days. */
    contractAlerts: ContractAlert[];
    /** Folder documents expired or expiring within 30 days. */
    expiringDocuments: { id: number; user_id: number; name: string; label: string; expires_on: string; expiry: 'expired' | 'soon' | null }[];
    /** Birthdays today and in the next 30 days, soonest first (with the age they turn). */
    birthdays: { user_id: number; name: string; department: string | null; date: string; age: number }[];
    /** Today's clocking; null while attendance tracking has not started. */
    attendance: { present: number; late: number; on_leave: number; expected: number; not_yet: { id: number; name: string }[] } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: "Vue d'ensemble", href: '/admin/overview' },
];

const shortDate = (iso: string) => parseISODate(iso)?.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) ?? iso;
const range = (start: string, end: string) => (start === end ? shortDate(start) : `${shortDate(start)} → ${shortDate(end)}`);

function Stat({ label, value, hint, tone }: { label: string; value: number; hint?: ReactNode; tone?: 'warn' }) {
    return (
        <Card className="gap-1 py-4">
            <CardContent className="px-4">
                <div className="text-muted-foreground text-sm">{label}</div>
                <div className={`text-3xl font-bold tabular-nums ${tone === 'warn' && value > 0 ? 'text-orange-700 dark:text-orange-400' : ''}`}>
                    {value}
                </div>
                {hint && <div className="text-muted-foreground mt-1 text-xs">{hint}</div>}
            </CardContent>
        </Card>
    );
}

function AbsenceList({ items, empty }: { items: Absence[]; empty: string }) {
    if (items.length === 0) return <p className="text-muted-foreground text-sm">{empty}</p>;
    return (
        <ul className="divide-y">
            {items.map((item) => (
                <li key={item.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <div className="min-w-0">
                        <div className="truncate font-medium">{item.name}</div>
                        <div className="text-muted-foreground truncate text-xs">
                            {[TYPE_CONGE_LABELS[item.type], item.department].filter(Boolean).join(' · ')}
                        </div>
                    </div>
                    <span className="text-muted-foreground shrink-0 text-xs">{range(item.start, item.end)}</span>
                </li>
            ))}
        </ul>
    );
}

export default function Overview({
    today,
    year,
    counts,
    offToday,
    upcoming,
    waiting,
    departments,
    expiringDocuments,
    contractAlerts,
    cnssPending,
    birthdays,
    attendance,
}: OverviewProps) {
    const todayLabel = parseISODate(today)?.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vue d'ensemble" />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Vue d'ensemble</h1>
                    <p className="text-muted-foreground mt-1 text-sm first-letter:uppercase">{todayLabel}</p>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label="Absents aujourd'hui" value={counts.offToday} hint={`sur ${counts.employees} employés actifs`} />
                    <Stat label="Demandes à traiter" value={counts.pending} tone="warn" />
                    <Stat label="Annulations demandées" value={counts.cancellations} tone="warn" />
                    <Stat
                        label="Dossiers incomplets"
                        value={counts.missingHireDate}
                        tone="warn"
                        hint={
                            counts.missingHireDate > 0 ? (
                                <Link href="/admin/users" className="underline">
                                    Date d'embauche manquante
                                </Link>
                            ) : (
                                "Dates d'embauche renseignées"
                            )
                        }
                    />
                </div>

                {attendance && (
                    <Card className="gap-2 py-4">
                        <CardContent className="flex flex-col gap-2 px-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                <span className="font-semibold">Pointage du jour :</span> {attendance.present} présent
                                {attendance.present > 1 ? 's' : ''} sur {attendance.expected} attendus
                                {attendance.late > 0 && <span className="text-orange-700 dark:text-orange-400"> · {attendance.late} en retard</span>}
                                {attendance.not_yet.length > 0 &&
                                    ` · ${attendance.not_yet.length} pas encore pointé${attendance.not_yet.length > 1 ? 's' : ''}`}
                            </p>
                            <Link href="/admin/attendance" className="text-sm underline">
                                Voir le pointage
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {cnssPending.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-orange-700 dark:text-orange-400" /> Dossiers CNSS à déposer
                            </CardTitle>
                            <CardDescription>
                                Arrêts maladie de 4 jours ou plus : la CNSS indemnise à partir du 4ᵉ jour, dossier à déposer dans les 30 jours.
                                Marquez le dépôt depuis « Gérer les demandes ».
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {cnssPending.map((leave) => (
                                    <li key={leave.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                        <span>
                                            <Link href={`/admin/users/${leave.user_id}`} className="font-medium hover:underline">
                                                {leave.name}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">
                                                · {range(leave.start, leave.end)} ({leave.calendar_days} j)
                                            </span>
                                        </span>
                                        <span className={leave.overdue ? 'text-red-700 dark:text-red-400' : 'text-orange-700 dark:text-orange-400'}>
                                            {leave.overdue ? 'Délai dépassé depuis le' : 'À déposer avant le'} {shortDate(leave.deadline)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {contractAlerts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-orange-700 dark:text-orange-400" /> Contrats à suivre
                            </CardTitle>
                            <CardDescription>CDD, contrats ANAPEC, stages et périodes d'essai qui se terminent dans les 30 jours.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {contractAlerts.map((alert) => (
                                    <li
                                        key={`${alert.user_id}-${alert.kind}`}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                                    >
                                        <Link href={`/admin/users/${alert.user_id}`} className="font-medium hover:underline">
                                            {alert.name}
                                        </Link>
                                        <span
                                            className={
                                                alert.kind === 'ended' ? 'text-red-700 dark:text-red-400' : 'text-orange-700 dark:text-orange-400'
                                            }
                                        >
                                            {alertText(alert)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {expiringDocuments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-orange-700 dark:text-orange-400" /> Documents à renouveler
                            </CardTitle>
                            <CardDescription>Expirés ou expirant dans les 30 jours (CIN, carte de séjour, aptitude médicale…).</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {expiringDocuments.map((document) => (
                                    <li key={document.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                        <span>
                                            <Link href={`/admin/users/${document.user_id}`} className="font-medium hover:underline">
                                                {document.name}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">· {document.label}</span>
                                        </span>
                                        <ExpiryBadge expiry={document.expiry} expiresOn={document.expires_on} />
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Absents aujourd'hui <Badge variant="secondary">{offToday.length}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AbsenceList items={offToday} empty="Personne n'est absent aujourd'hui." />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Dans les 7 prochains jours</CardTitle>
                            <CardDescription>Départs en congé à venir.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <AbsenceList items={upcoming} empty="Aucun départ prévu." />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>En attente de décision</CardTitle>
                            <CardDescription>Les plus proches en premier.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {waiting.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Rien à traiter.</p>
                            ) : (
                                <ul className="divide-y">
                                    {waiting.map((item) => (
                                        <li key={item.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">{item.name}</div>
                                                <div className="text-muted-foreground truncate text-xs">
                                                    {TYPE_CONGE_LABELS[item.type]} · {formatDays(item.days)} j · {range(item.start, item.end)}
                                                </div>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    item.status === 'annulation demandée'
                                                        ? 'border-orange-500 text-orange-700 dark:text-orange-400'
                                                        : ''
                                                }
                                            >
                                                {STATUS_LABELS[item.status]}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Button asChild variant="outline" size="sm" className="w-full">
                                <Link href="/demandes">Gérer les demandes</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {birthdays.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Cake className="h-4 w-4" /> Anniversaires
                            </CardTitle>
                            <CardDescription>Aujourd'hui et dans les 30 prochains jours.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-x-8 sm:grid-cols-2">
                                {birthdays.map((birthday) => (
                                    <li key={birthday.user_id} className="flex items-center justify-between gap-3 border-b py-2 text-sm">
                                        <div className="min-w-0">
                                            <Link href={`/admin/users/${birthday.user_id}`} className="block truncate font-medium hover:underline">
                                                {birthday.name}
                                            </Link>
                                            <div className="text-muted-foreground truncate text-xs">
                                                {[`${birthday.age} ans`, birthday.department].filter(Boolean).join(' · ')}
                                            </div>
                                        </div>
                                        {birthday.date === today ? (
                                            <Badge>Aujourd'hui</Badge>
                                        ) : (
                                            <span className="text-muted-foreground shrink-0 text-xs">{shortDate(birthday.date)}</span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Congés pris en {year} par département</CardTitle>
                        <CardDescription>
                            Jours décomptés du solde (congé annuel, part déduite des mariages et décès) et jours sans solde, congés acceptés
                            uniquement, employés actifs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Département</TableHead>
                                    <TableHead className="text-right">Employés</TableHead>
                                    <TableHead className="text-right">Jours décomptés</TableHead>
                                    <TableHead className="text-right">Moyenne / employé</TableHead>
                                    <TableHead className="text-right">Sans solde</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {departments.map((row) => (
                                    <TableRow key={row.name ?? 'none'}>
                                        <TableCell className={row.name ? 'font-medium' : 'text-muted-foreground italic'}>
                                            {row.name ?? 'Sans département'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{row.employees}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatDays(row.usedDays)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatDays(row.averageDays)}</TableCell>
                                        <TableCell
                                            className={`text-right tabular-nums ${row.unpaidDays > 0 ? 'text-red-700 dark:text-red-400' : ''}`}
                                        >
                                            {formatDays(row.unpaidDays)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
