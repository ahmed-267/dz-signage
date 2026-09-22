import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    CalendarDays,
    Clapperboard,
    LayoutDashboard,
    Monitor,
} from 'lucide-react';
import {
    AvailabilityAreaChart,
    HorizontalRankChart,
    StatusDonutChart,
} from '@/components/charts';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { dashboard, publishing, schedules, screens } from '@/routes/app';

type WorkspaceSummary = {
    name: string;
    industry: string;
    role: string | null;
    country: string;
    timezone: string;
};

type Metrics = {
    screens: {
        total: number;
        online: number;
        offline: number;
        attention: number;
        healthy: number;
        health_offline: number;
    };
    designs: number;
    playlists_active: number;
    schedules_active: number;
    availability_percent: number | null;
    playback_seconds: number;
    licences: {
        used: number;
        licensed: number;
        remaining: number;
        has_subscription: boolean;
    };
};

type DeploymentRow = {
    id: number;
    screen_name: string | null;
    content_name: string | null;
    content_type: string;
    status: string;
    status_label: string;
    deployed_at: string | null;
};

type ScreenRow = {
    id: number;
    name: string;
    location_name: string | null;
    network_state: string;
    health: string;
    health_label: string;
    last_seen_at: string | null;
};

type AvailabilityPoint = {
    date: string;
    online_seconds: number;
    offline_seconds: number;
    availability_percent: number | null;
};

type ContentActivity = {
    screen_design_version_id: number;
    screen_design_name: string;
    version_number: number | null;
    plays: number;
    playback_seconds: number;
    screen_count: number;
    last_played_at: string | null;
};

type Props = {
    workspaceSummary: WorkspaceSummary | null;
    metrics: Metrics | null;
    recentDeployments: DeploymentRow[];
    recentScreens: ScreenRow[];
    availabilitySeries: AvailabilityPoint[];
    contentActivity: ContentActivity[];
    empty: boolean;
};

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'info' | 'success' | 'warning' | 'destructive' {
    switch (status) {
        case 'active':
        case 'online':
        case 'healthy':
            return 'success';
        case 'failed':
        case 'attention':
        case 'error':
            return 'destructive';
        case 'offline':
        case 'superseded':
            return 'warning';
        case 'pending':
            return 'info';
        default:
            return 'secondary';
    }
}

function MetricCard({
    label,
    value,
    hint,
    href,
    icon: Icon,
}: {
    label: string;
    value: string | number;
    hint?: string;
    href?: string;
    icon: typeof Monitor;
}) {
    const body = (
        <Card className="hover:bg-muted/30 gap-1.5 py-3 shadow-none transition-colors">
            <CardHeader className="gap-1 px-3.5">
                <div className="flex items-start justify-between gap-2">
                    <CardDescription className="text-xs font-medium tracking-wide uppercase">
                        {label}
                    </CardDescription>
                    <Icon className="text-muted-foreground size-4 shrink-0" />
                </div>
                <CardTitle className="text-xl tabular-nums">{value}</CardTitle>
                {hint ? (
                    <p className="text-muted-foreground text-xs">{hint}</p>
                ) : null}
            </CardHeader>
        </Card>
    );

    if (!href) {
        return body;
    }

    return (
        <Link href={href} className="block focus-visible:outline-none">
            {body}
        </Link>
    );
}

