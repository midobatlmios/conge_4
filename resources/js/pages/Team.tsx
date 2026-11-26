import { toastErrors } from '@/components/app-toaster';
import { confirmAction, promptValue } from '@/components/confirm-dialog';
import DelegationPanel, { Delegation } from '@/components/DelegationPanel';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { LeaveStatus, STATUS_COLORS, STATUS_LABELS, TYPE_CONGE_LABELS, TypeConge, formatDays, halfDayNote, parseISODate } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, CheckCheck, UserCheck, X } from 'lucide-react';

interface TeamRequest {
    id: number;
    name: string;
    job_title: string | null;
    type: TypeConge;
    start: string;
    end: string;
    start_half: boolean;
    end_half: boolean;
    days: number;
    status: LeaveStatus;
    comment: string | null;
    manager_comment: string | null;
    can_decide: boolean;
    documents: number; // how many certificates are attached (managers cannot open them)
    needs_document: boolean;
    /** Name of the manager whose team was delegated to this user, if so. */
    delegated_by: string | null;
}

interface TeamProps {
    requests: TeamRequest[];
    teamSize: number;
    delegations: Delegation[];
    delegateOptions: { id: number; name: string }[];
    nextLeave: { start: string; end: string } | null;
    /** Managers who delegated their approvals to this user today. */
    delegatedTeams: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Mon équipe', href: '/equipe' },
];

const longDate = (iso: string) => parseISODate(iso)?.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) ?? iso;

const period = (r: TeamRequest) => `${longDate(r.start)}${halfDayNote(r.start_half, 'start')} → ${longDate(r.end)}${halfDayNote(r.end_half, 'end')}`;

const approve = async (r: TeamRequest) => {
    const ok = await confirmAction({
        title: `Valider la demande de ${r.name} ?`,
        description: `${period(r)}\nElle sera transmise aux RH pour la décision finale.`,
        confirmLabel: 'Valider',
    });
    if (ok) router.put(`/demandes/${r.id}/manager-approve`, {}, { preserveScroll: true, onError: toastErrors });
};

const refuse = async (r: TeamRequest) => {
    // null when cancelled; an empty reason is allowed.
    const reason = await promptValue({
        title: `Refuser la demande de ${r.name} ?`,
        description: `${period(r)}\nLe refus est définitif.`,
        label: 'Motif (facultatif)',
        multiline: true,
        confirmLabel: 'Refuser',
        destructive: true,
    });
    if (reason !== null) router.put(`/demandes/${r.id}/manager-refuse`, { manager_comment: reason }, { preserveScroll: true, onError: toastErrors });
};

function RequestRow({ request }: { request: TeamRequest }) {
    return (
        <li className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="font-medium">
                    {request.name}
                    {request.job_title && <span className="text-muted-foreground text-sm font-normal"> · {request.job_title}</span>}
                    {request.delegated_by && (
                        <Badge variant="outline" className="ml-2 align-middle text-[11px]">
                            Par délégation de {request.delegated_by}
                        </Badge>
                    )}
                </div>
                <div className="text-sm">
                    {TYPE_CONGE_LABELS[request.type]} · {formatDays(request.days)} j · {period(request)}
                </div>
                {request.comment && <div className="text-muted-foreground text-xs">« {request.comment} »</div>}
                {request.needs_document && (
                    <div
                        className={`text-xs ${request.documents > 0 ? 'text-green-700 dark:text-green-400' : 'text-orange-700 dark:text-orange-400'}`}
                    >
                        {request.documents > 0 ? 'Justificatif joint (consultable par les RH)' : 'Justificatif pas encore joint'}
                    </div>
                )}
                {request.manager_comment && <div className="text-xs text-red-700 dark:text-red-400">Motif du refus : {request.manager_comment}</div>}
            </div>
            {request.can_decide ? (
                <div className="flex shrink-0 gap-2">
                    <Button size="sm" className="bg-green-700 text-white hover:bg-green-800" onClick={() => approve(request)}>
                        <Check className="mr-1 h-4 w-4" /> Valider
                    </Button>
                    <Button size="sm" variant="outline" className="text-red-700 hover:text-red-700 dark:text-red-400" onClick={() => refuse(request)}>
                        <X className="mr-1 h-4 w-4" /> Refuser
                    </Button>
                </div>
            ) : (
                <span className={`shrink-0 text-sm font-medium ${STATUS_COLORS[request.status]}`}>{STATUS_LABELS[request.status]}</span>
            )}
        </li>
    );
}

export default function Team({ requests, teamSize, delegations, delegateOptions, nextLeave, delegatedTeams }: TeamProps) {
    const toDecide = requests.filter((r) => r.can_decide);
    const others = requests.filter((r) => !r.can_decide);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mon équipe" />
            <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Mon équipe</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {teamSize > 0
                            ? `Vous êtes le responsable de ${teamSize} employé${teamSize > 1 ? 's' : ''}. Vous validez leurs demandes en premier ; les RH prennent ensuite la décision finale.`
                            : "Aucun employé ne vous est rattaché pour l'instant."}
                    </p>
                    {delegatedTeams.length > 0 && (
                        <p className="mt-2 text-sm">
                            Par délégation, vous validez aussi aujourd'hui les demandes de l'équipe de{' '}
                            <span className="font-medium">{delegatedTeams.join(', ')}</span>.
                        </p>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            À valider <Badge variant={toDecide.length ? 'default' : 'secondary'}>{toDecide.length}</Badge>
                        </CardTitle>
                        <CardDescription>
                            Un refus est définitif et l'employé est prévenu ; une validation transmet la demande aux RH.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {toDecide.length ? (
                            <ul className="divide-y">
                                {toDecide.map((r) => (
                                    <RequestRow key={r.id} request={r} />
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={CheckCheck}
                                title="Rien à valider"
                                description="Les nouvelles demandes de votre équipe apparaîtront ici ; vous serez aussi prévenu par notification."
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Autres demandes de l'équipe</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {others.length ? (
                            <ul className="divide-y">
                                {others.map((r) => (
                                    <RequestRow key={r.id} request={r} />
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">Aucune autre demande.</p>
                        )}
                    </CardContent>
                </Card>

                {teamSize > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserCheck className="h-4 w-4" /> Délégation pendant une absence
                            </CardTitle>
                            <CardDescription>Confiez la validation de vos demandes à un collègue pendant vos congés.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DelegationPanel delegations={delegations} options={delegateOptions} url="/equipe/delegations" nextLeave={nextLeave} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
