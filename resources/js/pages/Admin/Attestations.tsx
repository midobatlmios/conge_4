import { toastErrors } from '@/components/app-toaster';
import { Attestation, pdfUrl, STATUS_CLASS } from '@/components/attestations/attestations';
import { confirmAction, promptValue } from '@/components/confirm-dialog';
import { formatDate } from '@/components/employees/personal-data';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { usePermissions } from '@/lib/roles';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, FileBadge } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Attestations', href: '/admin/attestations' },
];

/** Every attestation, requests waiting first. Admin and RH issue or refuse; Paie reads. */
export default function Attestations({ attestations }: { attestations: Attestation[] }) {
    const can = usePermissions();
    const { auth, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [busy, setBusy] = useState<number | null>(null);
    const waiting = attestations.filter((a) => a.status === 'demandee').length;

    const issue = async (attestation: Attestation) => {
        let net: string | null = null;
        if (attestation.type === 'salaire') {
            net = await promptValue({
                title: t("Délivrer l'attestation de salaire de :p0 ?", { p0: attestation.name }),
                description: t("Le salaire brut vient du contrat en cours. Laissez vide pour n'indiquer que le brut."),
                label: t('Salaire net mensuel (facultatif, DH)'),
                type: 'number',
                confirmLabel: t('Délivrer'),
            });
            if (net === null) return;
        } else if (
            !(await confirmAction({
                title: t("Délivrer l':p0 de :p1 ?", { p0: attestation.type_label.toLowerCase(), p1: attestation.name }),
                confirmLabel: t('Délivrer'),
            }))
        ) {
            return;
        }
        setBusy(attestation.id);
        router.put(`/admin/attestations/${attestation.id}/issue`, net ? { net_salary: net.replace(',', '.').replace(/\s/g, '') } : {}, {
            preserveScroll: true,
            onError: toastErrors,
            onFinish: () => setBusy(null),
        });
    };

    const refuse = async (attestation: Attestation) => {
        const reason = await promptValue({
            title: t('Refuser la demande de :p0 ?', { p0: attestation.name }),
            description: t(":p0. L'employé est prévenu avec ce motif.", { p0: attestation.type_label }),
            label: t('Motif du refus'),
            multiline: true,
            required: true,
            confirmLabel: t('Refuser'),
            destructive: true,
        });
        if (!reason) return;
        setBusy(attestation.id);
        router.put(
            `/admin/attestations/${attestation.id}/refuse`,
            { reason },
            { preserveScroll: true, onError: toastErrors, onFinish: () => setBusy(null) },
        );
    };

    const errorText = errors.attestation || errors.net_salary || errors.reason;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Attestations')} />
            <div className="container mx-auto max-w-5xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">{t('Attestations')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {waiting > 0 ? t(':p0 demande:p1 en attente.', { p0: waiting, p1: waiting > 1 ? 's' : '' }) : t('Aucune demande en attente.')}{' '}
                        {t("Pour établir une attestation sans demande, ouvrez la fiche de l'employé.")}
                    </p>
                </div>

                {errorText && (
                    <div
                        role="alert"
                        className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        {errorText}
                    </div>
                )}

                <Card>
                    <CardContent>
                        {attestations.length === 0 ? (
                            <EmptyState
                                icon={FileBadge}
                                title={t('Aucune attestation')}
                                description={t(
                                    "Les demandes des employés apparaîtront ici. Pour en établir une directement, ouvrez la fiche de l'employé.",
                                )}
                                className="border-0"
                            />
                        ) : (
                            <ul className="divide-y">
                                {attestations.map((attestation) => {
                                    const canHandle = can.manage_hr && attestation.status === 'demandee' && attestation.user_id !== auth.user.id;
                                    return (
                                        <li
                                            key={attestation.id}
                                            className="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Link href={`/admin/users/${attestation.user_id}`} className="font-medium hover:underline">
                                                        {attestation.name}
                                                    </Link>
                                                    <span>· {t(attestation.type_label)}</span>
                                                    <Badge variant="outline" className={STATUS_CLASS[attestation.status]}>
                                                        {t(attestation.status_label)}
                                                    </Badge>
                                                    {attestation.number && (
                                                        <span className="text-muted-foreground text-xs">n° {attestation.number}</span>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground text-xs">
                                                    {attestation.requested_by_employee ? t('Demandée') : t('Établie par le RH')} {t('le')}{' '}
                                                    {formatDate(attestation.requested_at)}
                                                    {attestation.purpose ? ` · ${attestation.purpose}` : ''}
                                                    {attestation.handled_by ? t(' · traitée par :p0', { p0: attestation.handled_by }) : ''}
                                                </p>
                                                {attestation.refusal_reason && (
                                                    <p className="text-xs text-red-700 dark:text-red-400">
                                                        {t('Refus :')} {attestation.refusal_reason}
                                                    </p>
                                                )}
                                                {attestation.missing && attestation.missing.length > 0 && (
                                                    <p className="text-xs text-orange-700 dark:text-orange-400">
                                                        {t('À compléter avant de délivrer :')} {attestation.missing.join(', ')}.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex gap-2 self-start sm:self-center">
                                                {attestation.status === 'delivree' && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <a href={pdfUrl(attestation)} target="_blank" rel="noopener">
                                                            <Download className="me-2 h-4 w-4" /> {t('PDF')}
                                                        </a>
                                                    </Button>
                                                )}
                                                {canHandle && (
                                                    <>
                                                        <Button size="sm" disabled={busy === attestation.id} onClick={() => issue(attestation)}>
                                                            {t('Délivrer')}
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="text-red-700 dark:text-red-400"
                                                            disabled={busy === attestation.id}
                                                            onClick={() => refuse(attestation)}
                                                        >
                                                            {t('Refuser')}
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
