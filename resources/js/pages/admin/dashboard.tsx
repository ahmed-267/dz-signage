import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Building2,
    FileText,
    HardDrive,
    LayoutTemplate,
    Monitor,
    Users,
} from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    audit_log,
    dashboard,
    publishing_jobs,
    screens,
    templates,
    users,
    workspaces,
} from '@/routes/admin';

type MetricKey =
    | 'workspaces'
    | 'users'
    | 'templates'
    | 'published_templates'
    | 'screens'
    | 'screens_online'
    | 'screens_offline'
    | 'screens_attention'
    | 'deployments_active'
    | 'deployments_failed_7d'
    | 'open_support'
    | 'unresolved_errors';

type AttentionScreen = {
    id?: number;
    name?: string | null;
    workspace_name?: string | null;
    health_label?: string | null;
    health?: string | null;
    last_seen_at?: string | null;
};

type PublishingRow = {
    id?: number;
    workspace_name?: string | null;
    screen_name?: string | null;
    content_name?: string | null;
    status_label?: string | null;
    status?: string | null;
    deployed_at?: string | null;
};

type WorkspaceRow = {
    id?: number;
    name?: string | null;
    industry?: string | null;
    owner?: string | null;
    created_at?: string | null;
};

type AuditRow = {
    id?: number;
    action?: string | null;
    actor_name?: string | null;
    subject?: string | null;
    entity_type?: string | null;
    created_at?: string | null;
};

type Props = {
    metrics?: Partial<Record<MetricKey, number>> &
        Record<string, number | undefined>;
    platform_role?: string | null;
    platform_role_label?: string | null;
    screens_needing_attention?: AttentionScreen[];
    recent_publishing?: PublishingRow[];
    recent_workspaces?: WorkspaceRow[];
    recent_audit?: AuditRow[];
};

const METRIC_CARDS: {
    key: MetricKey;
    label: string;
    href: string;
    icon: typeof Building2;
    test: string;
}[] = [
    {
        key: 'workspaces',
        label: 'Businesses',
        href: workspaces.url(),
        icon: Building2,
        test: 'admin-metric-workspaces',
    },
    {
        key: 'users',
        label: 'Users',
        href: users.url(),
        icon: Users,
        test: 'admin-metric-users',
    },
    {
        key: 'screens',
        label: 'TVs',
        href: screens.url(),
        icon: Monitor,
        test: 'admin-metric-screens',
    },
    {
        key: 'screens_online',
        label: 'TVs online',
        href: screens.url({ query: { filter: 'online' } }),
        icon: Activity,
        test: 'admin-metric-screens-online',
    },
    {
        key: 'screens_offline',
        label: 'TVs offline',
        href: screens.url({ query: { filter: 'offline' } }),
        icon: Activity,
        test: 'admin-metric-screens-offline',
    },
    {
        key: 'screens_attention',
        label: 'Need attention',
        href: screens.url({ query: { filter: 'attention' } }),
        icon: Activity,
        test: 'admin-metric-screens-attention',
    },
    {
        key: 'deployments_active',
        label: 'Active deployments',
        href: publishing_jobs.url(),
        icon: HardDrive,
        test: 'admin-metric-deployments-active',
    },
    {
        key: 'deployments_failed_7d',
        label: 'Failed (7d)',
        href: publishing_jobs.url({ query: { status: 'failed' } }),
        icon: HardDrive,
        test: 'admin-metric-deployments-failed',
    },
    {
        key: 'templates',
        label: 'Templates',
        href: templates.url(),
        icon: LayoutTemplate,
        test: 'admin-metric-templates',
    },
    {
        key: 'published_templates',
        label: 'Published templates',
        href: templates.url(),
        icon: LayoutTemplate,
        test: 'admin-metric-published-templates',
    },
    {
        key: 'open_support',
        label: 'Open support',
        href: '/admin/support',
        icon: FileText,
        test: 'admin-metric-open-support',
    },
    {
        key: 'unresolved_errors',
        label: 'Unresolved errors',
        href: '/admin/errors',
        icon: HardDrive,
        test: 'admin-metric-unresolved-errors',
    },
];

