import EmptyState from '@/components/empty-state';
import { frDate } from '@/components/recruitment/recruitment';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Briefcase, ExternalLink, Plus } from 'lucide-react';

type Offer = {
    id: number;
    title: string;
    department: string | null;
    contract: string;
    status: string;
    status_label: string;
    open: boolean;
    closes_on: string | null;
    hiring_manager: string | null;
    candidates: number;
    active: number;
    new: number;
    slug: string;
};

interface IndexProps {
    offers: Offer[];
    stats: {
        open_offers: number;
        applications_month: number;
        in_progress: number;
        hired_year: number;
        days_to_hire: number | null;
        from_careers_page: number | null;
    };
    canManage: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Recrutement', href: '/recrutement' },
];

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

/** Recruitment: the offers with their candidates, and the key figures. */
export default function RecruitmentIndex({ offers, stats, canManage }: IndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Recrutement')} />
            <div className="container mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Recrutement')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('Offres publiées sur la')}{' '}
                            <a href="/carrieres" target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 underline">
                                {t('page carrières')} <ExternalLink className="h-3 w-3" />
                            </a>
                            {t(', candidatures, entretiens, embauche.')}
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href="/recrutement/offres/nouvelle">
                                <Plus className="me-2 h-4 w-4" /> {t('Nouvelle offre')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label={t('Offres ouvertes')} value={stats.open_offers} />
                    <Stat
                        label={t('Candidatures ce mois-ci')}
                        value={stats.applications_month}
                        hint={stats.from_careers_page !== null ? t(':p0 % via la page carrières', { p0: stats.from_careers_page }) : undefined}
                    />
                    <Stat label={t('Candidats en cours')} value={stats.in_progress} />
                    <Stat
                        label={t('Embauches cette année')}
                        value={stats.hired_year}
                        hint={
                            stats.days_to_hire !== null
                                ? t(":p0 jours en moyenne de la candidature à l'embauche", { p0: stats.days_to_hire })
                                : undefined
                        }
                    />
                </div>

                <Card>
                    <CardContent className="overflow-x-auto">
                        {offers.length === 0 ? (
                            <EmptyState
                                icon={Briefcase}
                                title={t('Aucune offre')}
                                description={t('Créez une offre, puis publiez-la pour recevoir des candidatures.')}
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Offre')}</TableHead>
                                        <TableHead>{t('État')}</TableHead>
                                        <TableHead>{t('Responsable')}</TableHead>
                                        <TableHead className="text-end">{t('Candidats')}</TableHead>
                                        <TableHead className="text-end">{t('En cours')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {offers.map((offer) => (
                                        <TableRow key={offer.id}>
                                            <TableCell>
                                                <Link href={`/recrutement/offres/${offer.id}`} className="font-medium hover:underline">
                                                    {offer.title}
                                                </Link>
                                                <span className="text-muted-foreground block text-xs">
                                                    {[offer.contract, offer.department, offer.closes_on && `jusqu'au ${frDate(offer.closes_on)}`]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={offer.open ? 'outline' : 'secondary'}
                                                    className={offer.open ? 'border-green-600 text-green-700 dark:text-green-400' : ''}
                                                >
                                                    {t(offer.status_label)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{offer.hiring_manager ?? '—'}</TableCell>
                                            <TableCell className="text-end tabular-nums">
                                                {offer.candidates}
                                                {offer.new > 0 && (
                                                    <Badge className="ms-2" variant="default">
                                                        {offer.new} {t('nouv.')}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">{offer.active}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
