import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { MessagesSquare } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Row = {
    id: number;
    user_id: number;
    name: string;
    department: string | null;
    manager: string;
    status: string;
    status_label: string;
    rating_label: string | null;
};
type Recommendation = {
    id: number;
    user_id: number;
    name: string;
    kind_label: string;
    job_title: string | null;
    salary: number | null;
    reason: string | null;
    status: 'pending' | 'applied' | 'declined';
};

interface ReviewsProps {
    campaigns: { year: number; status: string }[];
    campaign: { id: number; year: number; status: string; due_on: string | null } | null;
    counts: { status: string; label: string; count: number }[];
    reviews: Row[];
    recommendations: Recommendation[];
    nextYear: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Campagnes d’entretiens', href: '/admin/reviews' },
];
const REC_STATUS: Record<Recommendation['status'], string> = { pending: 'À traiter', applied: 'Appliquée', declined: 'Écartée' };

function OpenCampaign({ year }: { year: number }) {
    const form = useForm({ year: String(year), due_on: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/admin/reviews', { onError: toastErrors });
    };
    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <div className="space-y-1">
                <Label htmlFor="campaign-year">{t('Année évaluée')}</Label>
                <Input
                    id="campaign-year"
                    type="number"
                    className="w-28"
                    value={form.data.year}
                    onChange={(e) => form.setData('year', e.target.value)}
                />
            </div>
            <div className="space-y-1">
                <Label htmlFor="campaign-due">{t('À terminer avant le (facultatif)')}</Label>
                <Input id="campaign-due" type="date" value={form.data.due_on} onChange={(e) => form.setData('due_on', e.target.value)} />
            </div>
            <Button type="submit" disabled={form.processing}>
                {t('Ouvrir la campagne')}
            </Button>
            {form.errors.year && <p className="w-full text-sm text-red-700 dark:text-red-400">{form.errors.year}</p>}
        </form>
    );
}

/** HR: open a year's review campaign, follow it, act on recommendations. */
export default function Reviews({ campaigns, campaign, counts, reviews, recommendations, nextYear }: ReviewsProps) {
    const can = usePermissions();
    const [filter, setFilter] = useState<string>('');
    const shown = filter ? reviews.filter((r) => r.status === filter) : reviews;

    const closeCampaign = async () => {
        const ok = await confirmAction({
            title: t('Clôturer la campagne :p0 ?', { p0: campaign!.year }),
            description: t('Les entretiens restent consultables ; les responsables ne les verront plus dans leur liste à mener.'),
            confirmLabel: t('Clôturer'),
        });
        if (ok) router.put(`/admin/review-campaigns/${campaign!.id}/close`, {}, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Campagnes d’entretiens')} />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Campagnes d’entretiens annuels')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t(
                                'Auto-évaluation, évaluation du responsable, commentaire de l’employé, clôture RH. Les recommandations deviennent une évolution de carrière en un clic.',
                            )}
                        </p>
                    </div>
                    {campaigns.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {campaigns.map((c) => (
                                <Link
                                    key={c.year}
                                    href={`/admin/reviews?year=${c.year}`}
                                    className={`rounded-md border px-2 py-1 text-sm ${c.year === campaign?.year ? 'bg-muted font-medium' : ''}`}
                                >
                                    {c.year} · {c.status === 'open' ? t('en cours') : t('clôturée')}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {can.manage_hr && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Nouvelle campagne')}</CardTitle>
                            <CardDescription>
                                {t('Crée un entretien pour chaque employé actif, avec son responsable actuel (RH pour ceux qui n’en ont pas).')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <OpenCampaign year={nextYear} />
                        </CardContent>
                    </Card>
                )}

                {campaign === null ? (
                    <EmptyState
                        icon={MessagesSquare}
                        title={t('Aucune campagne')}
                        description={t('Ouvrez la campagne de l’année pour lancer les entretiens.')}
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button size="sm" variant={filter === '' ? 'default' : 'outline'} onClick={() => setFilter('')}>
                                {t('Tous (')}
                                {reviews.length})
                            </Button>
                            {counts.map((c) => (
                                <Button
                                    key={c.status}
                                    size="sm"
                                    variant={filter === c.status ? 'default' : 'outline'}
                                    onClick={() => setFilter(c.status)}
                                >
                                    {c.label} ({c.count})
                                </Button>
                            ))}
                            {can.manage_hr && campaign.status === 'open' && (
                                <Button size="sm" variant="ghost" className="ms-auto" onClick={closeCampaign}>
                                    {t('Clôturer la campagne')} {campaign.year}
                                </Button>
                            )}
                        </div>

                        {recommendations.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Recommandations des responsables')}</CardTitle>
                                    <CardDescription>
                                        {t(
                                            '« Appliquer » ouvre l’évolution de carrière pré-remplie dans la fiche de l’employé ; vous décidez et enregistrez.',
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="divide-y">
                                        {recommendations.map((r) => (
                                            <li
                                                key={r.id}
                                                className="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {r.name} · {t(r.kind_label)}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {[r.job_title, r.salary && `${r.salary.toLocaleString(intlLocale())} DH brut`]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                    {r.reason && <p className="text-muted-foreground text-xs whitespace-pre-line">{r.reason}</p>}
                                                </div>
                                                {r.status === 'pending' && can.manage_hr ? (
                                                    <div className="flex gap-2">
                                                        <Button size="sm" asChild>
                                                            <Link href={`/admin/users/${r.user_id}?recommendation=${r.id}`}>{t('Appliquer')}</Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.put(
                                                                    `/admin/reviews/${r.id}/decline`,
                                                                    {},
                                                                    { preserveScroll: true, onError: toastErrors },
                                                                )
                                                            }
                                                        >
                                                            {t('Écarter')}
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Badge variant="secondary">{REC_STATUS[r.status]}</Badge>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('Entretiens')} {campaign.year}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Employé')}</TableHead>
                                            <TableHead>{t('Responsable')}</TableHead>
                                            <TableHead>{t('Étape')}</TableHead>
                                            <TableHead>{t('Appréciation')}</TableHead>
                                            <TableHead className="sr-only">{t('Ouvrir')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {shown.map((r) => (
                                            <TableRow key={r.id}>
                                                <TableCell>
                                                    <span className="font-medium">{r.name}</span>
                                                    {r.department && <span className="text-muted-foreground block text-xs">{r.department}</span>}
                                                </TableCell>
                                                <TableCell>{r.manager}</TableCell>
                                                <TableCell>
                                                    <Badge variant={r.status === 'closed' ? 'secondary' : 'outline'}>{t(r.status_label)}</Badge>
                                                </TableCell>
                                                <TableCell>{r.rating_label ?? '—'}</TableCell>
                                                <TableCell className="text-end">
                                                    <Button size="sm" variant="ghost" asChild>
                                                        <Link href={`/evaluations/${r.id}`}>{t('Ouvrir')}</Link>
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
