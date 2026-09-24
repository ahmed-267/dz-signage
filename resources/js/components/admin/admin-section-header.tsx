import { Link, router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type AdminSectionTab = {
    id: string;
    label: string;
    href: string;
    count?: number | null;
};

type AdminSectionHeaderProps = {
    title: string;
    subtitle: string;
    tabs: AdminSectionTab[];
    activeTab: string;
    testId?: string;
};

/**
 * Grouped Super Admin section chrome: title, subtitle, and URL-driven tabs.
 * Tab navigation uses Inertia visits so refresh / back / forward stay correct.
 */
export function AdminSectionHeader({
    title,
    subtitle,
    tabs,
    activeTab,
    testId = 'admin-section-tabs',
}: AdminSectionHeaderProps) {
    return (
        <div className="space-y-4" data-test={testId}>
            <div>
                <h1 className="font-display text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                <p className="text-muted-foreground mt-1 text-sm">{subtitle}</p>
            </div>
            <div
                className="border-border -mx-1 flex gap-1 overflow-x-auto px-1 pb-1"
                role="tablist"
                aria-label={`${title} sections`}
            >
                {tabs.map((tab) => {
                    const active = tab.id === activeTab;
                    return (
                        <Link
                            key={tab.id}
                            href={tab.href}
                            role="tab"
                            aria-selected={active}
                            data-test={`admin-tab-${tab.id}`}
                            className={cn(
                                'focus-visible:ring-ring inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                            onClick={(event) => {
                                // Prefer Inertia so SPA state stays warm.
                                if (
                                    !event.metaKey &&
                                    !event.ctrlKey &&
                                    !event.shiftKey
                                ) {
                                    event.preventDefault();
                                    router.visit(tab.href, {
                                        preserveScroll: true,
                                    });
                                }
                            }}
                        >
                            {tab.label}
                            {typeof tab.count === 'number' ? (
                                <span
                                    className={cn(
                                        'rounded-md px-1.5 py-0.5 font-mono text-[10px]',
                                        active
                                            ? 'bg-primary-foreground/20'
                                            : 'bg-muted',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            ) : null}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export const ADMIN_CUSTOMERS_TABS: AdminSectionTab[] = [
    { id: 'businesses', label: 'Businesses', href: '/admin/workspaces' },
    { id: 'users', label: 'Users', href: '/admin/users' },
    { id: 'tvs', label: 'TVs', href: '/admin/screens' },
];

export const ADMIN_BILLING_TABS: AdminSectionTab[] = [
    { id: 'plans', label: 'Plans', href: '/admin/subscriptions/plans' },
    {
        id: 'subscriptions',
        label: 'Subscriptions',
        href: '/admin/subscriptions',
    },
    { id: 'invoices', label: 'Invoices', href: '/admin/invoices' },
];

export const ADMIN_OPERATIONS_TABS: AdminSectionTab[] = [
    { id: 'tv-health', label: 'TV Health', href: '/admin/screen-health' },
    {
        id: 'publishing',
        label: 'Publishing Jobs',
        href: '/admin/publishing-jobs',
    },
    {
        id: 'system-health',
        label: 'System Health',
        href: '/admin/system-health',
    },
    { id: 'errors', label: 'Errors', href: '/admin/errors' },
];

/** Support is a single destination (Support Requests) — no sibling tabs. */
export const ADMIN_SUPPORT_TABS: AdminSectionTab[] = [
    { id: 'requests', label: 'Support Requests', href: '/admin/support' },
];

export const ADMIN_SETTINGS_TABS: AdminSectionTab[] = [
    { id: 'platform', label: 'Platform Settings', href: '/admin/settings' },
    {
        id: 'feature-flags',
        label: 'Feature Flags',
        href: '/admin/feature-flags',
    },
    { id: 'audit-log', label: 'Audit Log', href: '/admin/audit-log' },
];
