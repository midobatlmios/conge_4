import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';

type Entry = {
    id: number;
    at: string;
    actor: string;
    subject: string | null;
    subject_id: number | null;
    action: string;
    detail: string | null;
    ip: string | null;
};

interface PrivacyLogProps {
    entries: { data: Entry[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    actions: Record<string, string>;
    people: { id: number; name: string }[];
    filters: { person: number | null; action: string | null };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Protection des données', href: '/admin/privacy' },
    { title: 'Journal des accès', href: '/admin/privacy/log' },
];

const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

/** Who viewed or changed whose personal data. Read-only, for the Administrateur. */
export default function PrivacyLog({ entries, actions, people, filters }: PrivacyLogProps) {
    const filter = (next: Partial<PrivacyLogProps['filters']>) => {
        const params = { ...filters, ...next };
        router.get('/admin/privacy/log', Object.fromEntries(Object.entries(params).filter(([, value]) => value)), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Journal des accès')} />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Journal des accès aux données personnelles')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {entries.total} {t('entrée')}
                            {entries.total > 1 ? 's' : ''}
                            {t('. Les consultations de ses propres données ne sont pas enregistrées.')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <select
                            aria-label={t('Personne')}
                            className={selectClass}
                            value={filters.person ?? ''}
                            onChange={(e) => filter({ person: e.target.value ? Number(e.target.value) : null })}
                        >
                            <option value="">{t('Toutes les personnes')}</option>
                            {people.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label={t('Action')}
                            className={selectClass}
                            value={filters.action ?? ''}
                            onChange={(e) => filter({ action: e.target.value || null })}
                        >
                            <option value="">{t('Toutes les actions')}</option>
                            {Object.entries(actions).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto">
                        {entries.data.length === 0 ? (
                            <EmptyState
                                icon={History}
                                title={t('Aucun accès enregistré')}
                                description={t("Chaque consultation ou modification des données d'une autre personne apparaîtra ici.")}
                                className="border-0"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Date')}</TableHead>
                                        <TableHead>{t('Par')}</TableHead>
                                        <TableHead>{t('Données de')}</TableHead>
                                        <TableHead>{t('Action')}</TableHead>
                                        <TableHead>{t('Détail')}</TableHead>
                                        <TableHead>{t('Adresse IP')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                {new Date(entry.at).toLocaleString(intlLocale())}
                                            </TableCell>
                                            <TableCell>{entry.actor}</TableCell>
                                            <TableCell>
                                                {entry.subject_id ? (
                                                    <Link href={`/admin/users/${entry.subject_id}`} className="hover:underline">
                                                        {entry.subject}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell>{entry.action}</TableCell>
                                            <TableCell className="text-muted-foreground max-w-xs truncate" title={entry.detail ?? ''}>
                                                {entry.detail ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground tabular-nums">{entry.ip ?? '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {entries.links.length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-1" aria-label={t('Pages')}>
                        {entries.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                asChild={Boolean(link.url)}
                            >
                                {link.url ? (
                                    <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                )}
                            </Button>
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