function formatWhen(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

export default function AdminDashboard({
    metrics = {},
    platform_role_label,
    screens_needing_attention = [],
    recent_publishing = [],
    recent_workspaces = [],
    recent_audit = [],
}: Props) {
    const stats = METRIC_CARDS.filter(
        (card) => typeof metrics[card.key] === 'number',
    );

    return (
        <>
            <Head title="Platform Overview" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-overview"
            >
                <AdminPageHeader
                    badge={platform_role_label ?? 'Platform'}
                    title="Platform overview"
                    description="Live platform counts and recent activity. Billing revenue is never invented here."
                />

                {stats.length > 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat) => {
                            const Icon = stat.icon;
                            return (
                                <Link key={stat.key} href={stat.href} prefetch>
                                    <Card
                                        className="hover:bg-accent/40 gap-2 py-4 shadow-none transition-colors"
                                        data-test={stat.test}
                                    >
                                        <CardHeader className="px-4">
                                            <CardDescription className="flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                                <Icon className="size-3.5" />
                                                {stat.label}
                                            </CardDescription>
                                            <CardTitle className="text-2xl">
                                                {metrics[stat.key]}
                                            </CardTitle>
                                        </CardHeader>
                                    </Card>
                                </Link>
                            );
                        })}
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card
                        className="shadow-none"
                        data-test="admin-screens-attention"
                    >
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                TVs needing attention
                            </CardTitle>
                            <CardDescription>
                                Health alerts from real Player heartbeats.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {screens_needing_attention.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No TVs need attention right now.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Screen</TableHead>
                                            <TableHead>Workspace</TableHead>
                                            <TableHead>Health</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {screens_needing_attention.map(
                                            (row, index) => (
                                                <TableRow key={row.id ?? index}>
                                                    <TableCell className="font-medium">
                                                        {row.id != null ? (
                                                            <Link
                                                                href={`/admin/screens/${row.id}`}
                                                                className="hover:underline"
                                                            >
                                                                {row.name ??
                                                                    '—'}
                                                            </Link>
                                                        ) : (
                                                            (row.name ?? '—')
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.workspace_name ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <StatusBadge
                                                            label={
                                                                row.health_label ??
                                                                row.health ??
                                                                'Attention'
                                                            }
                                                            tone={
                                                                row.health ??
                                                                'attention'
                                                            }
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        className="shadow-none"
                        data-test="admin-recent-publishing"
                    >
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Recent publishing
                            </CardTitle>
                            <CardDescription>
                                Latest deployments across workspaces.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recent_publishing.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No recent publishing activity.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Content</TableHead>
                                            <TableHead>Screen</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recent_publishing.map((row, index) => (
                                            <TableRow key={row.id ?? index}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {row.content_name ??
                                                            '—'}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {row.workspace_name ??
                                                            '—'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {row.screen_name ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        label={
                                                            row.status_label ??
                                                            row.status ??
                                                            '—'
                                                        }
                                                        tone={row.status}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            <div className="mt-3">
                                <Link
                                    href={publishing_jobs()}
                                    className="text-sm font-medium hover:underline"
                                >
                                    View all publishing jobs
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className="shadow-none"
                        data-test="admin-recent-workspaces"
                    >
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Recent workspaces
                            </CardTitle>
                            <CardDescription>
                                Newly created customer workspaces.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recent_workspaces.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No recent workspaces.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Industry</TableHead>
                                            <TableHead>Created</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recent_workspaces.map((row, index) => (
                                            <TableRow key={row.id ?? index}>
                                                <TableCell className="font-medium">
                                                    {row.id != null ? (
                                                        <Link
                                                            href={`/admin/workspaces/${row.id}`}
                                                            className="hover:underline"
                                                        >
                                                            {row.name ?? '—'}
                                                        </Link>
                                                    ) : (
                                                        (row.name ?? '—')
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {row.industry ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {formatWhen(row.created_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        className="shadow-none"
                        data-test="admin-recent-audit"
                    >
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Recent audit
                            </CardTitle>
                            <CardDescription>
                                Latest platform audit events.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recent_audit.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No recent audit events.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Action</TableHead>
                                            <TableHead>Actor</TableHead>
                                            <TableHead>When</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recent_audit.map((row, index) => (
                                            <TableRow key={row.id ?? index}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {row.action ?? '—'}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {row.subject ??
                                                            row.entity_type ??
                                                            ''}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {row.actor_name ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {formatWhen(row.created_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            <div className="mt-3">
                                <Link
                                    href={audit_log()}
                                    className="text-sm font-medium hover:underline"
                                >
                                    Open audit log
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Overview',
            href: dashboard(),
        },
    ],
};
