import { toastErrors } from '@/components/app-toaster';
import { promptValue } from '@/components/confirm-dialog';
import { frDate, Option, Person, STAGE_CLASS } from '@/components/recruitment/recruitment';
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
import { CalendarPlus, FileText, Mail, Phone, UserCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Interview = {
    id: number;
    scheduled_at: string;
    interviewer: string | null;
    mode: string | null;
    rating: string | null;
    rating_label: string | null;
    comment: string | null;
    can_evaluate: boolean;
};

interface CandidateProps {
    candidate: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        message: string | null;
        has_cv: boolean;
        cv_name: string | null;
        source: string;
        stage: string;
        stage_label: string;
        rejection_reason: string | null;
        applied_on: string;
        hired_user: Person | null;
    };
    offer: {
        id: number;
        title: string;
        department_id: number | null;
        department: string | null;
        contract_type: string;
        hiring_manager_id: number | null;
        hiring_manager: string | null;
    };
    interviews: Interview[];
    stages: Option[];
    ratings: Option[];
    canManage: boolean;
    people: Person[];
    departments: Person[];
    contractOptions: { types: Option[]; categories: Option[] };
}

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
const when = (iso: string) => new Date(iso).toLocaleString(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' });
const errorText = (message?: string) => message && <p className="text-sm text-red-700 dark:text-red-400">{message}</p>;

function ScheduleInterview({
    candidateId,
    people,
    defaultInterviewer,
}: {
    candidateId: number;
    people: Person[];
    defaultInterviewer: number | null;
}) {
    const form = useForm({ scheduled_at: '', interviewer_id: defaultInterviewer ? String(defaultInterviewer) : '', mode: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/recrutement/candidats/${candidateId}/entretiens`, { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <form onSubmit={submit} className="grid gap-3 rounded-md border border-dashed p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
            <div className="space-y-1">
                <Label htmlFor="i-when">{t('Date et heure')}</Label>
                <Input
                    id="i-when"
                    type="datetime-local"
                    value={form.data.scheduled_at}
                    onChange={(e) => form.setData('scheduled_at', e.target.value)}
                    required
                />
                {errorText(form.errors.scheduled_at)}
            </div>
            <div className="space-y-1">
                <Label htmlFor="i-who">{t('Interviewer')}</Label>
                <select
                    id="i-who"
                    className={selectClass}
                    value={form.data.interviewer_id}
                    onChange={(e) => form.setData('interviewer_id', e.target.value)}
                >
                    <option value="">—</option>
                    {people.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-1">
                <Label htmlFor="i-mode">{t('Lieu ou visio')}</Label>
                <Input id="i-mode" placeholder={t('Siège, Teams…')} value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)} />
            </div>
            <Button type="submit" disabled={form.processing}>
                <CalendarPlus className="me-1 h-4 w-4" /> {t('Planifier')}
            </Button>
        </form>
    );
}

function Evaluate({ interview, ratings }: { interview: Interview; ratings: Option[] }) {
    const form = useForm({ rating: interview.rating ?? '', comment: interview.comment ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/recrutement/entretiens/${interview.id}`, { preserveScroll: true });
    };
    return (
        <form onSubmit={submit} className="mt-2 grid gap-2 sm:grid-cols-[12rem_1fr_auto] sm:items-start">
            <select
                aria-label={t('Évaluation')}
                className={selectClass}
                value={form.data.rating}
                onChange={(e) => form.setData('rating', e.target.value)}
            >
                <option value="">{t('Évaluation…')}</option>
                {ratings.map((r) => (
                    <option key={r.value} value={r.value}>
                        {r.label}
                    </option>
                ))}
            </select>
            <Textarea
                aria-label={t('Commentaire')}
                rows={2}
                placeholder={t('Points forts, réserves, avis…')}
                value={form.data.comment}
                onChange={(e) => form.setData('comment', e.target.value)}
            />
            <Button type="submit" size="sm" disabled={form.processing}>
                {t('Enregistrer')}
            </Button>
            {errorText(form.errors.rating)}
        </form>
    );
}

/** Creates the employee from the candidate: account, record, contract, CV in the folder, invitation. */
function HireForm({ candidate, offer, people, departments, contractOptions, onCancel }: CandidateProps & { onCancel: () => void }) {
    const form = useForm({
        name: candidate.name,
        email: candidate.email,
        matricule: '',
        job_title: offer.title,
        department_id: offer.department_id ? String(offer.department_id) : '',
        manager_id: offer.hiring_manager_id ? String(offer.hiring_manager_id) : '',
        hire_date: '',
        contract_type: offer.contract_type,
        category: '',
        gross_salary: '',
        end_date: '',
        trial_end: '',
        close_offer: false as boolean,
        invite: true as boolean,
    });
    const { data, setData, errors, processing } = form;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/recrutement/candidats/${candidate.id}/embaucher`);
    };
    const field = (
        key: 'name' | 'email' | 'matricule' | 'job_title' | 'hire_date' | 'gross_salary' | 'end_date' | 'trial_end',
        label: string,
        type = 'text',
    ) => (
        <div className="space-y-1">
            <Label htmlFor={`h-${key}`}>{label}</Label>
            <Input id={`h-${key}`} type={type} value={data[key]} onChange={(e) => setData(key, e.target.value)} />
            {errorText(errors[key])}
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                {field('name', 'Nom et prénom')}
                {field('email', 'E-mail (connexion)', 'email')}
                {field('job_title', 'Poste')}
                {field('matricule', 'Matricule (facultatif)')}
                <div className="space-y-1">
                    <Label htmlFor="h-dep">{t('Département')}</Label>
                    <select id="h-dep" className={selectClass} value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                        <option value="">—</option>
                        {departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <Label htmlFor="h-manager">{t('Responsable')}</Label>
                    <select id="h-manager" className={selectClass} value={data.manager_id} onChange={(e) => setData('manager_id', e.target.value)}>
                        <option value="">—</option>
                        {people.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </div>
                {field('hire_date', "Date d'embauche", 'date')}
                <div className="space-y-1">
                    <Label htmlFor="h-type">{t('Contrat')}</Label>
                    <select id="h-type" className={selectClass} value={data.contract_type} onChange={(e) => setData('contract_type', e.target.value)}>
                        {contractOptions.types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <Label htmlFor="h-cat">{t('Catégorie')}</Label>
                    <select id="h-cat" className={selectClass} value={data.category} onChange={(e) => setData('category', e.target.value)}>
                        <option value="">—</option>
                        {contractOptions.categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </div>
                {field('gross_salary', 'Salaire brut mensuel (DH)', 'number')}
                {data.contract_type !== 'cdi' && field('end_date', 'Date de fin du contrat', 'date')}
                {field('trial_end', "Fin de période d'essai (facultatif)", 'date')}
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.invite} onChange={(e) => setData('invite', e.target.checked)} />
                {t("Envoyer l'invitation par e-mail (le nouvel employé choisit son mot de passe)")}
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.close_offer} onChange={(e) => setData('close_offer', e.target.checked)} />
                {t("Clôturer l'offre (poste pourvu)")}
            </label>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={processing}>
                    {t('Annuler')}
                </Button>
                <Button type="submit" disabled={processing}>
                    <UserCheck className="me-2 h-4 w-4" /> {t('Embaucher')}
                </Button>
            </div>
        </form>
    );
}

/** One candidate: details, CV, stage, interviews and evaluations, hiring. */
export default function CandidatePage(props: CandidateProps) {
    const { candidate, offer, interviews, stages, ratings, canManage, people } = props;
    const [hiring, setHiring] = useState(false);
    const active = !['hired', 'rejected'].includes(candidate.stage);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Recrutement'), href: '/recrutement' },
        { title: offer.title, href: `/recrutement/offres/${offer.id}` },
        { title: candidate.name, href: `/recrutement/candidats/${candidate.id}` },
    ];

    const move = (stage: string) =>
        router.put(`/recrutement/candidats/${candidate.id}/etape`, { stage }, { preserveScroll: true, onError: toastErrors });
    const reject = async () => {
        const reason = await promptValue({
            title: t('Refuser la candidature de :p0 ?', { p0: candidate.name }),
            label: t('Motif (interne, facultatif)'),
            multiline: true,
            confirmLabel: t('Refuser'),
            destructive: true,
        });
        if (reason !== null)
            router.put(
                `/recrutement/candidats/${candidate.id}/etape`,
                { stage: 'rejected', rejection_reason: reason },
                { preserveScroll: true, onError: toastErrors },
            );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={candidate.name} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{candidate.name}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {offer.title} {t('· candidature du')} {frDate(candidate.applied_on)} ·{' '}
                            {candidate.source === 'manual' ? t('ajoutée par RH') : t('page carrières')}
                        </p>
                    </div>
                    <Badge variant="outline" className={STAGE_CLASS[candidate.stage]}>
                        {t(candidate.stage_label)}
                    </Badge>
                </div>

                <Card>
                    <CardContent className="space-y-3 text-sm">
                        <p className="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <a href={`mailto:${candidate.email}`} className="inline-flex items-center gap-1 underline">
                                <Mail className="h-4 w-4" /> {candidate.email}
                            </a>
                            {candidate.phone && (
                                <a href={`tel:${candidate.phone}`} className="inline-flex items-center gap-1 underline">
                                    <Phone className="h-4 w-4" /> {candidate.phone}
                                </a>
                            )}
                            {candidate.has_cv && (
                                <a
                                    href={`/recrutement/candidats/${candidate.id}/cv`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 underline"
                                >
                                    <FileText className="h-4 w-4" /> {candidate.cv_name ?? t('CV')}
                                </a>
                            )}
                        </p>
                        {candidate.message && <p className="whitespace-pre-line">{candidate.message}</p>}
                        {candidate.rejection_reason && (
                            <p className="text-red-700 dark:text-red-400">
                                {t('Motif du refus :')} {candidate.rejection_reason}
                            </p>
                        )}
                        {candidate.hired_user && (
                            <p className="text-green-700 dark:text-green-400">
                                {t('Embauché(e) :')}{' '}
                                <Link href={`/admin/users/${candidate.hired_user.id}`} className="underline">
                                    {t('fiche de')} {candidate.hired_user.name}
                                </Link>
                            </p>
                        )}
                        {canManage && active && (
                            <div className="flex flex-wrap gap-2 border-t pt-3">
                                {stages
                                    .filter((s) => !['hired', 'rejected', candidate.stage].includes(s.value))
                                    .map((s) => (
                                        <Button key={s.value} size="sm" variant="outline" onClick={() => move(s.value)}>
                                            → {s.label}
                                        </Button>
                                    ))}
                                <Button size="sm" variant="outline" className="text-red-700 dark:text-red-400" onClick={reject}>
                                    {t('Refuser')}
                                </Button>
                                <Button size="sm" className="ms-auto" onClick={() => setHiring(true)}>
                                    <UserCheck className="me-1 h-4 w-4" /> {t('Embaucher')}
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {hiring && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('Embaucher')} {candidate.name}
                            </CardTitle>
                            <CardDescription>
                                {t("Crée le compte, le dossier employé et le contrat, range le CV dans son dossier et envoie l'invitation.")}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <HireForm {...props} onCancel={() => setHiring(false)} />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Entretiens')}</CardTitle>
                        <CardDescription>
                            {t("L'interviewer, le responsable du recrutement")}
                            {offer.hiring_manager ? ` (${offer.hiring_manager})` : ''} {t('et les RH évaluent ; les RH décident.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {canManage && active && (
                            <ScheduleInterview candidateId={candidate.id} people={people} defaultInterviewer={offer.hiring_manager_id} />
                        )}
                        {interviews.length === 0 && <p className="text-muted-foreground text-sm">{t('Aucun entretien planifié.')}</p>}
                        <ul className="divide-y">
                            {interviews.map((interview) => (
                                <li key={interview.id} className="py-3 text-sm">
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{when(interview.scheduled_at)}</span>
                                        <span className="text-muted-foreground">
                                            {[interview.interviewer, interview.mode].filter(Boolean).join(' · ')}
                                        </span>
                                        {interview.rating_label && <Badge variant="outline">{t(interview.rating_label)}</Badge>}
                                    </p>
                                    {interview.comment && !interview.can_evaluate && <p className="mt-1 whitespace-pre-line">{interview.comment}</p>}
                                    {interview.can_evaluate && <Evaluate interview={interview} ratings={ratings} />}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
