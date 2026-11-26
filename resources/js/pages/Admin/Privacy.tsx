import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import { NoticeFacts, NoticeText } from '@/components/privacy/NoticeText';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Circle, History, Printer } from 'lucide-react';
import { useState } from 'react';

interface Due {
    certificates: { name: string | null; leave: string }[];
    personal: { name: string; left: string }[];
    anonymize: { name: string; left: string }[];
    /** Applications not hired, older than the retention period. */
    candidates: number;
}

interface PrivacyRegisterProps {
    notice: NoticeFacts;
    acknowledged: number;
    employees: number;
    notAcknowledged: string[];
    due: Due;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Protection des données', href: '/admin/privacy' },
];

const frDate = (iso: string) => new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString(intlLocale());

function Check({ done, children }: { done: boolean; children: React.ReactNode }) {
    return (
        <li className="flex items-start gap-2 text-sm">
            {done ? (
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-700 dark:text-green-400" />
            ) : (
                <Circle className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
            )}
            <span>{children}</span>
        </li>
    );
}

/** For the Administrateur: what to declare to the CNDP, who accepted the notice, what is due for deletion. */
export default function PrivacyRegister({ notice, acknowledged, employees, notAcknowledged, due }: PrivacyRegisterProps) {
    const [applying, setApplying] = useState(false);
    const dueCount = due.certificates.length + due.personal.length + due.anonymize.length + due.candidates;

    const applyRetention = async () => {
        const ok = await confirmAction({
            title: t('Appliquer les durées de conservation ?'),
            description: t(
                'Supprimer définitivement :p0 certificat(s) médical(aux), les données de :p1 ancien(s) employé(s) anonymiser :p2 compte(s) et supprimer :p3 candidature(s) expirée(s) ? Cette action est irréversible.',
                { p0: due.certificates.length, p1: due.personal.length, p2: due.anonymize.length, p3: due.candidates },
            ),
            confirmLabel: t('Supprimer définitivement'),
            destructive: true,
        });
        if (!ok) return;
        setApplying(true);
        router.post('/admin/privacy/retention', {}, { preserveScroll: true, onError: toastErrors, onFinish: () => setApplying(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Protection des données')} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Protection des données')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('Loi n° 09-08 : registre du traitement, notice, journal des accès et conservation.')}
                        </p>
                    </div>
                    <div className="flex gap-2 print:hidden">
                        <Button variant="outline" asChild>
                            <Link href="/admin/privacy/log">
                                <History className="me-2 h-4 w-4" /> {t('Journal des accès')}
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="me-2 h-4 w-4" /> {t('Imprimer')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2 print:hidden">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Notice acceptée')}</CardTitle>
                            <CardDescription>
                                {acknowledged} {t('employé')}
                                {acknowledged > 1 ? 's' : ''} {t('actif')}
                                {acknowledged > 1 ? 's' : ''} {t('sur')} {employees}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {notAcknowledged.length === 0 ? (
                                <p className="text-sm text-green-700 dark:text-green-400">{t('Tout le monde a pris connaissance de la notice.')}</p>
                            ) : (
                                <>
                                    <p className="text-muted-foreground mb-2 text-xs">
                                        {t("Pas encore (la notice s'affiche à leur prochaine connexion) :")}
                                    </p>
                                    <div className="flex flex-wrap gap-1">
                                        {notAcknowledged.map((name) => (
                                            <Badge key={name} variant="outline">
                                                {name}
                                            </Badge>
                                        ))}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Durées de conservation')}</CardTitle>
                            <CardDescription>
                                {dueCount === 0 ? t('Rien à supprimer pour le moment.') : t(':p0 élément(s) arrivé(s) à échéance.', { p0: dueCount })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {due.certificates.length > 0 && (
                                <p>
                                    {due.certificates.length} {t('certificat(s) médical(aux) de plus de')}{' '}
                                    {notice.retention.medical_certificates_years} {t('ans.')}
                                </p>
                            )}
                            {due.personal.map((p) => (
                                <p key={`p-${p.name}`}>
                                    {t('Données de')} {p.name} {t('(parti le')} {frDate(p.left)}).
                                </p>
                            ))}
                            {due.anonymize.map((p) => (
                                <p key={`a-${p.name}`}>
                                    {t('Anonymiser')} {p.name} {t('(parti le')} {frDate(p.left)}).
                                </p>
                            ))}
                            {due.candidates > 0 && (
                                <p>
                                    {due.candidates} {t('candidature(s) non retenue(s) de plus de 2 ans, avec leur CV.')}
                                </p>
                            )}
                            {dueCount > 0 && (
                                <Button variant="destructive" size="sm" onClick={applyRetention} disabled={applying}>
                                    {t('Appliquer la conservation')}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="print:hidden">
                    <CardHeader>
                        <CardTitle>{t('Avant la mise en service')}</CardTitle>
                        <CardDescription>
                            {t("Démarches de l'entreprise auprès de la CNDP (www.cndp.ma) ; l'application fournit les informations ci-dessous.")}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2">
                            <Check done={Boolean(notice.cndpNumber)}>
                                {t('Déclarer le traitement « gestion du personnel » à la CNDP, puis saisir le n° de récépissé sur la page')}{' '}
                                <Link href="/admin/company" className="underline">
                                    {t('Entreprise')}
                                </Link>
                                .
                            </Check>
                            <Check done={false}>
                                {t(
                                    "Demander l'autorisation de la CNDP pour les données de santé (certificats médicaux des arrêts maladie), données sensibles.",
                                )}
                            </Check>
                            <Check done={false}>
                                {t(
                                    "Si l'hébergeur ou le service d'envoi d'e-mails est situé hors du Maroc, déclarer ce transfert de données à l'étranger.",
                                )}
                            </Check>
                            <Check done={Boolean(notice.contact)}>
                                {t('Indiquer un contact pour les questions sur les données (page Entreprise).')}
                            </Check>
                            <Check done={acknowledged === employees && employees > 0}>
                                {t('Faire accepter la notice par chaque employé (automatique à la connexion).')}
                            </Check>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Registre du traitement : gestion du personnel')}</CardTitle>
                        <CardDescription>{t('Le texte de la notice remise aux employés, à reprendre dans la déclaration CNDP.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <NoticeText notice={notice} />
                        <div className="mt-6 space-y-2 text-sm">
                            <h2 className="text-base font-semibold">{t('Mesures de sécurité')}</h2>
                            <ul className="text-muted-foreground list-disc space-y-1 ps-5">
                                <li>{t('accès par compte personnel et mot de passe ; rôles Administrateur, RH, Paie (lecture seule), employé ;')}</li>
                                <li>
                                    {t("données sensibles chiffrées en base (clé de l'application) ; documents stockés hors du dossier public ;")}
                                </li>
                                <li>
                                    {t("journal des consultations et modifications des données personnelles, consultable par l'Administrateur ;")}
                                </li>
                                <li>
                                    {t(
                                        "personne ne modifie ses propres données RH ; suppression et anonymisation à l'échéance des durées de conservation.",
                                    )}
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
