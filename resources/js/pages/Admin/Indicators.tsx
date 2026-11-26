import MonthlyBars from '@/components/indicators/MonthlyBars';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
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
    siteId: number | null;
    siteOptions: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Indicateurs RH', href: '/admin/indicators' },
];

const number = (value: number, digits = 1) => value.toLocaleString(intlLocale(), { maximumFractionDigits: digits });
const percent = (value: number | null) => (value === null ? '—' : `${number(value)} %`);
const days = (value: number) => `${number(value)} j`;
const dh = (value: number) => `${number(value, 0)} DH`;
/** Large amounts on the chart axis: "1,2 M", "850 k". */
const compactDh = (value: number) =>
    value >= 1_000_000 ? `${number(value / 1_000_000)} M` : value >= 1_000 ? `${number(value / 1_000, 0)} k` : number(value, 0);
const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale());
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
export default function Indicators({
    year,
    until,
    summary,
    months,
    departments,
    balances,
    years,
    departmentId,
    departmentOptions,
    siteId,
    siteOptions,
}: IndicatorsProps) {
    const filter = (params: { year?: number; department?: number | null; site?: number | null }) => {
        const site = params.site === undefined ? siteId : params.site;
        const department = params.department === undefined ? departmentId : params.department;
        router.get(
            '/admin/indicators',
            { year: params.year ?? year, ...(department ? { department } : {}), ...(site ? { site } : {}) },
            { preserveScroll: true },
        );
    };
    const query = `year=${year}${departmentId ? `&department=${departmentId}` : ''}${siteId ? `&site=${siteId}` : ''}`;
    const exportUrl = `/admin/indicators/export?${query}`;
    const pdfUrl = `/admin/indicators/export-pdf?${query}`;
    const isCurrentYear = until.slice(0, 4) === String(new Date().getFullYear()) && year === new Date().getFullYear();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Indicateurs RH')} />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Indicateurs RH')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('Du 01/01/')}
                            {year} {t('au')} {frDate(until)}
                            {isCurrentYear ? t(' (année en cours)') : ''}.
                        </p>
                    </div>
                    {/* Filters in one row above the charts. */}
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            aria-label={t('Année')}
                            className={selectClass}
                            value={year}
                            onChange={(e) => filter({ year: Number(e.target.value) })}
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label={t('Département')}
                            className={selectClass}
                            value={departmentId ?? ''}
                            onChange={(e) => filter({ department: e.target.value ? Number(e.target.value) : null })}
                        >
                            <option value="">{t('Tous les départements')}</option>
                            {departmentOptions.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                        {siteOptions.length > 0 && (
                            <select
                                aria-label={t('Site')}
                                className={selectClass}
                                value={siteId ?? ''}
                                onChange={(e) => filter({ site: e.target.value ? Number(e.target.value) : null })}
                            >
                                <option value="">{t('Tous les sites')}</option>
                                {siteOptions.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download className="me-2 h-4 w-4" /> {t('Bilan (Excel)')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={pdfUrl}>
                                <FileText className="me-2 h-4 w-4" /> {t('Bilan (PDF)')}
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat
                        label={t('Effectif')}
                        value={summary.headcount_end}
                        hint={`${summary.headcount_start} au 1er janvier · moyenne ${number(summary.average_headcount)}`}
                    />
                    <Stat
                        label={t('Taux de rotation')}
                        value={percent(summary.turnover)}
                        hint={t(':p0 embauche:p1 · :p2 départ:p3', {
                            p0: summary.hires,
                            p1: summary.hires > 1 ? 's' : '',
                            p2: summary.departures,
                            p3: summary.departures > 1 ? 's' : '',
                        })}
                    />
                    <Stat
                        label={t('Absentéisme')}
                        value={percent(summary.absenteeism)}
                        hint={t(':p0 de maladie (:p1 arrêt:p2) · :p3 sans solde', {
                            p0: days(summary.sick_days),
                            p1: summary.sick_stops,
                            p2: summary.sick_stops > 1 ? 's' : '',
                            p3: days(summary.unpaid_days),
                        })}
                    />
                    <Stat
                        label={t('Ancienneté moyenne')}
                        value={summary.average_seniority === null ? '—' : `${number(summary.average_seniority)} ans`}
                        hint={t(':p0 de congés planifiés pris', { p0: days(summary.planned_days) })}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Effectif en fin de mois')}</CardTitle>
                            <CardDescription>{t('Employés en poste le dernier jour du mois ; embauches et départs au survol.')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MonthlyBars
                                title={t('Effectif en fin de mois')}
                                format={(v) => number(v, 0)}
                                bars={months.map((m) => ({
                                    label: m.label,
                                    value: m.headcount,
                                    details: [
                                        `${m.headcount} employé${m.headcount > 1 ? 's' : ''}`,
                                        t(':p0 embauche:p1, :p2 départ:p3', {
                                            p0: m.hires,
                                            p1: m.hires > 1 ? 's' : '',
                                            p2: m.departures,
                                            p3: m.departures > 1 ? 's' : '',
                                        }),
                                    ],
                                }))}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Absentéisme par mois')}</CardTitle>
                            <CardDescription>{t('Jours de maladie et sans solde ÷ jours ouvrés théoriques.')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MonthlyBars
                                title={t("Taux d'absentéisme par mois")}
                                format={(v) => `${number(v)} %`}
                                bars={months.map((m) => ({
                                    label: m.label,
                                    value: m.absenteeism,
                                    details: [
                                        percent(m.absenteeism),
                                        t(':p0 de maladie (:p1 arrêt:p2)', {
                                            p0: days(m.sick_days),
                                            p1: m.sick_stops,
                                            p2: m.sick_stops > 1 ? 's' : '',
                                        }),
                                        t(':p0 sans solde', { p0: days(m.unpaid_days) }),
                                    ],
                                }))}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Masse salariale brute')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Salaires bruts mensuels des contrats en cours le dernier jour de chaque mois, augmentations comprises. Hors primes, jours sans solde et charges patronales.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Sur la période')}</dt>
                                <dd className="text-2xl font-bold tabular-nums">{dh(summary.payroll)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Par mois en moyenne')}</dt>
                                <dd className="text-2xl font-bold tabular-nums">{dh(summary.average_payroll)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">{t('Salaire brut moyen')}</dt>
                                <dd className="text-2xl font-bold tabular-nums">
                                    {summary.average_salary === null ? '—' : dh(summary.average_salary)}
                                </dd>
                            </div>
                        </dl>
                        {summary.missing_salaries > 0 && (
                            <p role="status" className="text-sm text-orange-700 dark:text-orange-400">
                                {summary.missing_salaries} {t('employé')}
                                {summary.missing_salaries > 1 ? 's' : ''}{' '}
                                {t('en poste sans salaire renseigné sur leur contrat : la masse salariale est sous-estimée.')}
                            </p>
                        )}
                        <MonthlyBars
                            title={t('Masse salariale brute par mois')}
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
                            <CardTitle>{t('Par département')}</CardTitle>
                            <CardDescription>
                                {t('Sur toute la période')}
                                {departmentId ? t(', tous départements confondus') : ''}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Département')}</TableHead>
                                        <TableHead className="text-end">{t('Effectif')}</TableHead>
                                        <TableHead className="text-end">{t('Maladie')}</TableHead>
                                        <TableHead className="text-end">{t('Sans solde')}</TableHead>
                                        <TableHead className="text-end">{t('Absentéisme')}</TableHead>
                                        <TableHead className="text-end">{t('Masse salariale')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departments.map((d) => (
                                        <TableRow key={d.name ?? 'none'}>
                                            <TableCell>{d.name ?? <span className="text-muted-foreground">{t('Sans département')}</span>}</TableCell>
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
                            <CardTitle>{t('Congés non pris')}</CardTitle>
                            <CardDescription>
                                {days(balances.total_remaining)} {t('au total,')} {days(balances.average_remaining)} {t('par employé en moyenne.')}
                                {balances.carry_expiry && balances.carry_expiring > 0
                                    ? t(' :p0 reportés (:p1 employé:p2) expirent le :p3.', {
                                          p0: days(balances.carry_expiring),
                                          p1: balances.carry_people,
                                          p2: balances.carry_people > 1 ? 's' : '',
                                          p3: frDate(balances.carry_expiry),
                                      })
                                    : ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Employé')}</TableHead>
                                        <TableHead>{t('Département')}</TableHead>
                                        <TableHead className="text-end">{t('Jours restants')}</TableHead>
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
                                                    <span className="text-muted-foreground block text-xs">
                                                        {t('dont')} {days(row.carry_left)} {t('reportés')}
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <p className="text-muted-foreground mt-2 text-xs">
                                {t('Les')} {balances.top.length} {t('soldes les plus élevés ; la liste complète est dans le bilan Excel.')}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Table view of both charts. */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Détail mensuel')}</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Mois')}</TableHead>
                                    <TableHead className="text-end">{t('Effectif')}</TableHead>
                                    <TableHead className="text-end">{t('Embauches')}</TableHead>
                                    <TableHead className="text-end">{t('Départs')}</TableHead>
                                    <TableHead className="text-end">{t('Jours théoriques')}</TableHead>
                                    <TableHead className="text-end">{t('Maladie')}</TableHead>
                                    <TableHead className="text-end">{t('Sans solde')}</TableHead>
                                    <TableHead className="text-end">{t('Absentéisme')}</TableHead>
                                    <TableHead className="text-end">{t('Congés planifiés')}</TableHead>
                                    <TableHead className="text-end">{t('Masse salariale')}</TableHead>
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
