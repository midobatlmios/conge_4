import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { TYPE_CONGE_LABELS, useLeaveRules } from '@/lib/leave';
import { Demande } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
import { FormEvent, useRef } from 'react';

interface DocumentsModalProps {
    /** The request whose documents are shown; null closes the dialog. Pass it from the page props so it stays current. */
    demande: Demande | null;
    /** Add or remove documents (HR, or the employee while the request waits for a decision). */
    canManage: boolean;
    onClose: () => void;
}

const formatSize = (bytes: number) =>
    bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} Ko` : `${(bytes / 1024 / 1024).toFixed(1)} Mo`;

/** "Justificatifs (n)" button, flagged when a needed certificate is still missing. */
export function DocumentsButton({ demande, onClick }: { demande: Demande; onClick: () => void }) {
    const rules = useLeaveRules();
    const count = demande.documents?.length ?? 0;
    const missing = count === 0 && rules.documentTypes.includes(demande.type_conge) && ['en attente', 'validée manager'].includes(demande.etat);

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={onClick}
            className={missing ? 'border-orange-500 text-orange-700 hover:text-orange-800' : ''}
            title={missing ? 'Justificatif manquant : nécessaire avant acceptation' : undefined}
        >
            <FileText className="mr-1 h-4 w-4" />
            Justificatifs{count > 0 ? ` (${count})` : missing ? ' manquant' : ''}
        </Button>
    );
}

/** Supporting documents of a leave request: open, add, remove. */
export default function DocumentsModal({ demande, canManage, onClose }: DocumentsModalProps) {
    const rules = useLeaveRules();
    const form = useForm<{ document: File | null }>({ document: null });
    const fileInput = useRef<HTMLInputElement>(null);
    const documents = demande?.documents ?? [];
    const needed = demande ? rules.documentTypes.includes(demande.type_conge) : false;

    const upload = (event: FormEvent) => {
        event.preventDefault();
        if (!demande || !form.data.document) return;
        form.post(`/demandes/${demande.id}/documents`, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const remove = (id: number, name: string) => {
        if (window.confirm(`Supprimer le justificatif « ${name} » ?`)) {
            router.delete(`/documents/${id}`, { preserveScroll: true, preserveState: true });
        }
    };

    return (
        <Dialog
            open={demande !== null}
            onOpenChange={(open) => {
                if (!open) {
                    form.reset();
                    form.clearErrors();
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Justificatifs</DialogTitle>
                    <DialogDescription>
                        {demande && TYPE_CONGE_LABELS[demande.type_conge]}
                        {needed
                            ? ' : un justificatif (acte de mariage, de naissance ou de décès) est nécessaire avant que les RH puissent accepter la demande.'
                            : ' : un justificatif est facultatif pour ce type de congé.'}
                    </DialogDescription>
                </DialogHeader>

                {documents.length === 0 ? (
                    <p className={`text-sm ${needed ? 'text-orange-600' : 'text-muted-foreground'}`}>Aucun justificatif joint.</p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {documents.map((doc) => (
                            <li key={doc.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                <FileText className="text-muted-foreground h-4 w-4 shrink-0" />
                                <a href={`/documents/${doc.id}`} target="_blank" rel="noopener" className="min-w-0 flex-1 truncate underline">
                                    {doc.original_name}
                                </a>
                                <span className="text-muted-foreground shrink-0 text-xs">{formatSize(doc.size)}</span>
                                {canManage && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Supprimer ${doc.original_name}`}
                                        className="text-red-600 hover:text-red-700"
                                        onClick={() => remove(doc.id, doc.original_name)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canManage && (
                    <form onSubmit={upload} className="space-y-2">
                        <div className="flex gap-2">
                            <Input
                                ref={fileInput}
                                type="file"
                                accept={rules.documentMimes.map((ext) => `.${ext}`).join(',')}
                                onChange={(e) => form.setData('document', e.target.files?.[0] ?? null)}
                            />
                            <Button type="submit" disabled={!form.data.document || form.processing}>
                                <Upload className="mr-1 h-4 w-4" /> Ajouter
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            PDF, JPG ou PNG, {rules.documentMaxKb / 1024} Mo maximum. Visible uniquement par vous et les RH.
                        </p>
                        <InputError message={form.errors.document} />
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
