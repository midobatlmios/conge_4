import { formatDate } from '@/components/employees/personal-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/react';
import { FileText, Image, Trash2, Upload } from 'lucide-react';
import { FormEvent, useRef } from 'react';

/** A document of an employee's folder, as EmployeeDocumentController::folder() sends it. */
export type FolderDocument = {
    id: number;
    category: string;
    category_label: string;
    title: string | null;
    expires_on: string | null;
    /** Expired, or expiring within the alert window (30 days). */
    expiry: 'expired' | 'soon' | null;
    original_name: string;
    mime_type: string;
    size: number;
    uploaded_by: string | null;
    created_at: string;
};

export type CategoryOption = { value: string; label: string };

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
}

export function ExpiryBadge({ expiry, expiresOn }: { expiry: FolderDocument['expiry']; expiresOn: string | null }) {
    if (!expiresOn) return null;
    if (expiry === 'expired') {
        return <Badge variant="destructive">Expiré le {formatDate(expiresOn)}</Badge>;
    }
    if (expiry === 'soon') {
        return (
            <Badge variant="outline" className="border-orange-500 text-orange-700 dark:text-orange-400">
                Expire le {formatDate(expiresOn)}
            </Badge>
        );
    }
    return <span className="text-muted-foreground text-xs">Valable jusqu'au {formatDate(expiresOn)}</span>;
}

interface DocumentFolderProps {
    documents: FolderDocument[];
    /** HR adding/removing documents (not in their own folder). Without it the list is read-only. */
    upload?: { employeeId: number; categories: CategoryOption[]; maxKb: number };
}

/** An employee's document folder: list, and for HR an upload form and delete buttons. */
export default function DocumentFolder({ documents, upload }: DocumentFolderProps) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ category: string; title: string; expires_on: string; file: File | null }>({
        category: upload?.categories[0]?.value ?? '',
        title: '',
        expires_on: '',
        file: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!upload) return;
        form.post(`/admin/users/${upload.employeeId}/documents`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const remove = (document: FolderDocument) => {
        if (!window.confirm(`Supprimer « ${document.title || document.category_label} » du dossier ? Le fichier sera effacé.`)) return;
        router.delete(`/admin/employee-documents/${document.id}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {documents.length === 0 ? (
                <p className="text-muted-foreground text-sm">Aucun document dans le dossier.</p>
            ) : (
                <ul className="divide-y rounded-md border">
                    {documents.map((document) => {
                        const Icon = document.mime_type.startsWith('image/') ? Image : FileText;
                        return (
                            <li key={document.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex min-w-0 items-start gap-3">
                                    <Icon className="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                                    <div className="min-w-0 space-y-1">
                                        <a
                                            href={`/employee-documents/${document.id}`}
                                            target="_blank"
                                            rel="noopener"
                                            className="block truncate font-medium hover:underline"
                                        >
                                            {document.title || document.category_label}
                                        </a>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {document.title ? `${document.category_label} · ` : ''}
                                            {document.original_name} · {formatSize(document.size)} · ajouté le {formatDate(document.created_at)}
                                            {document.uploaded_by ? ` par ${document.uploaded_by}` : ''}
                                        </p>
                                        <ExpiryBadge expiry={document.expiry} expiresOn={document.expires_on} />
                                    </div>
                                </div>
                                {upload && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="self-start text-red-600 hover:bg-red-50 hover:text-red-700 sm:self-center"
                                        onClick={() => remove(document)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">Supprimer</span>
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {upload && (
                <form onSubmit={submit} className="space-y-4 rounded-md border border-dashed p-4">
                    <h3 className="text-sm font-semibold">Ajouter un document</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="doc-category">Type de document</Label>
                            <select
                                id="doc-category"
                                className={selectClass}
                                value={form.data.category}
                                onChange={(e) => form.setData('category', e.target.value)}
                            >
                                {upload.categories.map((category) => (
                                    <option key={category.value} value={category.value}>
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.category && <p className="text-sm text-red-500">{form.errors.category}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="doc-title">Intitulé (facultatif)</Label>
                            <Input
                                id="doc-title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="Ex. : Master en finance"
                            />
                            {form.errors.title && <p className="text-sm text-red-500">{form.errors.title}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="doc-expires">Date d'expiration (facultatif)</Label>
                            <Input
                                id="doc-expires"
                                type="date"
                                value={form.data.expires_on}
                                onChange={(e) => form.setData('expires_on', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">CIN, carte de séjour, aptitude médicale… : alerte 30 jours avant.</p>
                            {form.errors.expires_on && <p className="text-sm text-red-500">{form.errors.expires_on}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="doc-file">Fichier</Label>
                            <Input
                                id="doc-file"
                                ref={fileInput}
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                required
                            />
                            <p className="text-muted-foreground text-xs">PDF, JPG ou PNG, {upload.maxKb / 1024} Mo maximum.</p>
                            {form.errors.file && <p className="text-sm text-red-500">{form.errors.file}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Upload className="mr-2 h-4 w-4" />
                            {form.processing ? 'Envoi…' : 'Ajouter au dossier'}
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}
