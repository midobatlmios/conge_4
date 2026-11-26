import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    /** What to do next, or why the list is empty. */
    description?: ReactNode;
    /** A button or link to the next step. */
    action?: ReactNode;
    className?: string;
}

/** Shown instead of an empty list: says so plainly and points to the next step. */
export default function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center', className)}>
            <span className="bg-muted text-muted-foreground mb-1 flex h-11 w-11 items-center justify-center rounded-full" aria-hidden="true">
                <Icon className="h-5 w-5" />
            </span>
            <p className="font-medium">{title}</p>
            {description && <p className="text-muted-foreground max-w-sm text-sm">{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
