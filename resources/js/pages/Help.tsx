import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { ReactNode } from 'react';

type Topic = { title: string; steps: ReactNode[]; link?: { href: string; label: string } };
type Guide = { id: string; title: string; intro: string; topics: Topic[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Aide', href: '/aide' },
];

/** The user guide (French), by role. Each topic links to the screen it describes. */
const GUIDES: Guide[] = [
    {
        id: 'employe',
        title: 'Employé',
        intro: 'Ce que chaque salarié fait dans l’application.',
        topics: [
            {
                title: 'Demander un congé',
                steps: [
                    'Espace employé → « Nouvelle demande » : choisissez le type, les dates (demi-journées possibles) et un commentaire.',
                    'Le nombre de jours ouvrés est calculé tout de suite, hors week-ends et jours fériés. Un avertissement orange signale un dépassement de votre solde : la demande part quand même, mais les RH ne pourront pas l’accepter en l’état.',
                    'Mariage, naissance, décès : joignez le justificatif (vous pouvez l’ajouter après).',
                    'Votre responsable valide d’abord, puis les RH décident. Vous êtes prévenu par notification et par e-mail.',
                ],
                link: { href: '/dashboard', label: 'Ouvrir mon espace' },
            },
            {
                title: 'Arrêt maladie',
                steps: [
                    'Déclarez-le dans les 48 heures (type « Congé maladie »), avec le certificat médical.',
                    'Il ne réduit pas vos jours de congé ; les RH s’occupent du dossier CNSS.',
                ],
            },
            {
                title: 'Annuler un congé accepté',
                steps: [
                    'Dans « Mes demandes », « Demander l’annulation » tant que le congé n’a pas commencé. Les RH décident ; les jours vous sont rendus.',
                ],
            },
            {
                title: 'Pointer',
                steps: [
                    'Sur votre espace : « Pointer l’arrivée » le matin, « Pointer le départ » le soir. L’heure est celle du serveur.',
                    'Un oubli ou une erreur ? « Mes pointages » → « Corriger » sur le jour concerné, avec le motif. Votre responsable valide.',
                ],
                link: { href: '/pointage', label: 'Mes pointages' },
            },
            {
                title: 'Entretien annuel',
                steps: [
                    'Quand la campagne est ouverte : « Entretiens annuels » → remplissez votre auto-évaluation.',
                    'Après l’évaluation de votre responsable, lisez-la, ajoutez un commentaire si vous le souhaitez et signez.',
                    'Vos objectifs de l’année sont dans « Mes objectifs ».',
                ],
                link: { href: '/evaluations', label: 'Entretiens annuels' },
            },
            {
                title: 'Documents, attestations, bulletins',
                steps: [
                    'Paramètres → « Mes attestations » : demandez une attestation de travail ou de salaire, puis téléchargez-la une fois délivrée.',
                    'Paramètres → « Mes bulletins » : vos bulletins de paie, une fois la paie du mois validée.',
                    'Paramètres → « Données personnelles » et « Mes documents » : votre dossier (lecture seule ; une erreur ? signalez-la aux RH).',
                ],
                link: { href: '/settings/attestations', label: 'Mes attestations' },
            },
            {
                title: 'Langue et calendrier',
                steps: [
                    'Choisissez Français, العربية ou English en haut de la page ; votre choix est gardé sur votre compte.',
                    'Paramètres → « Calendrier » : abonnez Outlook, Google Agenda ou votre téléphone à vos congés.',
                ],
            },
        ],
    },
    {
        id: 'responsable',
        title: 'Responsable d’équipe',
        intro: 'Pour les personnes qui encadrent une équipe.',
        topics: [
            {
                title: 'Valider les demandes de l’équipe',
                steps: [
                    '« Mon équipe » : validez ou refusez (un refus est définitif ; une validation transmet la demande aux RH).',
                    'Le calendrier montre qui est absent, pour éviter que toute l’équipe parte en même temps.',
                ],
                link: { href: '/equipe', label: 'Mon équipe' },
            },
            {
                title: 'Pendant vos congés',
                steps: [
                    '« Mon équipe » → « Délégation » : confiez la validation à un collègue pour vos dates d’absence. Il reçoit les demandes et les rappels.',
                ],
            },
            {
                title: 'Corrections de pointage',
                steps: ['« Corrections de pointage » : acceptez (le pointage du jour est remplacé) ou refusez avec un motif.'],
                link: { href: '/pointage/corrections', label: 'Corrections de pointage' },
            },
            {
                title: 'Entretiens annuels',
                steps: [
                    'Après l’auto-évaluation, évaluez chaque objectif de l’année, donnez votre appréciation, fixez 3 à 5 objectifs pour l’année suivante (100 % au total).',
                    'Vous pouvez recommander une promotion ou une augmentation : seuls les RH la voient et décident.',
                ],
            },
            {
                title: 'Recrutement',
                steps: [
                    'Si vous êtes responsable d’une offre : « Recrutement » → consultez les candidats et les CV, évaluez les entretiens. Les RH décident.',
                ],
            },
        ],
    },
    {
        id: 'rh',
        title: 'Ressources humaines',
        intro: 'Mise en service, puis le travail de chaque jour, de chaque mois et de chaque année.',
        topics: [
            {
                title: 'Mise en service (une fois)',
                steps: [
                    '« Entreprise » : raison sociale, adresse, ICE, RC, CNSS, signataire, logo et couleur (imprimés sur les attestations et bulletins).',
                    '« Sites » (si plusieurs implantations), « Départements », puis « Jours fériés » : ajoutez les fêtes religieuses de l’année.',
                    '« Gérer les employés » → « Importer depuis Excel » avec le modèle fourni, puis envoyez les invitations.',
                    'Dans chaque fiche : contrat (type, salaire, période d’essai), données personnelles (CIN, CNSS, RIB…).',
                    '« Pointage » → Horaires : choisissez la date de début du pointage.',
                    '« Protection des données » : suivez la liste CNDP (déclaration, notice, conservation).',
                ],
                link: { href: '/admin/company', label: 'Entreprise' },
            },
            {
                title: 'Chaque jour',
                steps: [
                    '« Vue d’ensemble » : absents, demandes à traiter, contrats et documents qui expirent, dossiers CNSS, pointage du jour, anniversaires.',
                    '« Gérer les demandes » : décision finale sur les congés, dossiers CNSS des arrêts maladie de 4 jours ou plus (30 jours pour déposer).',
                    '« Attestations » : délivrez ou refusez les demandes.',
                ],
                link: { href: '/admin/overview', label: 'Vue d’ensemble' },
            },
            {
                title: 'Chaque mois : la paie',
                steps: [
                    'Paie prépare le mois dans « Bulletins de paie ». Vous validez ensuite (une autre personne que celle qui a préparé).',
                    'Une fois validés, les bulletins sont visibles par les employés.',
                ],
                link: { href: '/admin/payslips', label: 'Bulletins de paie' },
            },
            {
                title: 'Chaque année',
                steps: [
                    'Dès l’annonce officielle : ajoutez les fêtes religieuses dans « Jours fériés » (sans cocher « chaque année »).',
                    '« Campagnes d’entretiens » : ouvrez la campagne, suivez l’avancement, clôturez les entretiens signés, traitez les recommandations (« Appliquer » ouvre l’évolution de carrière pré-remplie).',
                    '« Indicateurs RH » : bilan de l’année (rotation, absentéisme, masse salariale) en Excel ou PDF.',
                    'Les jours non pris sont reportés au 1er janvier et expirent le 31 mars.',
                ],
                link: { href: '/admin/reviews', label: 'Campagnes d’entretiens' },
            },
            {
                title: 'Recruter',
                steps: [
                    '« Recrutement » → « Nouvelle offre », puis « Publier » : elle apparaît sur la page carrières publique (/carrieres).',
                    'Faites avancer les candidats d’étape en étape, planifiez les entretiens.',
                    '« Embaucher » crée le compte, le dossier, le contrat, range le CV et envoie l’invitation.',
                ],
                link: { href: '/recrutement', label: 'Recrutement' },
            },
            {
                title: 'Évolution de carrière',
                steps: [
                    'Dans la fiche d’un employé : « Nouveau changement » (promotion, augmentation, mutation). Le dossier et le contrat sont mis à jour ; l’historique est gardé.',
                ],
            },
        ],
    },
    {
        id: 'paie',
        title: 'Paie',
        intro: 'Préparer la paie du mois et fournir les fichiers au comptable.',
        topics: [
            {
                title: 'Préparer le mois',
                steps: [
                    '« Bulletins de paie » → choisissez le mois → « Préparer la paie » : un bulletin par employé ayant un salaire sur son contrat.',
                    'Le crayon sur chaque bulletin : jours sans solde et absences, heures supplémentaires (reprises du pointage), primes et indemnités (imposables ou non), avances et prêts.',
                    '« Recalculer » relit les dossiers sans perdre vos saisies ; puis « Envoyer pour validation ».',
                ],
                link: { href: '/admin/payslips', label: 'Bulletins de paie' },
            },
            {
                title: 'Pour le comptable et la CNSS',
                steps: [
                    '« Journal + CNSS (Excel) » : journal de paie et liste CNSS du mois.',
                    '« Fichier CNSS » : fichier de déclaration à vérifier sur Damancom avant envoi.',
                    '« Export paie » : jours de congé, absences et heures supplémentaires par employé.',
                ],
            },
            {
                title: 'À savoir',
                steps: [
                    'Les taux CNSS, AMO et IR sont dans config/payroll.php : faites-les vérifier par votre comptable, en particulier chaque mois de janvier (loi de finances).',
                ],
            },
        ],
    },
];

/** "Aide": the user guide, the reader's own role first, printable. */
export default function Help() {
    const can = usePermissions();
    const { auth } = usePage<SharedData>().props;
    const isManager = Boolean((auth.user as { is_manager?: boolean }).is_manager);
    const visible = GUIDES.filter(
        (g) =>
            g.id === 'employe' ||
            (g.id === 'responsable' && (isManager || can.manage_hr)) ||
            (g.id === 'rh' && can.manage_hr) ||
            (g.id === 'paie' && can.view_hr),
    );
    // The reader's main role first.
    const order = can.manage_hr
        ? ['rh', 'paie', 'responsable', 'employe']
        : can.view_hr
          ? ['paie', 'employe']
          : isManager
            ? ['responsable', 'employe']
            : ['employe'];
    const guides = [...visible].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Aide')} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{t('Aide')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Guide d’utilisation, selon votre rôle.</p>
                    </div>
                    <Button variant="outline" onClick={() => window.print()} className="print:hidden">
                        <Printer className="me-2 h-4 w-4" /> {t('Imprimer')}
                    </Button>
                </div>

                <nav aria-label="Sommaire" className="flex flex-wrap gap-2 print:hidden">
                    {guides.map((g) => (
                        <a key={g.id} href={`#${g.id}`} className="hover:bg-accent rounded-md border px-3 py-1 text-sm">
                            {g.title}
                        </a>
                    ))}
                </nav>

                {guides.map((guide) => (
                    <section key={guide.id} id={guide.id} className="break-inside-avoid-page space-y-3">
                        <div>
                            <h2 className="text-xl font-semibold">{guide.title}</h2>
                            <p className="text-muted-foreground text-sm">{guide.intro}</p>
                        </div>
                        {guide.topics.map((topic) => (
                            <Card key={topic.title} className="break-inside-avoid gap-2 py-4">
                                <CardHeader className="px-4">
                                    <CardTitle className="text-base">{topic.title}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 px-4 text-sm">
                                    <ol className="list-decimal space-y-1 ps-5">
                                        {topic.steps.map((step, i) => (
                                            <li key={i}>{step}</li>
                                        ))}
                                    </ol>
                                    {topic.link && (
                                        <Link href={topic.link.href} className="inline-block text-sm underline print:hidden">
                                            {topic.link.label} →
                                        </Link>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                ))}
            </div>
        </AppLayout>
    );
}
