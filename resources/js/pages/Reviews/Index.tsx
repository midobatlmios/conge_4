import EmptyState from '@/components/empty-state';
import { Objective, ReviewSummary } from '@/components/reviews/reviews';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MessagesSquare, Target } from 'lucide-react';

interface IndexProps {
    mine: ReviewSummary[];
    team: ReviewSummary[];
    objectives: Objective[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Entretiens annuels', href: '/evaluations' },
];
const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale());

function ReviewRow({ review, showName }: { review: ReviewSummary; showName: boolean }) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
            <div>
                <p className="font-medium">{showName ? review.name : t('Entretien :year', { year: review.year })}</p>
                <p className="text-muted-foreground text-xs">
                    {[
                        showName ? review.job_title : null,
                        t(review.status_label),
                        review.due_on && t('avant le :date', { date: frDate(review.due_on) }),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
            <div className="flex items-center gap-2">
                {review.rating_label && <Badge variant="outline">{t(review.rating_label)}</Badge>}
                <Button size="sm" variant={review.can_act ? 'default' : 'outline'} asChild>
                    <Link href={`/evaluations/${review.id}`}>{review.can_act ? t('À remplir') : t('Ouvrir')}</Link>
                </Button>
            </div>
        </li>
    );
}

/** The employee's reviews and objectives, and the reviews of their team. */
export default function Index({ mine, team, objectives }: IndexProps) {
    const years = [...new Set(objectives.map((o) => o.year))];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Entretiens annuels')} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">{t('Entretiens annuels')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {t('Chaque année : votre auto-évaluation, l’évaluation de votre responsable, votre commentaire, puis la clôture par les RH.')}
                    </p>
                </div>

                {team.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Mon équipe')}</CardTitle>
                            <CardDescription>{t('Les entretiens de la campagne en cours à mener avec votre équipe.')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {team.map((review) => (
                                    <ReviewRow key={review.id} review={review} showName />
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Mes entretiens')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {mine.length === 0 ? (
                            <EmptyState
                                icon={MessagesSquare}
                                title={t('Aucun entretien pour l’instant')}
                                description={t('Il apparaîtra ici quand le service RH ouvrira la campagne de l’année.')}
                            />
                        ) : (
                            <ul className="divide-y">
                                {mine.map((review) => (
                                    <ReviewRow key={review.id} review={review} showName={false} />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Target className="h-4 w-4" /> {t('Mes objectifs')}
                        </CardTitle>
                        <CardDescription>{t('Fixés lors de l’entretien, évalués à l’entretien suivant.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {objectives.length === 0 && <p className="text-muted-foreground text-sm">{t('Aucun objectif pour l’instant.')}</p>}
                        {years.map((year) => (
                            <div key={year} className="space-y-2">
                                <p className="text-muted-foreground text-sm font-medium">{year}</p>
                                <ul className="divide-y rounded-md border">
                                    {objectives
                                        .filter((o) => o.year === year)
                                        .map((o) => (
                                            <li key={o.id} className="flex flex-wrap items-start justify-between gap-2 px-3 py-2 text-sm">
                                                <span>
                                                    <span className="font-medium">{o.title}</span>{' '}
                                                    <span className="text-muted-foreground">· {o.weight} %</span>
                                                    {o.target && <span className="text-muted-foreground block text-xs">{o.target}</span>}
                                                </span>
                                                {o.rating_label && <Badge variant="outline">{t(o.rating_label)}</Badge>}
                                            </li>
                                        ))}
                                </ul>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
