import AttestationForm from '@/components/attestations/AttestationForm';
import AttestationList from '@/components/attestations/AttestationList';
import { Attestation, TypeOption } from '@/components/attestations/attestations';
import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes attestations', href: '/settings/attestations' }];

/** The employee asks for an attestation and downloads the ones HR issued. */
export default function MyAttestations({ attestations, types }: { attestations: Attestation[]; types: TypeOption[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Mes attestations')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Mes attestations')}
                        description={t("Demandez une attestation de travail ou de salaire : le service RH l'établit et vous la téléchargez ici.")}
                    />
                    <AttestationForm url="/attestations" types={types} submitLabel={t('Demander')} />
                    <AttestationList attestations={attestations} />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
