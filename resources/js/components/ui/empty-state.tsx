import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

/**
 * Shared empty-state pattern for lists, tables, and feature placeholders.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            data-slot="empty-state"
            className={cn(
                'border-border bg-card text-card-foreground flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center',
                className,
            )}
        >
            {Icon ? (
                <div className="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-lg">
                    <Icon className="size-5" aria-hidden />
                </div>
            ) : null}
            <div className="space-y-1">
                <h2 className="text-base font-semibold tracking-tight">
                    {title}
                </h2>
                {description ? (
                    <p className="text-muted-foreground max-w-sm text-sm text-pretty">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? <div className="mt-1">{action}</div> : null}
        </div>
    );
}
