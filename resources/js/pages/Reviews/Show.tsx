import { confirmAction } from '@/components/confirm-dialog';
import { Objective, Option, RATING_CLASS, Review, STEP_LABELS, STEPS } from '@/components/reviews/reviews';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Plus, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

interface ShowProps {
    review: Review;
    objectives: Objective[];
    nextObjectives: Objective[];
    ratings: Option[];
    recommendations: Option[];
}

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
const frDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale());
const errorText = (message?: string) => message && <p className="text-sm text-red-700 dark:text-red-400">{message}</p>;

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <p className="text-muted-foreground text-sm">{label}</p>
            <div className="text-sm whitespace-pre-line">{children || <span className="text-muted-foreground">—</span>}</div>
        </div>
    );
}

function RatingBadge({ value, label }: { value: string | null; label?: string | null }) {
    if (!value) return <span className="text-muted-foreground">—</span>;
    return (
        <Badge variant="outline" className={RATING_CLASS[value] ?? ''}>
            {label ?? value}
        </Badge>
    );
}

function RatingSelect({ id, value, onChange, ratings }: { id: string; value: string; onChange: (v: string) => void; ratings: Option[] }) {
    return (
        <select id={id} className={selectClass} value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">{t('Choisir…')}</option>
            {ratings.map((r) => (
                <option key={r.value} value={r.value}>
                    {t(r.label)}
                </option>
            ))}
        </select>
    );
}

/** Step 1: the employee's self-evaluation. */
function SelfForm({ review, ratings }: { review: Review; ratings: Option[] }) {
    const form = useForm({
        self_rating: review.self?.rating ?? '',
        self_assessment: review.self?.assessment ?? '',
        self_wishes: review.self?.wishes ?? '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/evaluations/${review.id}/self`, { preserveScroll: true });
    };
    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-1">
                <Label htmlFor="self-assessment">{t('Votre bilan de l’année : réussites, difficultés, objectifs atteints')}</Label>
                <Textarea
                    id="self-assessment"
                    rows={5}
                    value={form.data.self_assessment}
                    onChange={(e) => form.setData('self_assessment', e.target.value)}
                />
                {errorText(form.errors.self_assessment)}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="self-rating">{t('Votre appréciation globale')}</Label>
                    <RatingSelect id="self-rating" value={form.data.self_rating} onChange={(v) => form.setData('self_rating', v)} ratings={ratings} />
                    {errorText(form.errors.self_rating)}
                </div>
            </div>
            <div className="space-y-1">
                <Label htmlFor="self-wishes">{t('Vos souhaits : formation, évolution, mobilité (facultatif)')}</Label>
                <Textarea id="self-wishes" rows={3} value={form.data.self_wishes} onChange={(e) => form.setData('self_wishes', e.target.value)} />
            </div>
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {t('Envoyer à mon responsable')}
                </Button>
            </div>
        </form>
    );
}

type NewObjective = { title: string; target: string; weight: string };

