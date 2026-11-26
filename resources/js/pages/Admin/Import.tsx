import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Download, FileSpreadsheet, Mail, Upload } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';

type CheckedRow = {
    line: number;
    name: string;
    email: string;
    action: 'create' | 'update';
    department: string | null;
    manager_name: string | null;
    errors: string[];
    warnings: string[];
};

type Check = {
    rows: CheckedRow[];
    errors: number;
    creates: number;
    updates: number;
    new_departments: string[];
    file_error: string | null;
};

interface ImportProps {
    token: string | null;
    check: Check | null;
    columns: { heading: string; required: boolean; hint: string }[];
    imported: { created: { id: number; name: string; email: string }[]; updated: number; departments: number } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des employés', href: '/admin/users' },
    { title: 'Import Excel', href: '/admin/import' },
];

/** Import employees from Excel: template, check with errors per row, import (all or nothing), invitations. */
export default function Import({ token, check, columns, imported }: ImportProps) {
    const upload = useForm<{ file: File | null }>({ file: null });
    const fileInput = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);
    const [inviting, setInviting] = useState(false);
    const errors = usePage().props.errors as Record<string, string>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        upload.post('/admin/import', { forceFormData: true, onFinish: () => fileInput.current && (fileInput.current.value = '') });
    };

    const confirm = () => {
        setImporting(true);
        router.post(`/admin/import/${token}/confirm`, {}, { onFinish: () => setImporting(false) });
    };

    const invite = (ids: number[]) => {
        setInviting(true);
        router.post('/admin/users/invite', { ids }, { preserveScroll: true, onFinish: () => setInviting(false) });
    };

    const ready = check && !check.file_error && check.errors === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import Excel" />
            <div className="container mx-auto max-w-5xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Importer des employés depuis Excel</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Une ligne par employé. Un employé déjà présent (même e-mail ou même matricule) est mis à jour ; les cellules vides ne changent
                        rien. Rien n'est enregistré tant que le fichier contient des erreurs.
                    </p>
                </div>
                {(errors.file || errors.invite) && (
                    <div
                        role="alert"
                        className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        {errors.file || errors.invite}
                    </div>
                )}

                {imported && imported.created.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5 text-green-700 dark:text-green-400" /> Import terminé
                            </CardTitle>
                            <CardDescription>
                                {imported.created.length} compte(s) créé(s), {imported.updated} mis à jour
                                {imported.departments > 0 ? `, ${imported.departments} département(s) créé(s)` : ''}. Les nouveaux employés n'ont pas
                                encore de mot de passe : envoyez-leur une invitation (lien valable 7 jours).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ul className="text-muted-foreground max-h-48 overflow-y-auto text-sm">
                                {imported.created.map((user) => (
                                    <li key={user.id}>
                                        {user.name} · {user.email}
                                    </li>
                                ))}
                            </ul>
                            <Button onClick={() => invite(imported.created.map((user) => user.id))} disabled={inviting}>
                                <Mail className="me-2 h-4 w-4" /> Envoyer les invitations
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>1. Télécharger le modèle</CardTitle>
                            <CardDescription>
                                Remplissez la feuille « Employés » ; un exemple et le mode d'emploi sont dans les autres feuilles.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" asChild>
                                <a href="/admin/import/modele">
                                    <FileSpreadsheet className="me-2 h-4 w-4" /> Modèle Excel
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>2. Envoyer le fichier</CardTitle>
                            <CardDescription>Le fichier est vérifié ligne par ligne avant tout enregistrement.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    ref={fileInput}
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    aria-label="Fichier Excel"
                                    onChange={(e) => upload.setData('file', e.target.files?.[0] ?? null)}
                                    required
                                />
                                <Button type="submit" disabled={upload.processing}>
                                    <Upload className="me-2 h-4 w-4" /> Vérifier
                                </Button>
                            </form>
                            <InputError message={upload.errors.file} className="mt-2" />
                        </CardContent>
                    </Card>
                </div>

                {check?.file_error && (
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>{check.file_error}</p>
                    </div>
                )}

                {check && !check.file_error && (
                    <Card>
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>3. Vérification</CardTitle>
                                <CardDescription>
                                    {check.rows.length} ligne(s) : {check.creates} création(s), {check.updates} mise(s) à jour
                                    {check.new_departments.length > 0 ? `, nouveaux départements : ${check.new_departments.join(', ')}` : ''}.
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {check.errors > 0 && (
                                    <Button variant="outline" asChild>
                                        <a href={`/admin/import/${token}/erreurs`}>
                                            <Download className="me-2 h-4 w-4" /> Rapport d'erreurs
                                        </a>
                                    </Button>
                                )}
                                <Button onClick={confirm} disabled={!ready || importing}>
                                    {importing ? 'Import en cours…' : `Importer ${check.rows.length} employé(s)`}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {check.errors > 0 ? (
                                <p className="text-sm text-red-700 dark:text-red-400">
                                    {check.errors} ligne(s) à corriger dans le fichier, puis envoyez-le à nouveau. Rien n'a été enregistré.
                                </p>
                            ) : (
                                <p className="text-sm text-green-700 dark:text-green-400">Aucune erreur : vous pouvez importer.</p>
                            )}
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Ligne</TableHead>
                                            <TableHead>Employé</TableHead>
                                            <TableHead>Action</TableHead>
                                            <TableHead>Département</TableHead>
                                            <TableHead>Responsable</TableHead>
                                            <TableHead>Erreurs / remarques</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {check.rows.map((row) => (
                                            <TableRow key={row.line} className={row.errors.length > 0 ? 'bg-red-50/60 dark:bg-red-950/30' : ''}>
                                                <TableCell className="tabular-nums">{row.line}</TableCell>
                                                <TableCell>
                                                    <div className="font-medium">{row.name || '—'}</div>
                                                    <div className="text-muted-foreground text-xs">{row.email}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{row.action === 'create' ? 'Création' : 'Mise à jour'}</Badge>
                                                </TableCell>
                                                <TableCell>{row.department ?? '—'}</TableCell>
                                                <TableCell>{row.manager_name ?? '—'}</TableCell>
                                                <TableCell className="text-xs">
                                                    {row.errors.map((error) => (
                                                        <p key={error} className="text-red-700 dark:text-red-400">
                                                            {error}
                                                        </p>
                                                    ))}
                                                    {row.warnings.map((warning) => (
                                                        <p key={warning} className="text-orange-700 dark:text-orange-400">
                                                            {warning}
                                                        </p>
                                                    ))}
                                                    {row.errors.length === 0 && row.warnings.length === 0 && (
                                                        <span className="text-green-700 dark:text-green-400">OK</span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Colonnes du modèle</CardTitle>
                        <CardDescription>
                            Les colonnes peuvent être dans n'importe quel ordre ; seules les trois premières sont obligatoires. Les données sont
                            ensuite visibles sur la fiche de chaque employé (
                            <Link href="/admin/users" className="underline">
                                Gestion des employés
                            </Link>
                            ).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            {columns.map((column) => (
                                <div key={column.heading} className="flex gap-2">
                                    <dt className="font-medium">
                                        {column.heading}
                                        {column.required ? ' *' : ''}
                                    </dt>
                                    {column.hint && <dd className="text-muted-foreground">— {column.hint}</dd>}
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
