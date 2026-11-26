import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { TYPE_CONGE_LABELS, TypeConge, isWorkingDay, toISODate, todayISO, useLeaveRules } from '@/lib/leave';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Users } from 'lucide-react';

interface Leave {
    id: number;
    start: string; // YYYY-MM-DD
    end: string;
    startHalf: boolean; // starts in the afternoon
    endHalf: boolean; // ends at noon
    pending: boolean;
    type: TypeConge | null; // only for admins and the employee themself
}

interface Person {
    id: number;
    name: string;
    job_title: string | null;
    department: string | null;
    leaves: Leave[];
}

interface CalendarProps {
    month: string; // YYYY-MM
    departmentId: number | null;
    departments: { id: number; name: string }[]; // admins only
    departmentName: string | null; // the employee's own department
    people: Person[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Calendrier', href: '/calendrier' },
];

const ALL = 'all';
const WEEKDAY_INITIALS = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

const shiftMonth = (month: string, delta: number) => {
    const [year, m] = month.split('-').map(Number);
    const date = new Date(year, m - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
};

export default function Calendar({ month, departmentId, departments, departmentName, people }: CalendarProps) {
    const rules = useLeaveRules();
    const [year, monthNumber] = month.split('-').map(Number);
    const days = Array.from({ length: new Date(year, monthNumber, 0).getDate() }, (_, i) => new Date(year, monthNumber - 1, i + 1));
    const today = todayISO();
    const isAdmin = usePermissions().view_hr;

    const visit = (params: { month?: string; department?: string }) => {
        const department = params.department ?? (departmentId ? String(departmentId) : ALL);
        router.get(
            '/calendrier',
            { month: params.month ?? month, ...(department !== ALL ? { department } : {}) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const leavesOn = (person: Person, iso: string) => person.leaves.filter((leave) => leave.start <= iso && iso <= leave.end);

    // Which part of a day a leave covers: its first day may be an afternoon, its last a morning.
    const halfOf = (leave: Leave, iso: string): 'morning' | 'afternoon' | 'full' => {
        if (leave.start === iso && leave.startHalf) return 'afternoon';
        if (leave.end === iso && leave.endHalf) return 'morning';
        return 'full';
    };

    // People with accepted leave on each working day, to spot days when too many are away.
    const awayCount = (iso: string) => people.filter((person) => leavesOn(person, iso).some((leave) => !leave.pending)).length;

    const title = new Date(year, monthNumber - 1, 1).toLocaleDateString(intlLocale(), { month: 'long', year: 'numeric' });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Calendrier des absences')} />
            <div className="mx-auto w-full max-w-7xl space-y-4 px-4 py-10">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Calendrier des absences')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {isAdmin
                                ? t('Tous les employés actifs.')
                                : departmentName
                                  ? t('Département :name.', { name: departmentName })
                                  : t('Vos congés.')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {departments.length > 0 && (
                            <Select value={departmentId ? String(departmentId) : ALL} onValueChange={(value) => visit({ department: value })}>
                                <SelectTrigger className="w-48" aria-label={t('Département')}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>{t('Tous les départements')}</SelectItem>
                                    {departments.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t('Mois précédent')}
                            onClick={() => visit({ month: shiftMonth(month, -1) })}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="w-36 text-center font-medium capitalize">{title}</span>
                        <Button variant="outline" size="icon" aria-label={t('Mois suivant')} onClick={() => visit({ month: shiftMonth(month, 1) })}>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" onClick={() => visit({ month: today.slice(0, 7) })}>
                            {t("Aujourd'hui")}
                        </Button>
                    </div>
                </div>

                <div className="text-muted-foreground flex flex-wrap gap-4 text-xs">
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-5 rounded-sm bg-green-600" /> {t('Congé accepté')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-5 rounded-sm border border-amber-500 bg-[repeating-linear-gradient(45deg,var(--color-amber-300)_0_3px,transparent_3px_6px)]" />{' '}
                        {t('En attente')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="bg-muted inline-block h-3 w-5 rounded-sm" /> {t('Week-end ou jour férié')}
                    </span>
                </div>

                {people.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={t('Aucun employé à afficher')}
                        description={t('Choisissez un autre département, ou rattachez les employés à un département dans leur dossier.')}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-md border" tabIndex={0} role="region" aria-label={t('Calendrier des absences')}>
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="bg-background sticky start-0 z-10 min-w-40 px-3 py-2 text-start font-medium">{t('Employé')}</th>
                                    {days.map((day) => {
                                        const iso = toISODate(day);
                                        return (
                                            <th
                                                key={iso}
                                                className={`min-w-7 px-0 py-1 text-center text-xs font-normal ${isWorkingDay(day, rules) ? '' : 'bg-muted text-muted-foreground'} ${iso === today ? 'text-primary font-bold' : ''}`}
                                            >
                                                <div>{WEEKDAY_INITIALS[day.getDay()]}</div>
                                                <div>{day.getDate()}</div>
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {people.map((person) => (
                                    <tr key={person.id} className="border-b last:border-b-0">
                                        <td className="bg-background sticky start-0 z-10 px-3 py-1.5">
                                            <div className="truncate font-medium">{person.name}</div>
                                            {(person.job_title || (isAdmin && person.department)) && (
                                                <div className="text-muted-foreground truncate text-xs">
                                                    {[person.job_title, isAdmin ? person.department : null].filter(Boolean).join(' · ')}
                                                </div>
                                            )}
                                        </td>
                                        {days.map((day) => {
                                            const iso = toISODate(day);
                                            const working = isWorkingDay(day, rules);
                                            // Up to two leaves on one day: a morning and an afternoon.
                                            const dayLeaves = working ? leavesOn(person, iso) : [];
                                            const labels = dayLeaves.map((leave) => {
                                                const part = halfOf(leave, iso);
                                                return `${person.name} : ${leave.type ? TYPE_CONGE_LABELS[leave.type] : 'Absent'}${
                                                    part === 'morning' ? ' (matin)' : part === 'afternoon' ? ' (après-midi)' : ''
                                                }${leave.pending ? ' (en attente)' : ''}`;
                                            });
                                            return (
                                                <td
                                                    key={iso}
                                                    className={`h-9 p-0.5 ${working ? '' : 'bg-muted'}`}
                                                    title={labels.join('\n') || undefined}
                                                >
                                                    {dayLeaves.length > 0 && (
                                                        <div className="relative h-full w-full">
                                                            {dayLeaves.map((leave, i) => {
                                                                const part = halfOf(leave, iso);
                                                                return (
                                                                    <div
                                                                        key={leave.id}
                                                                        // A colored block: an image with a name for screen readers.
                                                                        role="img"
                                                                        aria-label={labels[i]}
                                                                        className={`absolute inset-y-0 rounded-sm ${
                                                                            part === 'morning'
                                                                                ? 'start-0 w-1/2'
                                                                                : part === 'afternoon'
                                                                                  ? 'end-0 w-1/2'
                                                                                  : 'inset-x-0'
                                                                        } ${
                                                                            leave.pending
                                                                                ? 'border border-amber-500 bg-[repeating-linear-gradient(45deg,var(--color-amber-300)_0_3px,transparent_3px_6px)]'
                                                                                : 'bg-green-600'
                                                                        }`}
                                                                    />
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t">
                                    <td className="bg-background text-muted-foreground sticky start-0 z-10 px-3 py-1.5 text-xs">{t('Absents')}</td>
                                    {days.map((day) => {
                                        const iso = toISODate(day);
                                        const count = isWorkingDay(day, rules) ? awayCount(iso) : 0;
                                        return (
                                            <td key={iso} className={`text-center text-xs ${isWorkingDay(day, rules) ? '' : 'bg-muted'}`}>
                                                {count > 0 && (
                                                    <span className={count > 1 ? 'font-bold text-red-700 dark:text-red-400' : ''}>{count}</span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