/** Step 2: the manager rates the year, gives feedback, sets next year's objectives, may recommend. */
function ManagerForm({ review, objectives, nextObjectives, ratings, recommendations }: ShowProps) {
    const form = useForm({
        overall_rating: review.evaluation?.rating ?? '',
        manager_feedback: review.evaluation?.feedback ?? '',
        strengths: review.evaluation?.strengths ?? '',
        improvements: review.evaluation?.improvements ?? '',
        training_needs: review.evaluation?.training_needs ?? '',
        interview_on: review.evaluation?.interview_on ?? '',
        ratings: Object.fromEntries(objectives.map((o) => [o.id, { rating: o.rating ?? '', comment: o.rating_comment ?? '' }])) as Record<
            number,
            { rating: string; comment: string }
        >,
        objectives: nextObjectives.map((o) => ({ title: o.title, target: o.target ?? '', weight: String(o.weight) })) as NewObjective[],
        recommendation: review.recommendation?.kind ?? '',
        recommended_job_title: review.recommendation?.job_title ?? '',
        recommended_salary: review.recommendation?.salary == null ? '' : String(review.recommendation.salary),
        recommendation_reason: review.recommendation?.reason ?? '',
    });
    const { data, setData, errors, processing } = form;
    const err = (key: string) => errorText((errors as Record<string, string>)[key]);
    const totalWeight = data.objectives.reduce((sum, o) => sum + (Number(o.weight) || 0), 0);
    const setObjective = (i: number, patch: Partial<NewObjective>) =>
        setData(
            'objectives',
            data.objectives.map((o, j) => (j === i ? { ...o, ...patch } : o)),
        );

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const ok = await confirmAction({
            title: t('Envoyer l’évaluation ?'),
            description: t('L’employé pourra la lire et la commenter. Elle ne sera plus modifiable.'),
            confirmLabel: t('Envoyer'),
        });
        if (ok) form.put(`/evaluations/${review.id}/manager`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {objectives.length > 0 && (
                <section className="space-y-3">
                    <h3 className="font-semibold">{t('Objectifs :year', { year: review.year })}</h3>
                    {objectives.map((o) => (
                        <div key={o.id} className="grid gap-2 rounded-md border p-3 sm:grid-cols-[1fr_12rem]">
                            <div className="text-sm">
                                <p className="font-medium">
                                    {o.title} <span className="text-muted-foreground">· {o.weight} %</span>
                                </p>
                                {o.target && <p className="text-muted-foreground">{o.target}</p>}
                                <Input
                                    className="mt-2"
                                    placeholder={t('Commentaire (facultatif)')}
                                    value={data.ratings[o.id]?.comment ?? ''}
                                    onChange={(e) =>
                                        setData('ratings', { ...data.ratings, [o.id]: { ...data.ratings[o.id], comment: e.target.value } })
                                    }
                                />
                            </div>
                            <div>
                                <RatingSelect
                                    id={`objective-${o.id}`}
                                    value={data.ratings[o.id]?.rating ?? ''}
                                    onChange={(v) => setData('ratings', { ...data.ratings, [o.id]: { ...data.ratings[o.id], rating: v } })}
                                    ratings={ratings}
                                />
                                {err(`ratings.${o.id}.rating`)}
                            </div>
                        </div>
                    ))}
                </section>
            )}

            <section className="space-y-3">
                <h3 className="font-semibold">{t('Appréciation du responsable')}</h3>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label htmlFor="overall">{t('Appréciation globale')}</Label>
                        <RatingSelect id="overall" value={data.overall_rating} onChange={(v) => setData('overall_rating', v)} ratings={ratings} />
                        {err('overall_rating')}
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="interview">{t("Date de l'entretien")}</Label>
                        <Input id="interview" type="date" value={data.interview_on} onChange={(e) => setData('interview_on', e.target.value)} />
                        {err('interview_on')}
                    </div>
                </div>
                <div className="space-y-1">
                    <Label htmlFor="feedback">{t('Bilan de l’année')}</Label>
                    <Textarea id="feedback" rows={4} value={data.manager_feedback} onChange={(e) => setData('manager_feedback', e.target.value)} />
                    {err('manager_feedback')}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="space-y-1">
                        <Label htmlFor="strengths">{t('Points forts')}</Label>
                        <Textarea id="strengths" rows={3} value={data.strengths} onChange={(e) => setData('strengths', e.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="improvements">{t('Axes de progrès')}</Label>
                        <Textarea id="improvements" rows={3} value={data.improvements} onChange={(e) => setData('improvements', e.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="training">{t('Besoins de formation')}</Label>
                        <Textarea id="training" rows={3} value={data.training_needs} onChange={(e) => setData('training_needs', e.target.value)} />
                    </div>
                </div>
            </section>

            <section className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <h3 className="font-semibold">{t('Objectifs :year', { year: review.year + 1 })}</h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={data.objectives.length >= 5}
                        onClick={() => setData('objectives', [...data.objectives, { title: '', target: '', weight: '' }])}
                    >
                        <Plus className="me-1 h-4 w-4" /> {t('Objectif')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-xs">
                    {t('3 à 5 objectifs, avec un poids : le total doit faire 100 %. Ils seront évalués au prochain entretien.')}
                </p>
                {data.objectives.map((o, i) => (
                    <div key={i} className="grid gap-2 rounded-md border p-3 sm:grid-cols-[1fr_6rem_auto]">
                        <div className="space-y-2">
                            <Input
                                aria-label={t('Intitulé')}
                                placeholder={t('Intitulé')}
                                value={o.title}
                                onChange={(e) => setObjective(i, { title: e.target.value })}
                            />
                            <Input
                                aria-label={t('Cible / indicateur')}
                                placeholder={t('Cible / indicateur (facultatif)')}
                                value={o.target}
                                onChange={(e) => setObjective(i, { target: e.target.value })}
                            />
                            {err(`objectives.${i}.title`)}
                        </div>
                        <Input
                            aria-label={t('Poids (%)')}
                            type="number"
                            min={0}
                            max={100}
                            placeholder="%"
                            value={o.weight}
                            onChange={(e) => setObjective(i, { weight: e.target.value })}
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            aria-label={t('Retirer cet objectif')}
                            onClick={() =>
                                setData(
                                    'objectives',
                                    data.objectives.filter((_, j) => j !== i),
                                )
                            }
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                ))}
                {data.objectives.length > 0 && (
                    <p className={`text-sm ${totalWeight === 100 ? 'text-muted-foreground' : 'text-orange-700 dark:text-orange-400'}`}>
                        {t('Total des poids : :total %', { total: totalWeight })}
                    </p>
                )}
                {err('objectives')}
            </section>

            <section className="space-y-3 rounded-md border border-dashed p-4">
                <h3 className="font-semibold">{t('Recommandation (vue par les RH, pas par l’employé)')}</h3>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="space-y-1">
                        <Label htmlFor="recommendation">{t('Recommander')}</Label>
                        <select
                            id="recommendation"
                            className={selectClass}
                            value={data.recommendation}
                            onChange={(e) => setData('recommendation', e.target.value)}
                        >
                            <option value="">{t('Aucune')}</option>
                            {recommendations.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {t(r.label)}
                                </option>
                            ))}
                        </select>
                    </div>
                    {data.recommendation && (
                        <>
                            <div className="space-y-1">
                                <Label htmlFor="rec-job">{t('Poste proposé')}</Label>
                                <Input
                                    id="rec-job"
                                    value={data.recommended_job_title}
                                    onChange={(e) => setData('recommended_job_title', e.target.value)}
                                />
                                {err('recommended_job_title')}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="rec-salary">{t('Salaire brut proposé (DH)')}</Label>
                                <Input
                                    id="rec-salary"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.recommended_salary}
                                    onChange={(e) => setData('recommended_salary', e.target.value)}
                                />
                                {err('recommended_salary')}
                            </div>
                        </>
                    )}
                </div>
                {data.recommendation && (
                    <div className="space-y-1">
                        <Label htmlFor="rec-reason">{t('Justification')}</Label>
                        <Textarea
                            id="rec-reason"
                            rows={2}
                            value={data.recommendation_reason}
                            onChange={(e) => setData('recommendation_reason', e.target.value)}
                        />
                        {err('recommendation_reason')}
                    </div>
                )}
            </section>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {t('Envoyer l’évaluation')}
                </Button>
            </div>
        </form>
    );
}

/** Step 3: the employee reads and comments. */
function CommentForm({ review }: { review: Review }) {
    const form = useForm({ employee_comment: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/evaluations/${review.id}/comment`, { preserveScroll: true });
    };
    return (
        <form onSubmit={submit} className="space-y-3">
            <Label htmlFor="comment">{t('Votre commentaire sur l’évaluation (facultatif)')}</Label>
            <Textarea id="comment" rows={3} value={form.data.employee_comment} onChange={(e) => form.setData('employee_comment', e.target.value)} />
            {errorText(form.errors.employee_comment)}
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <Check className="me-2 h-4 w-4" /> {t('Signer l’entretien')}
                </Button>
            </div>
        </form>
    );
}

/** One annual review: every step on one page, the form of whoever's turn it is. */
export default function Show(props: ShowProps) {
    const { review, objectives, nextObjectives, ratings } = props;
    const stepIndex = STEPS.indexOf(review.status);
    const ratingLabel = (value: string | null) => ratings.find((r) => r.value === value)?.label ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Tableau de bord', href: '/dashboard' },
        { title: 'Entretiens annuels', href: '/evaluations' },
        { title: `${review.employee.name} · ${review.year}`, href: `/evaluations/${review.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Entretien annuel :year', { year: review.year })} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <Link href="/evaluations" className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm">
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('Entretiens annuels')}
                </Link>
                <div>
                    <h1 className="text-2xl font-bold">
                        {t('Entretien annuel :year', { year: review.year })} · {review.employee.name}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {[review.employee.job_title, review.manager && t('Responsable : :name', { name: review.manager })]
                            .filter(Boolean)
                            .join(' · ')}
                        {review.due_on && ` · ${t('À terminer avant le :date', { date: frDate(review.due_on) })}`}
                    </p>
                </div>

                <ol className="flex flex-wrap gap-2 text-xs" aria-label={t('Étapes')}>
                    {STEPS.map((step, i) => (
                        <li
                            key={step}
                            aria-current={i === stepIndex ? 'step' : undefined}
                            className={`rounded-full border px-3 py-1 ${i < stepIndex ? 'text-muted-foreground line-through' : ''} ${i === stepIndex ? 'bg-primary text-primary-foreground' : ''}`}
                        >
                            {i + 1}. {STEP_LABELS[step]}
                        </li>
                    ))}
                </ol>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Auto-évaluation')}</CardTitle>
                        <CardDescription>{t("Le bilan de l'année par l'employé.")}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {review.status === 'self' && review.can_act ? (
                            <SelfForm review={review} ratings={ratings} />
                        ) : review.self && review.status !== 'self' ? (
                            <>
                                <Field label={t('Appréciation globale')}>
                                    <RatingBadge
                                        value={review.self.rating}
                                        label={ratingLabel(review.self.rating) && t(ratingLabel(review.self.rating)!)}
                                    />
                                </Field>
                                <Field label={t('Bilan')}>{review.self.assessment}</Field>
                                <Field label={t('Souhaits')}>{review.self.wishes}</Field>
                            </>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t('En attente de l’auto-évaluation.')}</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Évaluation du responsable')}</CardTitle>
                        <CardDescription>{t('Objectifs de l’année, appréciation, objectifs de l’année prochaine.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {review.status === 'manager' && review.can_act ? (
                            <ManagerForm {...props} />
                        ) : review.evaluation && stepIndex > 1 ? (
                            <>
                                {objectives.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-sm">{t('Objectifs :year', { year: review.year })}</p>
                                        <ul className="divide-y rounded-md border">
                                            {objectives.map((o) => (
                                                <li key={o.id} className="flex flex-wrap items-start justify-between gap-2 px-3 py-2 text-sm">
                                                    <span>
                                                        <span className="font-medium">{o.title}</span>{' '}
                                                        <span className="text-muted-foreground">· {o.weight} %</span>
                                                        {o.rating_comment && (
                                                            <span className="text-muted-foreground block text-xs">{o.rating_comment}</span>
                                                        )}
                                                    </span>
                                                    <RatingBadge value={o.rating} label={o.rating_label && t(o.rating_label)} />
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                <Field label={t('Appréciation globale')}>
                                    <RatingBadge
                                        value={review.evaluation.rating}
                                        label={review.evaluation.rating_label && t(review.evaluation.rating_label)}
                                    />
                                </Field>
                                <Field label={t('Bilan de l’année')}>{review.evaluation.feedback}</Field>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field label={t('Points forts')}>{review.evaluation.strengths}</Field>
                                    <Field label={t('Axes de progrès')}>{review.evaluation.improvements}</Field>
                                    <Field label={t('Besoins de formation')}>{review.evaluation.training_needs}</Field>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {[
                                        review.evaluation.reviewed_by && t('Évalué par :name', { name: review.evaluation.reviewed_by }),
                                        review.evaluation.interview_on && t('entretien le :date', { date: frDate(review.evaluation.interview_on) }),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                {nextObjectives.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-sm">{t('Objectifs :year', { year: review.year + 1 })}</p>
                                        <ul className="divide-y rounded-md border">
                                            {nextObjectives.map((o) => (
                                                <li key={o.id} className="px-3 py-2 text-sm">
                                                    <span className="font-medium">{o.title}</span>{' '}
                                                    <span className="text-muted-foreground">· {o.weight} %</span>
                                                    {o.target && <span className="text-muted-foreground block text-xs">{o.target}</span>}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                {review.recommendation?.kind && (
                                    <div className="rounded-md border border-dashed p-3 text-sm">
                                        <p className="font-medium">
                                            {t('Recommandation : :kind', { kind: t(review.recommendation.kind_label ?? '') })}
                                            {review.recommendation.status === 'applied' && ` · ${t('appliquée')}`}
                                            {review.recommendation.status === 'declined' && ` · ${t('écartée')}`}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {[
                                                review.recommendation.job_title,
                                                review.recommendation.salary && `${review.recommendation.salary.toLocaleString(intlLocale())} DH`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        {review.recommendation.reason && <p className="mt-1 whitespace-pre-line">{review.recommendation.reason}</p>}
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                {review.is_employee && stepIndex <= 1
                                    ? t('Votre responsable remplira cette partie après votre auto-évaluation.')
                                    : t('Pas encore rempli.')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Commentaire de l’employé et clôture')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {review.status === 'comment' && review.can_act ? (
                            <CommentForm review={review} />
                        ) : stepIndex > 2 ? (
                            <Field label={t('Commentaire de l’employé')}>{review.employee_comment}</Field>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t('Après l’évaluation du responsable.')}</p>
                        )}
                        {review.status === 'hr' && review.can_act && (
                            <div className="flex justify-end">
                                <Button onClick={() => router.put(`/evaluations/${review.id}/close`, {}, { preserveScroll: true })}>
                                    {t("Clôturer l'entretien")}
                                </Button>
                            </div>
                        )}
                        {review.status === 'closed' && <Badge variant="secondary">{t('Entretien clôturé')}</Badge>}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
