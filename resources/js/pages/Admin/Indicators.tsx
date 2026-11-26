import MonthlyBars from '@/components/indicators/MonthlyBars';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { ReactNode } from 'react';

type Month = {
    month: number;
    label: string;
    headcount: number;
    hires: number;
    departures: number;
    theoretical_days: number;
    sick_days: number;
    unpaid_days: number;
    planned_days: number;
    sick_stops: number;
    absenteeism: number | null;
    /** Monthly gross salaries of those employed on the last day (DH). */
    payroll: number;
    missing_salaries: number;
};

type Summary = {
    headcount_start: number;
    headcount_end: number;
    average_headcount: number;
    hires: number;
    departures: number;
    turnover: number | null;
    average_seniority: number | null;
    theoretical_days: number;
    sick_days: number;
    unpaid_days: number;
    planned_days: number;
    sick_stops: number;
    absenteeism: number | null;
    payroll: number;
    average_payroll: number;
    average_salary: number | null;
    missing_salaries: number;
};

type DepartmentRow = {
    name: string | null;
    headcount: number;
    theoretical_days: number;
    sick_days: number;
    unpaid_days: number;
    absenteeism: number | null;
    payroll: number;
};
type BalanceRow = { id: number; name: string; department: string | null; remaining: number; carry_left: number };

interface IndicatorsProps {
    year: number;
    until: string;
    summary: Summary;
    months: Month[];
    departments: DepartmentRow[];
    balances: {
        total_remaining: number;
        average_remaining: number;
        carry_expiring: number;
        carry_expiry: string | null;
        carry_people: number;
        top: BalanceRow[];
    };
    years: number[];
    departmentId: number | null;
    departmentOptions: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Indicateurs RH', href: '/admin/indicators' },
];

const number = (value: number, digits = 1) => value.toLocaleString('fr-FR', { maximumFractionDigits: digits });
const percent = (value: number | null) => (value === null ? '—' : `${number(value)} %`);
const days = (value: number) => `${number(value)} j`;
const dh = (value: number) => `${number(value, 0)} DH`;
/** Large amounts on the chart axis: "1,2 M", "850 k". */
const compactDh = (value: number) =>
    value >= 1_000_000 ? `${number(value / 1_000_000)} M` : value >= 1_000 ? `${number(value / 1_000, 0)} k` : number(value, 0);
const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR');
const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

/** "12 salaires (1 manquant)" for a month. */
function salaryCount(m: Month) {
    const known = m.headcount - m.missing_salaries;
    const missing = m.missing_salaries > 0 ? ` (${m.missing_salaries} manquant${m.missing_salaries > 1 ? 's' : ''})` : '';
    return `${known} salaire${known > 1 ? 's' : ''}${missing}`;
}

function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
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

