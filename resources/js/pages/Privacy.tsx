import { NoticeFacts, NoticeText } from '@/components/privacy/NoticeText';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, ShieldCheck } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Protection des données', href: '/protection-des-donnees' }];

/** The notice every employee accepts once (loi 09-08, art. 5), and the export of their data. */
export default function Privacy({ notice, acknowledgedAt }: { notice: NoticeFacts; acknowledgedAt: string | null }) {
    const form = useForm({});

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Protection des données')} />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <div className="flex items-start gap-3">
                    <ShieldCheck className="text-muted-foreground mt-1 h-6 w-6 shrink-0" />
                    <div>
                        <h1 className="text-2xl font-bold">{t('Protection de vos données personnelles')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {t('Information prévue par la loi n° 09-08 relative à la protection des personnes physiques.')}
                        </p>
                    </div>
                </div>

                {!acknowledgedAt && (
                    <div
                        role="status"
                        className="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                    >
                        {t("Avant d'utiliser l'application, merci de lire cette notice et de confirmer en bas de page.")}
                    </div>
                )}

                <Card>
                    <CardContent className="py-2">
                        <NoticeText notice={notice} />
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button variant="outline" asChild>
                        <a href="/protection-des-donnees/export">
                            <Download className="me-2 h-4 w-4" /> {t('Télécharger mes données (ZIP)')}
                        </a>
                    </Button>
                    {acknowledgedAt ? (
                        <p className="flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
                            <CheckCircle2 className="h-4 w-4" /> {t('Prise de connaissance le')}{' '}
                            {new Date(acknowledgedAt).toLocaleDateString(intlLocale())}
                        </p>
                    ) : (
                        <Button onClick={() => form.post('/protection-des-donnees/accept')} disabled={form.processing}>
                            {t("J'ai pris connaissance de cette notice")}
                        </Button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
