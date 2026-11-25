import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
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
    const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const form = useForm({ name: '' });

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const startEdit = (department: Department) => {
        setEditing(department);
        setFeedback(null);
        form.clearErrors();
        form.setData('name', department.name);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setFeedback(null);
        const name = form.data.name;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setFeedback({ type: 'success', text: editing ? `« ${name} » a été modifié.` : `« ${name} » a été ajouté.` });
                resetForm();
            },
        };

        if (editing) {
            form.put(`/admin/departments/${editing.id}`, options);
        } else {
            form.post('/admin/departments', options);
        }
    };

    const remove = (department: Department) => {
        if (!window.confirm(`Supprimer le département « ${department.name} » ?`)) {
            return;
        }
        setFeedback(null);
        router.delete(`/admin/departments/${department.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setFeedback({ type: 'success', text: `« ${department.name} » a été supprimé.` });
                if (editing?.id === department.id) resetForm();
            },
            onError: (errors) => setFeedback({ type: 'error', text: Object.values(errors).join(' ') }),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Départements" />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Départements</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Les départements proposés dans le dossier de chaque employé (page « Gestion des employés »).
                    </p>
                </div>

                {feedback && (
                    <div
                        role="status"
                        className={`rounded-md border px-4 py-3 text-sm ${
                            feedback.type === 'success'
                                ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                        }`}
                    >
                        {feedback.text}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{editing ? `Renommer « ${editing.name} »` : 'Ajouter un département'}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name" className="sr-only">
                                    Nom
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Ex. : Comptabilité"
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="flex gap-2">
                                {editing && (
                                    <Button type="button" variant="outline" onClick={resetForm}>
                                        Annuler
                                    </Button>
                                )}
                                <Button type="submit" disabled={form.processing} className="bg-green-600 hover:bg-green-700">
                                    {editing ? (
                                        'Enregistrer'
                                    ) : (
                                        <>
                                            <Plus className="mr-1 h-4 w-4" /> Ajouter
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
                            Liste <Badge variant="secondary">{departments.length}</Badge>
                        </CardTitle>
                        <CardDescription>Un département ne peut être supprimé que s'il n'a plus d'employés.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {departments.length ? (
                            <ul className="divide-y">
                                {departments.map((department) => (
                                    <li key={department.id} className="flex items-center gap-3 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{department.name}</div>
                                            <div className="text-muted-foreground text-sm">
                                                {department.users_count} employé{department.users_count > 1 ? 's' : ''}
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
                                            className="text-red-600 hover:text-red-700"
                                            onClick={() => remove(department)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">Aucun département pour l'instant.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
};

export default Departments;
