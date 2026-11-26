import { CareerChange } from '@/components/employees/career';
import CareerHistory from '@/components/employees/CareerHistory';
import ContractList from '@/components/employees/ContractList';
import { Contract } from '@/components/employees/contracts';
import { EmployeeProfile } from '@/components/employees/personal-data';
import PersonalDataView from '@/components/employees/PersonalDataView';
import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Données personnelles', href: '/settings/personal-data' }];

/** The employee's own personal data. Only HR can change it. */
export default function PersonalData({
    profile,
    contracts,
    careerChanges,
}: {
    profile: EmployeeProfile | null;
    contracts: Contract[];
    careerChanges: CareerChange[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Données personnelles')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Données personnelles')}
                        description={t('Les informations que le service RH utilise pour la paie, la CNSS et vos attestations.')}
                    />

                    <div className="bg-muted/50 flex items-start gap-3 rounded-md border px-4 py-3 text-sm">
                        <Lock className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            {t(
                                'Seuls vous et le service RH voyez ces informations. Une erreur (RIB, adresse…) ? Signalez-la au service RH, qui la corrigera.',
                            )}
                        </p>
                    </div>

                    {profile ? (
                        <PersonalDataView profile={profile} />
                    ) : (
                        <p className="text-muted-foreground text-sm">{t("Le service RH n'a pas encore renseigné vos données personnelles.")}</p>
                    )}

                    <section className="space-y-2">
                        <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{t('Mes contrats')}</h3>
                        <ContractList contracts={contracts} />
                    </section>

                    {careerChanges.length > 0 && (
                        <section className="space-y-2">
                            <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{t('Mon évolution')}</h3>
                            <CareerHistory changes={careerChanges} />
                        </section>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
