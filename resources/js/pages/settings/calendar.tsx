import { toast } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Copy, Link2, Lock } from 'lucide-react';

interface CalendarSettingsProps {
    /** Null until the person creates their links. */
    links: { me: string; team: string | null } | null;
    /** Manager or delegate: gets the team calendar. */
    approves: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendrier', href: '/settings/calendar' }];

function FeedLink({ id, label, description, url }: { id: string; label: string; description: string; url: string }) {
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            toast.success('Lien copié.');
        } catch {
            toast.error('Copie impossible : sélectionnez le lien et copiez-le.');
        }
    };

    return (
        <div className="space-y-1">
            <Label htmlFor={id}>{label}</Label>
            <p className="text-muted-foreground text-xs">{description}</p>
            <div className="flex gap-2">
                <Input id={id} readOnly value={url} onFocus={(e) => e.target.select()} className="font-mono text-xs" />
                <Button type="button" variant="outline" onClick={copy}>
                    <Copy className="mr-2 h-4 w-4" /> Copier
                </Button>
            </div>
        </div>
    );
}

/** Private links to see one's leave (and one's team's) in Outlook or Google Calendar. */
export default function CalendarSettings({ links, approves }: CalendarSettingsProps) {
    const regenerate = async () => {
        if (links) {
            const ok = await confirmAction({
                title: 'Créer de nouveaux liens ?',
                description: 'Les anciens liens cesseront de fonctionner : il faudra réabonner vos agendas avec les nouveaux.',
                confirmLabel: 'Créer de nouveaux liens',
            });
            if (!ok) return;
        }
        router.post('/settings/calendar', {}, { preserveScroll: true });
    };

    const disable = async () => {
        const ok = await confirmAction({
            title: "Désactiver l'abonnement ?",
            description: 'Les agendas abonnés ne recevront plus vos congés.',
            confirmLabel: 'Désactiver',
            destructive: true,
        });
        if (ok) router.delete('/settings/calendar', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendrier" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Abonnement calendrier"
                        description="Voyez vos congés dans Outlook, Google Agenda ou le calendrier de votre téléphone, mis à jour automatiquement."
                    />

                    {links ? (
                        <div className="space-y-5">
                            <FeedLink
                                id="feed-me"
                                label="Mes congés"
                                description="Vos congés acceptés et ceux en attente (marqués « en attente »)."
                                url={links.me}
                            />
                            {links.team && (
                                <FeedLink
                                    id="feed-team"
                                    label="Absences de mon équipe"
                                    description="Les congés acceptés de votre équipe (et d'une équipe qui vous est déléguée), avec le nom seulement : le type de congé n'est pas partagé."
                                    url={links.team}
                                />
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" onClick={regenerate}>
                                    Créer de nouveaux liens
                                </Button>
                                <Button variant="outline" className="text-red-700 dark:text-red-400" onClick={disable}>
                                    Désactiver
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <Button onClick={regenerate}>
                            <Link2 className="mr-2 h-4 w-4" /> Créer mes liens d'abonnement
                        </Button>
                    )}

                    <div className="bg-muted/50 flex items-start gap-3 rounded-md border px-4 py-3 text-sm">
                        <Lock className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            Ces liens sont personnels : toute personne qui les connaît voit les congés qu'ils contiennent. Ne les partagez pas ; en
                            cas de doute, créez-en de nouveaux.
                        </p>
                    </div>

                    <div className="space-y-2 text-sm">
                        <h3 className="font-medium">Comment s'abonner</h3>
                        <ul className="text-muted-foreground list-disc space-y-1 pl-5">
                            <li>
                                <span className="text-foreground">Google Agenda</span> : « Autres agendas » → « + » → « À partir de l'URL », collez le
                                lien.
                            </li>
                            <li>
                                <span className="text-foreground">Outlook</span> : « Ajouter un calendrier » → « S'abonner à partir du web », collez
                                le lien.
                            </li>
                            <li>
                                <span className="text-foreground">iPhone</span> : Réglages → Calendrier → Comptes → Ajouter un compte → Autre → «
                                Ajouter un calendrier avec abonnement ».
                            </li>
                        </ul>
                        <p className="text-muted-foreground text-xs">
                            Les agendas se mettent à jour d'eux-mêmes, en général toutes les quelques heures.
                        </p>
                        {!approves && (
                            <p className="text-muted-foreground text-xs">
                                Les responsables d'équipe disposent aussi d'un calendrier des absences de leur équipe.
                            </p>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
