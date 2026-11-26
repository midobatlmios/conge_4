import { toastErrors } from '@/components/app-toaster';
import { frDate, Option, STAGE_CLASS } from '@/components/recruitment/recruitment';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, FileText, Pencil, UserPlus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type CandidateCard = {
    id: number;
    name: string;
    stage: string;
    source: string;
    applied_on: string;
    has_cv: boolean;
    interviews: number;
    rating: string | null;
};

interface OfferProps {
    offer: {
        id: number;
        title: string;
        slug: string;
        department: string | null;
        location: string | null;
        contract: string;
        status: string;
        status_label: string;
        open: boolean;
        closes_on: string | null;
        hiring_manager: string | null;
        description: string;
        requirements: string | null;
    };
    candidates: CandidateCard[];
    stages: Option[];
    canManage: boolean;
}

/** HR adds a candidate received elsewhere. */
function AddCandidate({ offerId, onDone }: { offerId: number; onDone: () => void }) {
    const form = useForm<{ name: string; email: string; phone: string; message: string; cv: File | null }>({
        name: '',
        email: '',
        phone: '',
        message: '',
        cv: null,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/recrutement/offres/${offerId}/candidats`, { forceFormData: true });
    };
    const error = (key: keyof typeof form.errors) => form.errors[key] && <p className="text-sm text-red-700 dark:text-red-400">{form.errors[key]}</p>;
    return (
        <form onSubmit={submit} className="grid gap-3 rounded-md border border-dashed p-4 sm:grid-cols-2">
            <div className="space-y-1">
                <Label htmlFor="c-name">{t('Nom')}</Label>
                <Input id="c-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                {error('name')}
            </div>
            <div className="space-y-1">
                <Label htmlFor="c-email">{t('E-mail')}</Label>
                <Input id="c-email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                {error('email')}
            </div>
            <div className="space-y-1">
                <Label htmlFor="c-phone">{t('Téléphone')}</Label>
                <Input id="c-phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="c-cv">{t('CV (PDF ou Word)')}</Label>
                <Input id="c-cv" type="file" accept=".pdf,.doc,.docx" onChange={(e) => form.setData('cv', e.target.files?.[0] ?? null)} />
                {error('cv')}
            </div>
            <div className="space-y-1 sm:col-span-2">
                <Label htmlFor="c-notes">{t('Notes (source, cooptation…)')}</Label>
                <Textarea id="c-notes" rows={2} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} />
            </div>
            <div className="flex justify-end gap-2 sm:col-span-2">
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('Annuler')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {t('Ajouter')}
                </Button>
            </div>
        </form>
    );
}

/** One offer: its candidates as columns by stage. */
export default function Offer({ offer, candidates, stages, canManage }: OfferProps) {
    const [adding, setAdding] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Recrutement'), href: '/recrutement' },
        { title: offer.title, href: `/recrutement/offres/${offer.id}` },
    ];
    const setStatus = (status: string) =>
        router.put(`/recrutement/offres/${offer.id}/statut`, { status }, { preserveScroll: true, onError: toastErrors });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={offer.title} />
            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{offer.title}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {[
                                offer.contract,
                                offer.department,
                                offer.location,
                                offer.hiring_manager && `Responsable : ${offer.hiring_manager}`,
                                offer.closes_on && `jusqu'au ${frDate(offer.closes_on)}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={offer.open ? 'outline' : 'secondary'}>{t(offer.status_label)}</Badge>
                        {offer.status !== 'draft' && (
                            <Button variant="ghost" size="sm" asChild>
                                <a href={`/carrieres/${offer.slug}`} target="_blank" rel="noreferrer">
                                    <ExternalLink className="me-1 h-4 w-4" /> {t("Voir l'annonce")}
                                </a>
                            </Button>
                        )}
                        {canManage && (
                            <>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/recrutement/offres/${offer.id}/modifier`}>
                                        <Pencil className="me-1 h-4 w-4" /> {t('Modifier')}
                                    </Link>
                                </Button>
                                {offer.status !== 'published' && (
                                    <Button size="sm" onClick={() => setStatus('published')}>
                                        {t('Publier')}
                                    </Button>
                                )}
                                {offer.status === 'published' && (
                                    <Button size="sm" variant="outline" onClick={() => setStatus('closed')}>
                                        {t('Clôturer')}
                                    </Button>
                                )}
                                {!adding && (
                                    <Button size="sm" variant="outline" onClick={() => setAdding(true)}>
                                        <UserPlus className="me-1 h-4 w-4" /> {t('Ajouter un candidat')}
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {adding && <AddCandidate offerId={offer.id} onDone={() => setAdding(false)} />}

                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    {stages.map((stage) => {
                        const column = candidates.filter((c) => c.stage === stage.value);
                        return (
                            <section key={stage.value} aria-label={stage.label} className="bg-muted/40 flex flex-col gap-2 rounded-lg p-2">
                                <h2 className="flex items-center justify-between px-1 text-sm font-semibold">
                                    {stage.label} <span className="text-muted-foreground font-normal">{column.length}</span>
                                </h2>
                                {column.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={`/recrutement/candidats/${c.id}`}
                                        className={`bg-background hover:bg-accent rounded-md border-s-4 p-2 text-sm shadow-sm ${STAGE_CLASS[c.stage]}`}
                                    >
                                        <p className="text-foreground font-medium">{c.name}</p>
                                        <p className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
                                            <span>{frDate(c.applied_on)}</span>
                                            {c.source === 'manual' && <span>{t('ajouté par RH')}</span>}
                                            {c.has_cv && <FileText className="h-3 w-3" aria-label={t('CV joint')} />}
                                            {c.interviews > 0 && (
                                                <span>
                                                    {c.interviews} {t('entretien(s)')}
                                                </span>
                                            )}
                                        </p>
                                        {c.rating && (
                                            <Badge variant="outline" className="mt-1 text-xs">
                                                {c.rating}
                                            </Badge>
                                        )}
                                    </Link>
                                ))}
                                {column.length === 0 && <p className="text-muted-foreground px-1 text-xs">—</p>}
                            </section>
                        );
                    })}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t("L'annonce")}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <p className="whitespace-pre-line">{offer.description}</p>
                        {offer.requirements && (
                            <div>
                                <p className="font-medium">{t('Profil recherché')}</p>
                                <p className="whitespace-pre-line">{offer.requirements}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
