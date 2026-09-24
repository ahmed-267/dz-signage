import { Head, Link, router } from '@inertiajs/react';
import {
    healthBadgeVariant,
    ScreenStateBadges,
} from '@/components/screens/screen-state-badges';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_OPERATIONS_TABS } from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';
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
import { formatLastSeen } from '@/lib/format-relative-time';
import { cn } from '@/lib/utils';
import { screen_health as screenHealthIndex } from '@/routes/admin';
import { show as showScreen } from '@/routes/admin/screens';
import type {
    AdminScreenHealthCounts,
    AdminScreenHealthProps,
} from '@/types/screen';

const SUMMARY: {
    key: keyof AdminScreenHealthCounts;
    label: string;
    description: string;
}[] = [
    { key: 'total', label: 'TVs', description: 'Across all businesses' },
    {
        key: 'online',
        label: 'Online',
        description: 'Heartbeat inside the health window',
    },
    { key: 'offline', label: 'Offline', description: 'No recent heartbeat' },
    { key: 'inactive', label: 'Inactive', description: 'Turned off by owner' },
    {
        key: 'attention',
        label: 'Needs attention',
        description: 'Health alerts',
    },
    {
        key: 'connected',
        label: 'Connected',
        description: 'Device still paired',
    },
];

const FILTERS: { id: string; label: string }[] = [
    { id: 'all', label: 'All' },
    { id: 'online', label: 'Online' },
    { id: 'offline', label: 'Offline' },
    { id: 'offline_ready', label: 'Offline ready' },
    { id: 'offline_not_ready', label: 'Offline no cache' },
    { id: 'inactive', label: 'Inactive' },
    { id: 'attention', label: 'Needs attention' },
];

function labelize(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatHeartbeatAge(seconds?: number | null): string {
    if (seconds == null) {
        return 'No heartbeat';
    }

    if (seconds < 60) {
        return `${seconds}s ago`;
    }

    const minutes = Math.floor(seconds / 60);

    return `${minutes}m ago`;
}

/**
 * Platform-wide presence overview. Every value comes from real Player
 * heartbeats — no synthetic CPU/uptime/IP metrics.
 */
export default function AdminScreenHealth({
    counts,
    screens,
    filters,
    health_window_seconds: healthWindowSeconds = 90,
    heartbeat_interval_seconds: heartbeatIntervalSeconds = 45,
}: AdminScreenHealthProps) {
    function setFilter(filter: string) {
        router.get(
            screenHealthIndex.url(),
            { filter },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="TV Health" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-screen-health"
            >
                <AdminPageHeader
                    title="Operations"
                    description={`Health window: last ${healthWindowSeconds} seconds. A TV is Online when its Player has reported within this window (heartbeat every ${heartbeatIntervalSeconds}s). This is current presence, not the Analytics date range.`}
                    badge={null}
                    tabs={ADMIN_OPERATIONS_TABS}
                    activeTab="tv-health"
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {SUMMARY.map((card) => (
                        <Card key={card.key} className="shadow-none">
                            <CardHeader className="pb-2">
                                <CardDescription>{card.label}</CardDescription>
                                <CardTitle
                                    className="font-display text-2xl"
                                    data-test={`admin-health-count-${card.key}`}
                                >
                                    {counts[card.key]}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-muted-foreground text-xs">
                                {card.description}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex gap-1.5 overflow-x-auto pb-1">
                    {FILTERS.map((chip) => {
                        const active = filters.filter === chip.id;
                        return (
                            <button
                                key={chip.id}
                                type="button"
                                data-test={`admin-health-filter-${chip.id}`}
                                onClick={() => setFilter(chip.id)}
                                aria-pressed={active}
                                className={cn(
                                    'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:text-foreground hover:border-primary/40',
                                )}
                            >
                                {chip.label}
                            </button>
                        );
                    })}
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            TVs
                        </CardTitle>
                        <CardDescription>
                            {screens.length} screen
                            {screens.length === 1 ? '' : 's'} in this view.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {screens.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="admin-health-empty"
                            >
                                No TVs match this filter.
                            </p>
                        ) : null}

                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>TV</TableHead>
                                        <TableHead>Business</TableHead>
                                        <TableHead className="hidden lg:table-cell">
                                            Location
                                        </TableHead>
                                        <TableHead>Health</TableHead>
                                        <TableHead>Online</TableHead>
                                        <TableHead className="hidden xl:table-cell">
                                            Content
                                        </TableHead>
                                        <TableHead className="hidden md:table-cell">
                                            Sync
                                        </TableHead>
                                        <TableHead>Last heartbeat</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {screens.map((screen) => (
                                        <TableRow
                                            key={screen.id}
                                            data-test={`admin-health-row-${screen.id}`}
                                        >
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={showScreen.url({
                                                        screen: screen.id,
                                                    })}
                                                    className="hover:underline"
                                                >
                                                    {screen.name}
                                                </Link>
                                                {screen.last_error_code ? (
                                                    <p className="text-warning text-xs">
                                                        {labelize(
                                                            screen.last_error_code,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {screen.workspace_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden lg:table-cell">
                                                {screen.location_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={healthBadgeVariant(
                                                        screen.health,
                                                    )}
                                                >
                                                    {screen.health_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm capitalize">
                                                {screen.network_state}
                                            </TableCell>
                                            <TableCell className="hidden text-sm xl:table-cell">
                                                {screen.current_design_name ??
                                                    'No content'}
                                            </TableCell>
                                            <TableCell className="hidden text-sm md:table-cell">
                                                {screen.content_sync_label ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                <div>
                                                    {formatLastSeen(
                                                        screen.last_seen_at,
                                                    )}
                                                </div>
                                                <div className="text-xs">
                                                    {formatHeartbeatAge(
                                                        screen.heartbeat_age_seconds,
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {screens.map((screen) => (
                                <div
                                    key={screen.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <Link
                                        href={showScreen.url({
                                            screen: screen.id,
                                        })}
                                        className="font-medium hover:underline"
                                    >
                                        {screen.name}
                                    </Link>
                                    <div className="text-muted-foreground text-sm">
                                        {screen.workspace_name ?? '—'}
                                    </div>
                                    <ScreenStateBadges
                                        id={screen.id}
                                        operationalStatus={
                                            screen.operational_status
                                        }
                                        pairingState={screen.pairing_state}
                                        networkState={screen.network_state}
                                    />
                                    <div className="text-muted-foreground text-xs">
                                        {screen.health_label} ·{' '}
                                        {screen.playback_state
                                            ? labelize(screen.playback_state)
                                            : 'No playback state'}{' '}
                                        · Last seen{' '}
                                        {formatLastSeen(screen.last_seen_at)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminScreenHealth.layout = {
    breadcrumbs: [
        {
            title: 'TV Health',
            href: screenHealthIndex(),
        },
    ],
};
