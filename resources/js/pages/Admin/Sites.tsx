import { toastErrors } from '@/components/app-toaster';
import { confirmAction } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { MapPinned, Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Site {
    id: number;
    name: string;
    city: string | null;
    users_count: number;
    holidays_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Sites', href: '/admin/sites' },
];

/** Company sites: each employee belongs to one; a holiday can be local to one site. */
export default function Sites({ sites }: { sites: Site[] }) {
    const [editing, setEditing] = useState<Site | null>(null);
    const form = useForm({ name: '', city: '' });

    const reset = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing) form.put(`/admin/sites/${editing.id}`, options);
        else form.post('/admin/sites', options);
    };
    const remove = async (site: Site) => {
        const ok = await confirmAction({
            title: t('Supprimer le site « :name » ?', { name: site.name }),
            description: t('Ses jours fériés locaux sont supprimés avec lui. Un site ne peut être supprimé que s’il n’a plus d’employés.'),
            confirmLabel: t('Supprimer'),
            destructive: true,
        });
        if (ok) router.delete(`/admin/sites/${site.id}`, { preserveScroll: true, onError: toastErrors });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Sites')} />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">{t('Sites')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {t(
                            'Siège, entrepôt, agence… Chaque employé est rattaché à un site ; un jour férié peut être propre à un site (fête locale).',
                        )}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{editing ? t('Modifier le site') : t('Nouveau site')}</CardTitle>
                        <CardDescription>{t('Sans site, un employé suit seulement les jours fériés nationaux.')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            <div className="space-y-1">
                                <Label htmlFor="site-name">{t('Nom')}</Label>
                                <Input
                                    id="site-name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder={t('Siège de Casablanca')}
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="site-city">{t('Ville')}</Label>
                                <Input id="site-city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                            </div>
                            <div className="flex gap-2">
                                {editing && (
                                    <Button type="button" variant="outline" onClick={reset}>
                                        {t('Annuler')}
                                    </Button>
                                )}
                                <Button type="submit" disabled={form.processing}>
                                    {editing ? t('Enregistrer') : t('Ajouter')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        {sites.length === 0 ? (
                            <EmptyState
                                icon={MapPinned}
                                title={t('Aucun site')}
                                description={t('Une seule implantation ? Inutile de créer des sites.')}
                            />
                        ) : (
                            <ul className="divide-y">
                                {sites.map((site) => (
                                    <li key={site.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                        <span>
                                            <span className="font-medium">{site.name}</span>
                                            {site.city && <span className="text-muted-foreground"> · {site.city}</span>}
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <Badge variant="secondary">{t(':count employé(s)', { count: site.users_count })}</Badge>
                                            {site.holidays_count > 0 && (
                                                <Badge variant="outline">
                                                    {t(':count jour(s) férié(s) local(aux)', { count: site.holidays_count })}
                                                </Badge>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t('Modifier :name', { name: site.name })}
                                                onClick={() => {
                                                    setEditing(site);
                                                    form.setData({ name: site.name, city: site.city ?? '' });
                                                }}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t('Supprimer :name', { name: site.name })}
                                                onClick={() => remove(site)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
