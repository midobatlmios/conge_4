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
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Department {
    id: number;
    name: string;
    users_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Départements', href: '/admin/departments' },
];

const Departments = ({ departments }: { departments: Department[] }) => {
    const [editing, setEditing] = useState<Department | null>(null);
    const form = useForm({ name: '' });

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const startEdit = (department: Department) => {
        setEditing(department);
        form.clearErrors();
        form.setData('name', department.name);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
            },
        };

        if (editing) {
            form.put(`/admin/departments/${editing.id}`, options);
        } else {
            form.post('/admin/departments', options);
        }
    };

    const remove = async (department: Department) => {
        const ok = await confirmAction({
            title: t('Supprimer le département « :p0 » ?', { p0: department.name }),
            confirmLabel: t('Supprimer'),
            destructive: true,
        });
        if (!ok) return;
        router.delete(`/admin/departments/${department.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (editing?.id === department.id) resetForm();
            },
            onError: toastErrors,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Départements')} />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">{t('Départements')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {t('Les départements proposés dans le dossier de chaque employé (page « Gestion des employés »).')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{editing ? `Renommer « ${editing.name} »` : t('Ajouter un département')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name" className="sr-only">
                                    {t('Nom')}
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder={t('Ex. : Comptabilité')}
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="flex gap-2">
                                {editing && (
                                    <Button type="button" variant="outline" onClick={resetForm}>
                                        {t('Annuler')}
                                    </Button>
                                )}
                                <Button type="submit" disabled={form.processing} className="bg-green-700 text-white hover:bg-green-800">
                                    {editing ? (
                                        t('Enregistrer')
                                    ) : (
                                        <>
                                            <Plus className="me-1 h-4 w-4" /> {t('Ajouter')}
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {t('Liste')} <Badge variant="secondary">{departments.length}</Badge>
                        </CardTitle>
                        <CardDescription>{t("Un département ne peut être supprimé que s'il n'a plus d'employés.")}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {departments.length ? (
                            <ul className="divide-y">
                                {departments.map((department) => (
                                    <li key={department.id} className="flex items-center gap-3 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{department.name}</div>
                                            <div className="text-muted-foreground text-sm">
                                                {department.users_count} {t('employé')}
                                                {department.users_count > 1 ? 's' : ''}
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Renommer ${department.name}`}
                                            onClick={() => startEdit(department)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Supprimer ${department.name}`}
                                            className="text-red-700 hover:text-red-700 dark:text-red-400"
                                            onClick={() => remove(department)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={Building2}
                                title={t("Aucun département pour l'instant")}
                                description={t('Ajoutez-en un ci-dessus ; ils servent au dossier des employés, au calendrier et aux indicateurs.')}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
};

export default Departments;
