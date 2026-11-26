import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { FormEvent, useEffect, useState } from 'react';

type ConfirmOptions = {
    title: string;
    /** Details under the title; line breaks are kept. */
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Red confirm button, for what cannot be undone. */
    destructive?: boolean;
};

type PromptOptions = ConfirmOptions & {
    label: string;
    defaultValue?: string;
    placeholder?: string;
    /** The answer cannot be empty. */
    required?: boolean;
    /** Several lines (a reason) instead of one field. */
    multiline?: boolean;
    type?: 'text' | 'date' | 'number';
};

type Request =
    | { kind: 'confirm'; options: ConfirmOptions; resolve: (value: boolean) => void }
    | { kind: 'prompt'; options: PromptOptions; resolve: (value: string | null) => void };

let show: ((request: Request) => void) | null = null;

/**
 * Asks for confirmation in the app's own dialog (instead of window.confirm).
 * Resolves to true when confirmed. Usable from any file once <ConfirmDialogHost/> is mounted.
 */
export function confirmAction(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => (show ? show({ kind: 'confirm', options, resolve }) : resolve(window.confirm(options.title))));
}

/** Asks for a value (a reason, a date…) in the app's dialog. Resolves to null when cancelled. */
export function promptValue(options: PromptOptions): Promise<string | null> {
    return new Promise((resolve) =>
        show ? show({ kind: 'prompt', options, resolve }) : resolve(window.prompt(options.title, options.defaultValue ?? '')),
    );
}

/** The single dialog behind confirmAction() and promptValue(); mounted once in the app layout. */
export default function ConfirmDialogHost() {
    const [request, setRequest] = useState<Request | null>(null);
    const [value, setValue] = useState('');

    useEffect(() => {
        show = (next) => {
            setValue(next.kind === 'prompt' ? (next.options.defaultValue ?? '') : '');
            setRequest(next);
        };
        return () => {
            show = null;
        };
    }, []);

    const close = (answer: boolean) => {
        if (!request) return;
        if (request.kind === 'confirm') request.resolve(answer);
        else request.resolve(answer ? value.trim() : null);
        setRequest(null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        close(true);
    };

    const options = request?.options;
    const prompt = request?.kind === 'prompt' ? request.options : null;
    const blocked = Boolean(prompt?.required && value.trim() === '');

    return (
        <Dialog open={request !== null} onOpenChange={(open) => !open && close(false)}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{options?.title}</DialogTitle>
                        {options?.description && <DialogDescription className="whitespace-pre-line">{options.description}</DialogDescription>}
                    </DialogHeader>
                    {prompt && (
                        <div className="space-y-1">
                            <Label htmlFor="dialog-value">{prompt.label}</Label>
                            {prompt.multiline ? (
                                <Textarea
                                    id="dialog-value"
                                    autoFocus
                                    rows={3}
                                    value={value}
                                    placeholder={prompt.placeholder}
                                    onChange={(e) => setValue(e.target.value)}
                                />
                            ) : (
                                <Input
                                    id="dialog-value"
                                    autoFocus
                                    type={prompt.type ?? 'text'}
                                    step={prompt.type === 'number' ? 'any' : undefined}
                                    value={value}
                                    placeholder={prompt.placeholder}
                                    onChange={(e) => setValue(e.target.value)}
                                />
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => close(false)}>
                            {options?.cancelLabel ?? t('Annuler')}
                        </Button>
                        <Button type="submit" variant={options?.destructive ? 'destructive' : 'default'} disabled={blocked} autoFocus={!prompt}>
                            {options?.confirmLabel ?? t('Confirmer')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
