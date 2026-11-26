import { toastErrors } from '@/components/app-toaster';
import AttestationForm from '@/components/attestations/AttestationForm';
import AttestationList from '@/components/attestations/AttestationList';
import { Attestation, TypeOption } from '@/components/attestations/attestations';
import { confirmAction } from '@/components/confirm-dialog';
import DelegationPanel, { Delegation } from '@/components/DelegationPanel';
import EditUtilisateurModal from '@/components/EditUtilisateurModal';
import { CareerChange } from '@/components/employees/career';
import CareerChangeForm from '@/components/employees/CareerChangeForm';
import CareerHistory from '@/components/employees/CareerHistory';
import ContractForm from '@/components/employees/ContractForm';
import ContractList from '@/components/employees/ContractList';
import { alertText, Contract, ContractAlert, ContractOptions } from '@/components/employees/contracts';
import DocumentFolder, { CategoryOption, FolderDocument } from '@/components/employees/DocumentFolder';
import { EmployeeProfile, formatDate } from '@/components/employees/personal-data';
import PersonalDataForm from '@/components/employees/PersonalDataForm';
import PersonalDataView from '@/components/employees/PersonalDataView';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { User } from '@/components/users/columns';
import { Option } from '@/components/users/EmployeeRecordFields';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { canManageAccount, ROLE_LABELS, RoleOption, usePermissions } from '@/lib/roles';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Download, FileBadge, FileSignature, FolderOpen, Lock, Pencil, Plus, TrendingUp, UserCheck } from 'lucide-react';
import { useState } from 'react';

interface EmployeeProps {
    employee: User;
    profile: EmployeeProfile | null;
    /** Labels of the essential fields (CIN, CNSS, RIB…) still empty. */
    missing: string[];
    departments: Option[];
    managers: Option[];
    /** Roles the signed-in user may give. */
    roles: RoleOption[];
    /** The document folder (contract, CIN copy, diplomas…). */
    documents: FolderDocument[];
    documentCategories: CategoryOption[];
    documentMaxKb: number;
    /** Contract history, newest first, and what HR must act on soon. */
    contracts: Contract[];
    contractAlerts: ContractAlert[];
    contractOptions: ContractOptions;
    /** Promotions, raises and transfers, newest first. */
    careerChanges: CareerChange[];
    careerKinds: { value: string; label: string }[];
    /** A review recommendation to apply (from Campagnes d'entretiens → Appliquer). */
    recommendation: { review_id: number; kind: string; job_title: string | null; gross_salary: number | null; notes: string } | null;
    attestations: Attestation[];
    attestationTypes: TypeOption[];
    /** Sick leave this year: working days off, calendar days (CNSS), number of stops. */
    sickDays: { year: number; working: number; calendar: number; count: number };
    /** Manages a team: HR can delegate their approvals. */
    isManager: boolean;
    delegations: Delegation[];
    nextLeave: { start: string; end: string } | null;
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-1 py-2 text-sm sm:grid-cols-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="sm:col-span-2">{children || <span className="text-muted-foreground">—</span>}</dd>
        </div>
    );
}