/** Headcount, turnover, absenteeism and unused leave for a year, with the Excel year-end report. */
export default function Indicators({ year, until, summary, months, departments, balances, years, departmentId, departmentOptions }: IndicatorsProps) {
    const filter = (params: { year?: number; department?: number | null }) => {
        const department = params.department === undefined ? departmentId : params.department;
        router.get('/admin/indicators', { year: params.year ?? year, ...(department ? { department } : {}) }, { preserveScroll: true });
    };
    const query = `year=${year}${departmentId ? `&department=${departmentId}` : ''}`;
    const exportUrl = `/admin/indicators/export?${query}`;
    const pdfUrl = `/admin/indicators/export-pdf?${query}`;
    const isCurrentYear = until.slice(0, 4) === String(new Date().getFullYear()) && year === new Date().getFullYear();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Indicateurs RH" />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Indicateurs RH</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Du 01/01/{year} au {frDate(until)}
                            {isCurrentYear ? ' (année en cours)' : ''}.
                        </p>
                    </div>
                    {/* Filters in one row above the charts. */}
                    <div className="flex flex-wrap items-center gap-2">
                        <select aria-label="Année" className={selectClass} value={year} onChange={(e) => filter({ year: Number(e.target.value) })}>
                            {years.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
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
                                <Download className="me-2 h-4 w-4" /> Bilan (Excel)
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={pdfUrl}>
                                <FileText className="me-2 h-4 w-4" /> Bilan (PDF)
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat
                        label="Effectif"
                        value={summary.headcount_end}
                        hint={`${summary.headcount_start} au 1er janvier · moyenne ${number(summary.average_headcount)}`}
                    />
                    <Stat
                        label="Taux de rotation"
                        value={percent(summary.turnover)}
                        hint={`${summary.hires} embauche${summary.hires > 1 ? 's' : ''} · ${summary.departures} départ${summary.departures > 1 ? 's' : ''}`}
                    />
                    <Stat
                        label="Absentéisme"
                        value={percent(summary.absenteeism)}
                        hint={`${days(summary.sick_days)} de maladie (${summary.sick_stops} arrêt${summary.sick_stops > 1 ? 's' : ''}) · ${days(summary.unpaid_days)} sans solde`}
                    />
                    <Stat
                        label="Ancienneté moyenne"
                        value={summary.average_seniority === null ? '—' : `${number(summary.average_seniority)} ans`}
                        hint={`${days(summary.planned_days)} de congés planifiés pris`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Effectif en fin de mois</CardTitle>
                            <CardDescription>Employés en poste le dernier jour du mois ; embauches et départs au survol.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MonthlyBars
                                title="Effectif en fin de mois"
                                format={(v) => number(v, 0)}
                                bars={months.map((m) => ({
                                    label: m.label,
                                    value: m.headcount,
                                    details: [
                                        `${m.headcount} employé${m.headcount > 1 ? 's' : ''}`,
                                        `${m.hires} embauche${m.hires > 1 ? 's' : ''}, ${m.departures} départ${m.departures > 1 ? 's' : ''}`,
                                    ],
                                }))}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Absentéisme par mois</CardTitle>
                            <CardDescription>Jours de maladie et sans solde ÷ jours ouvrés théoriques.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MonthlyBars
                                title="Taux d'absentéisme par mois"
                                format={(v) => `${number(v)} %`}
                                bars={months.map((m) => ({
                                    label: m.label,
                                    value: m.absenteeism,
                                    details: [
                                        percent(m.absenteeism),
                                        `${days(m.sick_days)} de maladie (${m.sick_stops} arrêt${m.sick_stops > 1 ? 's' : ''})`,
                                        `${days(m.unpaid_days)} sans solde`,
                                    ],
                                }))}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Masse salariale brute</CardTitle>
                        <CardDescription>
                            Salaires bruts mensuels des contrats en cours le dernier jour de chaque mois, augmentations comprises. Hors primes, jours
                            sans solde et charges patronales.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground text-sm">Sur la période</dt>
                                <dd className="text-2xl font-bold tabular-nums">{dh(summary.payroll)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Par mois en moyenne</dt>
                                <dd className="text-2xl font-bold tabular-nums">{dh(summary.average_payroll)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Salaire brut moyen</dt>
                                <dd className="text-2xl font-bold tabular-nums">
                                    {summary.average_salary === null ? '—' : dh(summary.average_salary)}
                                </dd>
                            </div>
                        </dl>
                        {summary.missing_salaries > 0 && (
                            <p role="status" className="text-sm text-orange-700 dark:text-orange-400">
                                {summary.missing_salaries} employé{summary.missing_salaries > 1 ? 's' : ''} en poste sans salaire renseigné sur leur
                                contrat : la masse salariale est sous-estimée.
                            </p>
                        )}
                        <MonthlyBars
                            title="Masse salariale brute par mois"
                            format={compactDh}
                            bars={months.map((m) => ({
                                label: m.label,
                                value: m.payroll,
                                details: [dh(m.payroll), salaryCount(m)],
                            }))}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 2xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Par département</CardTitle>
                            <CardDescription>Sur toute la période{departmentId ? ', tous départements confondus' : ''}.</CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Département</TableHead>
                                        <TableHead className="text-end">Effectif</TableHead>
                                        <TableHead className="text-end">Maladie</TableHead>
                                        <TableHead className="text-end">Sans solde</TableHead>
                                        <TableHead className="text-end">Absentéisme</TableHead>
                                        <TableHead className="text-end">Masse salariale</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departments.map((d) => (
                                        <TableRow key={d.name ?? 'none'}>
                                            <TableCell>{d.name ?? <span className="text-muted-foreground">Sans département</span>}</TableCell>
                                            <TableCell className="text-end tabular-nums">{d.headcount}</TableCell>
                                            <TableCell className="text-end tabular-nums">{days(d.sick_days)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{days(d.unpaid_days)}</TableCell>
                                            <TableCell className="text-end font-medium tabular-nums">{percent(d.absenteeism)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{dh(d.payroll)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Congés non pris</CardTitle>
                            <CardDescription>
                                {days(balances.total_remaining)} au total, {days(balances.average_remaining)} par employé en moyenne.
                                {balances.carry_expiry && balances.carry_expiring > 0
                                    ? ` ${days(balances.carry_expiring)} reportés (${balances.carry_people} employé${balances.carry_people > 1 ? 's' : ''}) expirent le ${frDate(balances.carry_expiry)}.`
                                    : ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employé</TableHead>
                                        <TableHead>Département</TableHead>
                                        <TableHead className="text-end">Jours restants</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {balances.top.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link href={`/admin/users/${row.id}`} className="hover:underline">
                                                    {row.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{row.department ?? '—'}</TableCell>
                                            <TableCell className="text-end font-medium tabular-nums">
                                                {days(row.remaining)}
                                                {row.carry_left > 0 && (
                                                    <span className="text-muted-foreground block text-xs">dont {days(row.carry_left)} reportés</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <p className="text-muted-foreground mt-2 text-xs">
                                Les {balances.top.length} soldes les plus élevés ; la liste complète est dans le bilan Excel.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Table view of both charts. */}
                <Card>
                    <CardHeader>
                        <CardTitle>Détail mensuel</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mois</TableHead>
                                    <TableHead className="text-end">Effectif</TableHead>
                                    <TableHead className="text-end">Embauches</TableHead>
                                    <TableHead className="text-end">Départs</TableHead>
                                    <TableHead className="text-end">Jours théoriques</TableHead>
                                    <TableHead className="text-end">Maladie</TableHead>
                                    <TableHead className="text-end">Sans solde</TableHead>
                                    <TableHead className="text-end">Absentéisme</TableHead>
                                    <TableHead className="text-end">Congés planifiés</TableHead>
                                    <TableHead className="text-end">Masse salariale</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {months.map((m) => (
                                    <TableRow key={m.month}>
                                        <TableCell>{m.label}</TableCell>
                                        <TableCell className="text-end tabular-nums">{m.headcount}</TableCell>
                                        <TableCell className="text-end tabular-nums">{m.hires}</TableCell>
                                        <TableCell className="text-end tabular-nums">{m.departures}</TableCell>
                                        <TableCell className="text-end tabular-nums">{m.theoretical_days}</TableCell>
                                        <TableCell className="text-end tabular-nums">{days(m.sick_days)}</TableCell>
                                        <TableCell className="text-end tabular-nums">{days(m.unpaid_days)}</TableCell>
                                        <TableCell className="text-end font-medium tabular-nums">{percent(m.absenteeism)}</TableCell>
                                        <TableCell className="text-end tabular-nums">{days(m.planned_days)}</TableCell>
                                        <TableCell className="text-end tabular-nums">{dh(m.payroll)}</TableCell>
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
