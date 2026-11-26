import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { parseISODate, todayISO } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface Holiday {
    id: number;
    name: string;
    date: string; // YYYY-MM-DD; for a repeating holiday only month and day matter
    recurring: boolean;
}

interface HolidaysProps {
    holidays: Holiday[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Jours fériés', href: '/admin/holidays' },
];

const formatDayMonth = (iso: string) => parseISODate(iso)?.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }) ?? iso;

const formatFullDate = (iso: string) =>
    parseISODate(iso)?.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) ?? iso;

const Holidays = ({ holidays }: HolidaysProps) => {
    const [editing, setEditing] = useState<Holiday | null>(null);
    const form = useForm<{ name: string; date: string; recurring: boolean }>({ name: '', date: '', recurring: false });

    const recurring = holidays.filter((h) => h.recurring);
    const today = todayISO();
    const currentYear = Number(today.slice(0, 4));

    // Single-date holidays (Eid, Mawlid…) grouped by year, most recent year first.
    const byYear = useMemo(() => {
        const groups = new Map<number, Holiday[]>();
        holidays
            .filter((h) => !h.recurring)
            .forEach((h) => {
                const year = Number(h.date.slice(0, 4));
                groups.set(year, [...(groups.get(year) ?? []), h]);
            });
        return [...groups.entries()].sort(([a], [b]) => b - a);
    }, [holidays]);

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const startEdit = (holiday: Holiday) => {
        setEditing(holiday);
        form.clearErrors();
        form.setData({
            name: holiday.name,
            // A repeating holiday is stored in year 2000; show it in the current year.
            date: holiday.recurring ? `${currentYear}${holiday.date.slice(4)}` : holiday.date,
            recurring: holiday.recurring,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
            },
        };

        if (editing) {
            form.put(`/admin/holidays/${editing.id}`, options);
        } else {
            form.post('/admin/holidays', options);
        }
    };

    const remove = async (holiday: Holiday) => {
        const ok = await confirmAction({
            title: `Supprimer le jour férié « ${holiday.name} » ?`,
            description: 'Les jours de congé seront recalculés en conséquence.',
            confirmLabel: 'Supprimer',
            destructive: true,
        });
        if (!ok) return;
        router.delete(`/admin/holidays/${holiday.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (editing?.id === holiday.id) resetForm();
            },
        });
    };

    const row = (holiday: Holiday, label: string, past = false) => (
        <li key={holiday.id} className={`flex items-center gap-3 py-2.5 ${past ? 'text-muted-foreground' : ''}`}>
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{holiday.name}</div>
                <div className="text-muted-foreground text-sm first-letter:uppercase">{label}</div>
            </div>
            <Button variant="ghost" size="icon" aria-label={`Modifier ${holiday.name}`} onClick={() => startEdit(holiday)}>
                <Pencil className="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label={`Supprimer ${holiday.name}`}
                className="text-red-700 hover:text-red-700 dark:text-red-400"
                onClick={() => remove(holiday)}
            >
                <Trash2 className="h-4 w-4" />
            </Button>
        </li>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jours fériés" />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Jours fériés</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Ces jours ne sont pas décomptés des congés. Toute modification s'applique immédiatement au calcul des jours et des soldes.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{editing ? `Modifier « ${editing.name} »` : 'Ajouter un jour férié'}</CardTitle>
                        <CardDescription>
                            Les fêtes religieuses (Aïd al-Fitr, Aïd al-Adha, 1er Moharram, Aïd al-Mawlid) changent de date chaque année : ajoutez-les
                            dès que les dates officielles sont annoncées, sans cocher « chaque année ».
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nom</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        placeholder="Ex. : Aïd al-Fitr"
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="date">Date</Label>
                                    <Input
                                        id="date"
                                        type="date"
                                        value={form.data.date}
                                        onChange={(e) => form.setData('date', e.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.date} />
                                </div>
                                <div className="flex items-center gap-2 sm:col-span-2">
                                    <Checkbox
                                        id="recurring"
                                        checked={form.data.recurring}
                                        onCheckedChange={(checked) => form.setData('recurring', checked === true)}
                                    />
                                    <Label htmlFor="recurring" className="font-normal">
                                        Se répète chaque année à la même date (fête nationale)
                                    </Label>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                {editing && (
                                    <Button type="button" variant="outline" onClick={resetForm}>
                                        Annuler
                                    </Button>
                                )}
                                <Button type="submit" disabled={form.processing} className="bg-green-700 text-white hover:bg-green-800">
                                    {editing ? (
                                        'Enregistrer'
                                    ) : (
                                        <>
                                            <Plus className="mr-1 h-4 w-4" /> Ajouter
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Chaque année <Badge variant="secondary">{recurring.length}</Badge>
                            </CardTitle>
                            <CardDescription>Fêtes nationales à date fixe.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recurring.length ? (
                                <ul className="divide-y">{recurring.map((h) => row(h, formatDayMonth(h.date)))}</ul>
                            ) : (
                                <p className="text-muted-foreground text-sm">Aucun jour férié fixe.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Dates variables</CardTitle>
                            <CardDescription>Fêtes religieuses et jours exceptionnels, à saisir chaque année.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {!byYear.some(([year]) => year >= currentYear) && (
                                <p className="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300">
                                    Aucune fête religieuse n'est encore saisie pour {currentYear}.
                                </p>
                            )}
                            {byYear.map(([year, items]) => (
                                <div key={year}>
                                    <h3 className="text-muted-foreground text-sm font-semibold">{year}</h3>
                                    <ul className="divide-y">{items.map((h) => row(h, formatFullDate(h.date), h.date < today))}</ul>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
};

export default Holidays;
