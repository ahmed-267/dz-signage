import type { ReactNode } from 'react';
import {
    AdminSectionHeader,
    type AdminSectionTab,
} from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';

type AdminPageHeaderProps = {
    title: string;
    description?: string;
    badge?: string | null;
    actions?: ReactNode;
    /** When set, renders URL-driven section tabs under the title. */
    tabs?: AdminSectionTab[];
    activeTab?: string;
};

export function AdminPageHeader({
    title,
    description,
    badge = 'Platform',
    actions,
    tabs,
    activeTab,
}: AdminPageHeaderProps) {
    if (tabs && activeTab) {
        return (
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <AdminSectionHeader
                        title={title}
                        subtitle={description ?? ''}
                        tabs={tabs}
                        activeTab={activeTab}
                    />
                </div>
                {actions ? (
                    <div className="flex flex-wrap gap-2">{actions}</div>
                ) : null}
            </div>
        );
    }

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
