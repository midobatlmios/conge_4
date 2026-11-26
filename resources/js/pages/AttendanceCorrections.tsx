import { toastErrors } from '@/components/app-toaster';
import { Correction } from '@/components/attendance/attendance';
import { promptValue } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, ClockAlert, X } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Corrections de pointage', href: '/pointage/corrections' },
];

const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale(), { weekday: 'long', day: 'numeric', month: 'long' });
const times = (c: Correction) => `${c.clock_in}${c.clock_out ? ` → ${c.clock_out}` : ' (départ non indiqué)'}`;

/** Corrections of clocking waiting for the manager (or HR), and the latest decided. */
export default function AttendanceCorrections({ pending, recent }: { pending: Correction[]; recent: Correction[] }) {
    const approve = (c: Correction) => router.put(`/pointage/corrections/${c.id}/approve`, {}, { preserveScroll: true, onError: toastErrors });
    const refuse = async (c: Correction) => {
        const reason = await promptValue({
            title: t('Refuser cette correction ?'),
            description: `${c.name}, ${frDate(c.date)} : ${times(c)}.`,
            label: t('Motif (facultatif, visible par l’employé)'),
            multiline: true,
            confirmLabel: t('Refuser'),
            destructive: true,
        });
        if (reason !== null)
            router.put(`/pointage/corrections/${c.id}/refuse`, { refusal_reason: reason }, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Corrections de pointage')} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">{t('Corrections de pointage')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {t('Oublis et erreurs de pointage signalés par votre équipe. Accepter une correction remplace le pointage du jour.')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {t('À traiter')} <Badge variant="secondary">{pending.length}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pending.length === 0 ? (
                            <EmptyState
                                icon={ClockAlert}
                                title={t('Rien à traiter')}
                                description={t('Les demandes de correction apparaîtront ici.')}
                            />
                        ) : (
                            <ul className="divide-y">
                                {pending.map((c) => (
                                    <li key={c.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="text-sm">
                                            <p className="font-medium">{c.name}</p>
                                            <p className="first-letter:uppercase">
                                                {frDate(c.date)} · <span className="tabular-nums">{times(c)}</span>
                                            </p>
                                            <p className="text-muted-foreground">{c.reason}</p>
                                        </div>
                                        {c.can_decide && (
                                            <div className="flex gap-2">
                                                <Button size="sm" onClick={() => approve(c)}>
                                                    <Check className="me-1 h-4 w-4" /> {t('Accepter')}
                                                </Button>
                                                <Button size="sm" variant="outline" onClick={() => refuse(c)}>
                                                    <X className="me-1 h-4 w-4" /> {t('Refuser')}
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {recent.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Dernières décisions')}</CardTitle>
                            <CardDescription>{t('Les 15 plus récentes.')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {recent.map((c) => (
                                    <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                        <span>
                                            <span className="font-medium">{c.name}</span> ·{' '}
                                            {new Date(`${c.date}T00:00:00`).toLocaleDateString(intlLocale())} ·{' '}
                                            <span className="tabular-nums">{times(c)}</span>
                                        </span>
                                        <Badge variant="secondary">
                                            {t(c.status_label)}
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
