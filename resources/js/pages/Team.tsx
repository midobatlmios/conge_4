import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { LeaveStatus, STATUS_COLORS, STATUS_LABELS, TYPE_CONGE_LABELS, TypeConge, formatDays, halfDayNote, parseISODate } from '@/lib/leave';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Mon équipe', href: '/equipe' },
];

const longDate = (iso: string) => parseISODate(iso)?.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) ?? iso;

const period = (r: TeamRequest) => `${longDate(r.start)}${halfDayNote(r.start_half, 'start')} → ${longDate(r.end)}${halfDayNote(r.end_half, 'end')}`;

const approve = (r: TeamRequest) => {
    if (window.confirm(`Valider la demande de ${r.name} (${period(r)}) ?\n\nElle sera transmise aux RH pour la décision finale.`)) {
        router.put(`/demandes/${r.id}/manager-approve`, {}, { preserveScroll: true });
    }
};

const refuse = (r: TeamRequest) => {
    // null when cancelled; an empty reason is allowed.
    const reason = window.prompt(`Refuser la demande de ${r.name} (${period(r)}) ?\n\nLe refus est définitif. Motif (facultatif) :`, '');
    if (reason !== null) {
        router.put(`/demandes/${r.id}/manager-refuse`, { manager_comment: reason }, { preserveScroll: true });
    }
};

function RequestRow({ request }: { request: TeamRequest }) {
    return (
        <li className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="font-medium">
                    {request.name}
                    {request.job_title && <span className="text-muted-foreground text-sm font-normal"> · {request.job_title}</span>}
                </div>
                <div className="text-sm">
                    {TYPE_CONGE_LABELS[request.type]} · {formatDays(request.days)} j · {period(request)}
                </div>
                {request.comment && <div className="text-muted-foreground text-xs">« {request.comment} »</div>}
                {request.needs_document && (
                    <div className={`text-xs ${request.documents > 0 ? 'text-green-700' : 'text-orange-600'}`}>
                        {request.documents > 0 ? 'Justificatif joint (consultable par les RH)' : 'Justificatif pas encore joint'}
                    </div>
                )}
                {request.manager_comment && <div className="text-xs text-red-600">Motif du refus : {request.manager_comment}</div>}
            </div>
            {request.can_decide ? (
                <div className="flex shrink-0 gap-2">
                    <Button size="sm" className="bg-green-600 hover:bg-green-700" onClick={() => approve(request)}>
                        <Check className="mr-1 h-4 w-4" /> Valider
                    </Button>
                    <Button size="sm" variant="outline" className="text-red-600 hover:text-red-700" onClick={() => refuse(request)}>
                        <X className="mr-1 h-4 w-4" /> Refuser
                    </Button>
                </div>
            ) : (
                <span className={`shrink-0 text-sm font-medium ${STATUS_COLORS[request.status]}`}>{STATUS_LABELS[request.status]}</span>
            )}
        </li>
    );
}

export default function Team({ requests, teamSize }: { requests: TeamRequest[]; teamSize: number }) {
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
                            <p className="text-muted-foreground text-sm">Aucune demande en attente de votre validation.</p>
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
            </div>
        </AppLayout>
    );
}
