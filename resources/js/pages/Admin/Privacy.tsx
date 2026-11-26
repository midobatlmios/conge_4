import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import { NoticeFacts, NoticeText } from '@/components/privacy/NoticeText';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Circle, History, Printer } from 'lucide-react';
import { useState } from 'react';

interface Due {
    certificates: { name: string | null; leave: string }[];
    personal: { name: string; left: string }[];
    anonymize: { name: string; left: string }[];
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

const frDate = (iso: string) => new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString('fr-FR');

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
    const dueCount = due.certificates.length + due.personal.length + due.anonymize.length;

    const applyRetention = async () => {
        const ok = await confirmAction({
            title: 'Appliquer les durées de conservation ?',
            description: `Supprimer définitivement ${due.certificates.length} certificat(s) médical(aux), les données de ${due.personal.length} ancien(s) employé(s) et anonymiser ${due.anonymize.length} compte(s) ? Cette action est irréversible.`,
            confirmLabel: 'Supprimer définitivement',
            destructive: true,
        });
        if (!ok) return;
        setApplying(true);
        router.post('/admin/privacy/retention', {}, { preserveScroll: true, onError: toastErrors, onFinish: () => setApplying(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Protection des données" />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Protection des données</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Loi n° 09-08 : registre du traitement, notice, journal des accès et conservation.
                        </p>
                    </div>
                    <div className="flex gap-2 print:hidden">
                        <Button variant="outline" asChild>
                            <Link href="/admin/privacy/log">
                                <History className="me-2 h-4 w-4" /> Journal des accès
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="me-2 h-4 w-4" /> Imprimer
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2 print:hidden">
                    <Card>
                        <CardHeader>
                            <CardTitle>Notice acceptée</CardTitle>
                            <CardDescription>
                                {acknowledged} employé{acknowledged > 1 ? 's' : ''} actif{acknowledged > 1 ? 's' : ''} sur {employees}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {notAcknowledged.length === 0 ? (
                                <p className="text-sm text-green-700 dark:text-green-400">Tout le monde a pris connaissance de la notice.</p>
                            ) : (
                                <>
                                    <p className="text-muted-foreground mb-2 text-xs">
                                        Pas encore (la notice s'affiche à leur prochaine connexion) :
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
                            <CardTitle>Durées de conservation</CardTitle>
                            <CardDescription>
                                {dueCount === 0 ? 'Rien à supprimer pour le moment.' : `${dueCount} élément(s) arrivé(s) à échéance.`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {due.certificates.length > 0 && (
                                <p>
                                    {due.certificates.length} certificat(s) médical(aux) de plus de {notice.retention.medical_certificates_years} ans.
                                </p>
                            )}
                            {due.personal.map((p) => (
                                <p key={`p-${p.name}`}>
                                    Données de {p.name} (parti le {frDate(p.left)}).
                                </p>
                            ))}
                            {due.anonymize.map((p) => (
                                <p key={`a-${p.name}`}>
                                    Anonymiser {p.name} (parti le {frDate(p.left)}).
                                </p>
                            ))}
                            {dueCount > 0 && (
                                <Button variant="destructive" size="sm" onClick={applyRetention} disabled={applying}>
                                    Appliquer la conservation
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="print:hidden">
                    <CardHeader>
                        <CardTitle>Avant la mise en service</CardTitle>
                        <CardDescription>
                            Démarches de l'entreprise auprès de la CNDP (www.cndp.ma) ; l'application fournit les informations ci-dessous.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2">
                            <Check done={Boolean(notice.cndpNumber)}>
                                Déclarer le traitement « gestion du personnel » à la CNDP, puis saisir le n° de récépissé sur la page{' '}
                                <Link href="/admin/company" className="underline">
                                    Entreprise
                                </Link>
                                .
                            </Check>
                            <Check done={false}>
                                Demander l'autorisation de la CNDP pour les données de santé (certificats médicaux des arrêts maladie), données
                                sensibles.
                            </Check>
                            <Check done={false}>
                                Si l'hébergeur ou le service d'envoi d'e-mails est situé hors du Maroc, déclarer ce transfert de données à l'étranger.
                            </Check>
                            <Check done={Boolean(notice.contact)}>Indiquer un contact pour les questions sur les données (page Entreprise).</Check>
                            <Check done={acknowledged === employees && employees > 0}>
                                Faire accepter la notice par chaque employé (automatique à la connexion).
                            </Check>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Registre du traitement : gestion du personnel</CardTitle>
                        <CardDescription>Le texte de la notice remise aux employés, à reprendre dans la déclaration CNDP.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <NoticeText notice={notice} />
                        <div className="mt-6 space-y-2 text-sm">
                            <h2 className="text-base font-semibold">Mesures de sécurité</h2>
                            <ul className="text-muted-foreground list-disc space-y-1 ps-5">
                                <li>accès par compte personnel et mot de passe ; rôles Administrateur, RH, Paie (lecture seule), employé ;</li>
                                <li>données sensibles chiffrées en base (clé de l'application) ; documents stockés hors du dossier public ;</li>
                                <li>journal des consultations et modifications des données personnelles, consultable par l'Administrateur ;</li>
                                <li>
                                    personne ne modifie ses propres données RH ; suppression et anonymisation à l'échéance des durées de conservation.
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