export default function AppDashboard({
    workspaceSummary,
    metrics,
    recentDeployments,
    recentScreens,
    availabilitySeries,
    contentActivity,
    empty,
}: Props) {
    const healthSlices = metrics
        ? [
              {
                  key: 'healthy',
                  label: 'Healthy',
                  value: metrics.screens.healthy,
                  color: 'var(--success)',
              },
              {
                  key: 'attention',
                  label: 'Attention',
                  value: metrics.screens.attention,
                  color: 'var(--warning)',
              },
              {
                  key: 'offline',
                  label: 'Offline',
                  value: metrics.screens.health_offline,
                  color: 'var(--muted-foreground)',
              },
          ]
        : [];

    const contentRank = contentActivity.slice(0, 5).map((item) => ({
        id: item.screen_design_version_id,
        label: item.screen_design_name,
        value: item.plays,
    }));

    return (
        <>
            <Head title="Dashboard" />
            <div
                className="flex h-full flex-1 flex-col gap-3 p-3 md:gap-3.5 md:p-4"
                data-test="app-dashboard"
            >
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h1 className="font-display text-xl font-semibold tracking-tight md:text-2xl">
                            Dashboard
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            {workspaceSummary
                                ? `Overview for ${workspaceSummary.name}`
                                : 'Your RMSignage workspace overview'}
                        </p>
                    </div>
                    {workspaceSummary ? (
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {workspaceSummary.industry}
                            </Badge>
                            {workspaceSummary.role ? (
                                <Badge variant="info">
                                    {workspaceSummary.role}
                                </Badge>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {empty || !metrics ? (
                    <EmptyState
                        icon={LayoutDashboard}
                        title="Nothing to show yet"
                        description={`Add a ${ProductLabels.screenDesignSingular} or pair a TV to populate this dashboard.`}
                    />
                ) : (
                    <>
                        <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard
                                label={ProductLabels.online}
                                value={metrics.screens.online}
                                hint={`${metrics.screens.total} total · ${metrics.screens.offline} offline`}
                                href={screens.url()}
                                icon={Monitor}
                            />
                            <MetricCard
                                label={ProductLabels.availability}
                                value={
                                    metrics.availability_percent != null
                                        ? `${metrics.availability_percent}%`
                                        : '—'
                                }
                                hint="Avg over last 30 days · Player connectivity"
                                icon={Activity}
                            />
                            <MetricCard
                                label="Playback time"
                                value={formatDuration(metrics.playback_seconds)}
                                hint="Last 30 days from Player telemetry"
                                icon={Clapperboard}
                            />
                            <MetricCard
                                label="Active schedules"
                                value={metrics.schedules_active}
                                hint={`${metrics.playlists_active} published playlist${metrics.playlists_active === 1 ? '' : 's'}`}
                                href={schedules.url()}
                                icon={CalendarDays}
                            />
                        </div>

                        <div className="grid gap-2.5 lg:grid-cols-[minmax(0,1.95fr)_minmax(0,1fr)]">
                            <Card className="shadow-none">
                                <CardHeader className="px-3.5 pt-3 pb-1">
                                    <CardTitle className="text-base">
                                        {ProductLabels.availability} trend
                                    </CardTitle>
                                    <CardDescription>
                                        Last 30 days · Player heartbeats
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="px-2 pb-3 sm:px-3.5">
                                    <AvailabilityAreaChart
                                        data={availabilitySeries}
                                        height={240}
                                    />
                                </CardContent>
                            </Card>

                            <Card className="shadow-none">
                                <CardHeader className="px-3.5 pt-3 pb-1">
                                    <CardTitle className="text-base">
                                        {ProductLabels.health}
                                    </CardTitle>
                                    <CardDescription>
                                        Pairing, network, and content state
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="px-3.5 pb-3">
                                    <StatusDonutChart
                                        data={healthSlices}
                                        height={200}
                                        emptyTitle={`No ${ProductLabels.displayPlural} yet`}
                                        emptyDescription="Pair a TV to see health breakdown."
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid gap-2.5 lg:grid-cols-3">
                            <Card className="shadow-none">
                                <CardHeader className="px-3.5 pt-3 pb-1">
                                    <CardTitle className="text-base">
                                        Content activity
                                    </CardTitle>
                                    <CardDescription>
                                        Top 5 by starts · last 30 days
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="px-2 pb-3 sm:px-3.5">
                                    <HorizontalRankChart
                                        data={contentRank}
                                        valueLabel="Plays"
                                        height={200}
                                        emptyTitle="No content plays yet"
                                        emptyDescription="Content rankings appear when Players report playback."
                                    />
                                </CardContent>
                            </Card>

                            <Card className="shadow-none">
                                <CardHeader className="flex flex-row items-center justify-between gap-2 px-3.5 pt-3 pb-1">
                                    <div>
                                        <CardTitle className="text-base">
                                            Recent publishing
                                        </CardTitle>
                                        <CardDescription>
                                            Latest deployments
                                        </CardDescription>
                                    </div>
                                    <Link
                                        href={publishing.url()}
                                        className="text-primary shrink-0 text-sm font-medium hover:underline"
                                    >
                                        View all
                                    </Link>
                                </CardHeader>
                                <CardContent className="px-0 pb-2">
                                    {recentDeployments.length === 0 ? (
                                        <p className="text-muted-foreground px-3.5 pb-3 text-sm">
                                            No deployments yet.
                                        </p>
                                    ) : (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        Content
                                                    </TableHead>
                                                    <TableHead>TV</TableHead>
                                                    <TableHead>
                                                        Status
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {recentDeployments
                                                    .slice(0, 5)
                                                    .map((row) => (
                                                        <TableRow key={row.id}>
                                                            <TableCell>
                                                                <div className="max-w-[9rem] truncate font-medium">
                                                                    {row.content_name ??
                                                                        '—'}
                                                                </div>
                                                                <div className="text-muted-foreground text-xs">
                                                                    {formatWhen(
                                                                        row.deployed_at,
                                                                    )}
                                                                </div>
                                                            </TableCell>
                                                            <TableCell className="max-w-[6rem] truncate">
                                                                {row.screen_name ??
                                                                    '—'}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant={statusVariant(
                                                                        row.status,
                                                                    )}
                                                                >
                                                                    {
                                                                        row.status_label
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                            </TableBody>
                                        </Table>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="shadow-none">
                                <CardHeader className="flex flex-row items-center justify-between gap-2 px-3.5 pt-3 pb-1">
                                    <div>
                                        <CardTitle className="text-base">
                                            {ProductLabels.displayPlural}{' '}
                                            needing attention
                                        </CardTitle>
                                        <CardDescription>
                                            Health from Player heartbeats
                                        </CardDescription>
                                    </div>
                                    <Link
                                        href={screens.url()}
                                        className="text-primary shrink-0 text-sm font-medium hover:underline"
                                    >
                                        View all
                                    </Link>
                                </CardHeader>
                                <CardContent className="px-0 pb-2">
                                    {recentScreens.length === 0 ? (
                                        <p className="text-muted-foreground px-3.5 pb-3 text-sm">
                                            No TVs yet.
                                        </p>
                                    ) : (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>TV</TableHead>
                                                    <TableHead>
                                                        Health
                                                    </TableHead>
                                                    <TableHead>
                                                        Last seen
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {recentScreens
                                                    .slice(0, 5)
                                                    .map((row) => (
                                                        <TableRow key={row.id}>
                                                            <TableCell>
                                                                <div className="max-w-[9rem] truncate font-medium">
                                                                    {row.name}
                                                                </div>
                                                                <div className="text-muted-foreground truncate text-xs">
                                                                    {row.location_name ??
                                                                        'No location'}
                                                                </div>
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant={statusVariant(
                                                                        row.health ===
                                                                            'healthy'
                                                                            ? row.network_state
                                                                            : row.health,
                                                                    )}
                                                                    className={cn(
                                                                        row.health ===
                                                                            'healthy' &&
                                                                            row.network_state ===
                                                                                'online' &&
                                                                            'capitalize',
                                                                    )}
                                                                >
                                                                    {
                                                                        row.health_label
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-muted-foreground text-sm">
                                                                {formatWhen(
                                                                    row.last_seen_at,
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                            </TableBody>
                                        </Table>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

AppDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