/** One employee's file for HR: record (edited in the usual modal) and personal data. */
export default function Employee({
    employee,
    profile,
    missing,
    departments,
    managers,
    roles,
    documents,
    documentCategories,
    documentMaxKb,
    contracts,
    contractAlerts,
    contractOptions,
    careerChanges,
    careerKinds,
    recommendation,
    attestations,
    attestationTypes,
    sickDays,
    isManager,
    delegations,
    nextLeave,
}: EmployeeProps) {
    const [editOpen, setEditOpen] = useState(false);
    const can = usePermissions();
    const { auth } = usePage<SharedData>().props;
    // Paie only reads; nobody edits their own personal data (another HR person does).
    const isSelf = auth.user.id === employee.id;
    const canEditData = can.manage_hr && !isSelf;
    // null: no form open; 'new': adding; a contract: editing it.
    const [contractForm, setContractForm] = useState<Contract | 'new' | null>(null);
    // Opened straight away when coming to apply a review recommendation.
    const [careerFormOpen, setCareerFormOpen] = useState(recommendation !== null);
    // As on the server: the latest contract that has started, else the next one.
    const today = new Date().toLocaleDateString('sv-SE');
    const currentContract = contracts.find((c) => c.start_date <= today) ?? contracts.at(-1) ?? null;

    const deleteCareerChange = async (change: CareerChange) => {
        const ok = await confirmAction({
            title: t('Supprimer ce changement ?'),
            description: t(
                ":p0 du :p1. À utiliser pour une erreur de saisie : le poste, le département, la catégorie et le salaire d'avant sont rétablis.",
                { p0: change.kind_label, p1: formatDate(change.effective_date) },
            ),
            confirmLabel: t('Supprimer'),
            destructive: true,
        });
        if (ok) router.delete(`/admin/career-changes/${change.id}`, { preserveScroll: true, onError: toastErrors });
    };

    const deleteContract = async (contract: Contract) => {
        const ok = await confirmAction({
            title: t('Supprimer ce contrat ?'),
            description: t(":p0 du :p1. À utiliser pour une erreur de saisie : l'historique des contrats sert aux attestations.", {
                p0: contract.type_label,
                p1: formatDate(contract.start_date),
            }),
            confirmLabel: t('Supprimer'),
            destructive: true,
        });
        if (ok) router.delete(`/admin/contracts/${contract.id}`, { preserveScroll: true, onError: toastErrors });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Tableau de bord'), href: '/dashboard' },
        { title: t('Gestion des employés'), href: '/admin/users' },
        { title: employee.name, href: `/admin/users/${employee.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Fiche de :p0', { p0: employee.name })} />
            <div className="container mx-auto max-w-4xl space-y-6 px-4 py-10">
                <Link href="/admin/users" className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm">
                    <ArrowLeft className="h-4 w-4" /> {t('Tous les employés')}
                </Link>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{employee.name}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {[employee.job_title, employee.department?.name, employee.matricule && `Matricule ${employee.matricule}`]
                                .filter(Boolean)
                                .join(' · ') || t('Dossier à compléter')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can.manage_hr && !isSelf && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/admin/users/${employee.id}/data-export`}
                                    title={t("Droit d'accès (loi 09-08) : toutes ses données et ses documents")}
                                >
                                    <Download className="me-2 h-4 w-4" /> {t('Exporter ses données')}
                                </a>
                            </Button>
                        )}
                        {employee.deactivated_at ? (
                            <Badge variant="secondary">
                                {t('Désactivé le')} {formatDate(employee.deactivated_at)}
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="border-green-600 text-green-700 dark:text-green-400">
                                {t('Actif')}
                            </Badge>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>{t('Dossier employé')}</CardTitle>
                            <CardDescription>{t('Poste, rattachement et compte de connexion.')}</CardDescription>
                        </div>
                        {canManageAccount(can, employee.role) && (
                            <Button variant="outline" size="sm" onClick={() => setEditOpen(true)}>
                                <Pencil className="me-2 h-4 w-4" /> {t('Modifier')}
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y">
                            <Row label={t('E-mail professionnel')}>{employee.email}</Row>
                            <Row label={t('Matricule')}>{employee.matricule}</Row>
                            <Row label={t('Poste')}>{employee.job_title}</Row>
                            <Row label={t('Département')}>{employee.department?.name}</Row>
                            {employee.site && <Row label={t('Site')}>{employee.site.name}</Row>}
                            <Row label={t('Responsable')}>{employee.manager?.name}</Row>
                            <Row label={t("Date d'embauche")}>{formatDate(employee.hire_date)}</Row>
                            <Row label={t('Rôle')}>{ROLE_LABELS[employee.role]}</Row>
                            <Row label={t('Congé maladie :p0', { p0: sickDays.year })}>
                                {sickDays.count > 0
                                    ? t(':p0 arrêt:p1 · :p2 jour:p3 ouvré:p4 (:p5 calendaires)', {
                                          p0: sickDays.count,
                                          p1: sickDays.count > 1 ? 's' : '',
                                          p2: String(sickDays.working).replace('.', ','),
                                          p3: sickDays.working > 1 ? 's' : '',
                                          p4: sickDays.working > 1 ? 's' : '',
                                          p5: sickDays.calendar,
                                      })
                                    : t('Aucun arrêt')}
                            </Row>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <FileSignature className="h-4 w-4" /> {t('Contrats')}
                            </CardTitle>
                            <CardDescription>
                                {t('Historique des contrats (CDD, renouvellement, CDI…), du plus récent au plus ancien.')}
                            </CardDescription>
                        </div>
                        {canEditData && contractForm === null && (
                            <Button variant="outline" size="sm" onClick={() => setContractForm('new')}>
                                <Plus className="me-2 h-4 w-4" /> {t('Nouveau contrat')}
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {contractAlerts.map((alert) => (
                            <div
                                key={alert.kind}
                                role="status"
                                className="flex items-start gap-3 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300"
                            >
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <p>{alertText(alert)}.</p>
                            </div>
                        ))}
                        {contractForm !== null && (
                            <ContractForm
                                key={contractForm === 'new' ? 'new' : contractForm.id}
                                employeeId={employee.id}
                                contract={contractForm === 'new' ? null : contractForm}
                                options={contractOptions}
                                onDone={() => setContractForm(null)}
                            />
                        )}
                        <ContractList
                            contracts={contracts}
                            onEdit={canEditData ? setContractForm : undefined}
                            onDelete={canEditData ? deleteContract : undefined}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" /> {t('Évolution de carrière')}
                            </CardTitle>
                            <CardDescription>{t("Promotions, augmentations et mutations, de la plus récente à l'embauche.")}</CardDescription>
                        </div>
                        {canEditData && !careerFormOpen && (
                            <Button variant="outline" size="sm" onClick={() => setCareerFormOpen(true)}>
                                <Plus className="me-2 h-4 w-4" /> {t('Nouveau changement')}
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {careerFormOpen && (
                            <CareerChangeForm
                                employeeId={employee.id}
                                current={{
                                    jobTitle: employee.job_title ?? null,
                                    departmentId: employee.department_id ?? null,
                                    contract: currentContract,
                                }}
                                kinds={careerKinds}
                                categories={contractOptions.categories}
                                departments={departments}
                                onDone={() => setCareerFormOpen(false)}
                                initial={recommendation}
                            />
                        )}
                        <CareerHistory
                            changes={careerChanges}
                            hireDate={employee.hire_date}
                            onDelete={canEditData ? deleteCareerChange : undefined}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lock className="h-4 w-4" /> {t('Données personnelles et administratives')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                "Visibles uniquement par les comptes Administrateur, RH et Paie, et par l'employé lui-même (en lecture seule). Utilisées pour la paie, la CNSS et les attestations.",
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {missing.length > 0 && (
                            <div
                                role="status"
                                className="flex items-start gap-3 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300"
                            >
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <p>
                                    {t('À compléter pour la paie et les attestations :')} {missing.join(', ')}.
                                </p>
                            </div>
                        )}
                        {canEditData ? (
                            // Re-mounted after each save so the form starts from what was stored.
                            <PersonalDataForm key={profile?.updated_at ?? 'new'} employeeId={employee.id} profile={profile} />
                        ) : (
                            <>
                                <p className="text-muted-foreground text-sm">
                                    {isSelf
                                        ? t('Vos propres données sont modifiées par une autre personne du service RH.')
                                        : t('Lecture seule : seuls les comptes RH et Administrateur modifient ces données.')}
                                </p>
                                {profile && <PersonalDataView profile={profile} />}
                            </>
                        )}
                    </CardContent>
                </Card>

                {isManager && can.manage_hr && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserCheck className="h-4 w-4" /> {t('Délégation de validation')}
                            </CardTitle>
                            <CardDescription>
                                {employee.name}{' '}
                                {t("valide les demandes de son équipe. En cas d'absence, confiez cette validation à quelqu'un d'autre.")}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DelegationPanel
                                delegations={delegations}
                                options={managers.filter((m) => m.id !== employee.id)}
                                url={`/admin/users/${employee.id}/delegations`}
                                nextLeave={nextLeave}
                                whose={employee.name}
                            />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileBadge className="h-4 w-4" /> {t('Attestations')}
                        </CardTitle>
                        <CardDescription>{t('Attestations de travail et de salaire délivrées ou demandées.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {canEditData && (
                            <AttestationForm
                                url={`/admin/users/${employee.id}/attestations`}
                                types={attestationTypes}
                                submitLabel={t("Établir l'attestation")}
                                withNetSalary
                            />
                        )}
                        <AttestationList attestations={attestations} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FolderOpen className="h-4 w-4" /> {t('Dossier documents')}
                        </CardTitle>
                        <CardDescription>
                            {t("Contrat, copie CIN, diplômes… Fichiers privés : comptes Administrateur, RH et Paie, et l'employé (lecture seule).")}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {can.manage_hr && isSelf && (
                            <p className="text-muted-foreground text-sm">
                                {t('Votre propre dossier est tenu par une autre personne du service RH.')}
                            </p>
                        )}
                        <DocumentFolder
                            documents={documents}
                            upload={canEditData ? { employeeId: employee.id, categories: documentCategories, maxKb: documentMaxKb } : undefined}
                        />
                    </CardContent>
                </Card>
            </div>

            {editOpen && (
                <EditUtilisateurModal
                    isOpen={editOpen}
                    onClose={() => setEditOpen(false)}
                    user={employee}
                    departments={departments}
                    managers={managers}
                    roles={roles}
                />
            )}
        </AppLayout>
    );
}
