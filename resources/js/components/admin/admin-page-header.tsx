import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';

type AdminPageHeaderProps = {
    title: string;
    description?: string;
    badge?: string;
    actions?: ReactNode;
};

export function AdminPageHeader({
    title,
    description,
    badge = 'Platform',
    actions,
}: AdminPageHeaderProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
                {badge ? (
                    <div className="mb-1">
                        <Badge variant="info">{badge}</Badge>
                    </div>
                ) : null}
                <h1 className="font-display text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description ? (
                    <p className="text-muted-foreground mt-1 text-sm">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap gap-2">{actions}</div>
            ) : null}
        </div>
    );
}
