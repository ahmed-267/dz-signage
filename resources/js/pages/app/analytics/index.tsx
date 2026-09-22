import { Head, router } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    AvailabilityAreaChart,
    HorizontalRankChart,
    PlaybackBarChart,
    StatusDonutChart,
} from '@/components/charts';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { analytics } from '@/routes/app';

type ScreenOption = { id: number; name: string };
type LocationOption = { id: number; name: string };

type Filters = {
    range: string;
    from: string;
    to: string;
    screen_id: number | null;
    location_id?: number | null;
    label: string;
    timezone: string;
};

type Dashboard = {
    overview: {
        total_screens: number;
        online_now: number;
        offline_now: number;
        unpaired_now: number;
        online_seconds: number;
        offline_seconds: number;
        playback_seconds: number;
        content_play_count: number;
        error_count: number;
        availability_percent: number | null;
        availability_note: string;
    };
    screens: {
        id: number;
        name: string;
        network_state: string;
        last_seen_at: string | null;
        online_seconds: number;
        offline_seconds: number;
        availability_percent: number | null;
        playback_seconds: number;
        content_play_count: number;
        error_count: number;
    }[];
    content: {
        screen_design_version_id: number;
        screen_design_name: string;
        version_number: number | null;
        plays: number;
        playback_seconds: number;
        screen_count: number;
        last_played_at: string | null;
    }[];
    playlists: {
        playlist_version_id: number;
        playlist_name: string;
        version_number: number | null;
        plays: number;
        playback_seconds: number;
        screen_count: number;
        last_played_at: string | null;
    }[];
    publishing: {
        total: number;
        active: number;
        pending: number;
        failed: number;
        superseded: number;
        revoked: number;
        note: string;
    };
    series: {
        availability: {
            date: string;
            online_seconds: number;
            offline_seconds: number;
            availability_percent: number | null;
        }[];
        playback: { date: string; plays: number; playback_seconds: number }[];
        errors: { date: string; error_count: number }[];
        publishing_trend: {
            date: string;
            active: number;
            pending: number;
            failed: number;
            superseded: number;
            revoked: number;
            total: number;
        }[];
    };
    empty: boolean;
};

type AnalyticsTab = 'overview' | 'tvs' | 'content' | 'publishing' | 'errors';

type Props = {
    filters: Filters;
    screens: ScreenOption[];
    locations?: LocationOption[];
    tab?: AnalyticsTab;
    dashboard: Dashboard;
    has_advanced_analytics?: boolean;
};

const RANGES = [
    { id: '7d', label: 'Last 7 days' },
    { id: '30d', label: 'Last 30 days' },
    { id: 'this_month', label: 'This month' },
    { id: 'prev_month', label: 'Previous month' },
] as const;

const TABS: { id: AnalyticsTab; label: string }[] = [
    { id: 'overview', label: 'Overview' },
    { id: 'tvs', label: 'TVs' },
    { id: 'content', label: 'Content' },
    { id: 'publishing', label: 'Publishing' },
    { id: 'errors', label: 'Errors' },
];

const VALID_TABS = new Set(TABS.map((tab) => tab.id));

function MetricCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div
            className="border-border/70 bg-card rounded-xl border p-3"
            data-test="analytics-metric"
        >
            <p className="text-muted-foreground font-mono text-[10px] tracking-[0.16em] uppercase">
                {label}
            </p>
            <p className="font-display mt-1.5 text-xl font-semibold tracking-tight">
                {value}
            </p>
            {hint ? (
                <p className="text-muted-foreground mt-1 text-xs">{hint}</p>
            ) : null}
        </div>
    );
}

function SectionCard({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'border-border/70 bg-card rounded-xl border p-3.5',
                className,
            )}
        >
            <div className="mb-2.5">
                <h2 className="text-base font-semibold tracking-tight">
                    {title}
                </h2>
                {description ? (
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

export default function AnalyticsIndex({
    filters,
    screens,
    locations = [],
    tab: tabProp = 'overview',
    dashboard,
    has_advanced_analytics: hasAdvancedAnalytics = true,
}: Props) {
    const activeTab: AnalyticsTab = VALID_TABS.has(tabProp)
        ? tabProp
        : 'overview';

    function navigate(
        patch: Partial<{
            range: string;
            screen_id: string;
            location_id: string;
            tab: AnalyticsTab;
        }>,
    ) {
        const nextTab = patch.tab ?? activeTab;
        router.get(
            analytics.url(),
            {
                range: patch.range ?? filters.range,
                ...(nextTab !== 'overview' ? { tab: nextTab } : {}),
                ...(patch.screen_id !== undefined
                    ? {
                          screen_id:
                              patch.screen_id === ''
                                  ? undefined
                                  : patch.screen_id,
                      }
                    : filters.screen_id
                      ? { screen_id: filters.screen_id }
                      : {}),
                ...(patch.location_id !== undefined
                    ? {
                          location_id:
                              patch.location_id === ''
                                  ? undefined
                                  : patch.location_id,
                      }
                    : filters.location_id
                      ? { location_id: filters.location_id }
                      : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }

    const publishingSlices = [
        {
            key: 'active',
            label: 'Active',
            value: dashboard.publishing.active,
            color: 'var(--success)',
        },
        {
            key: 'pending',
            label: 'Pending',
            value: dashboard.publishing.pending,
            color: 'var(--info)',
        },
        {
            key: 'failed',
            label: 'Failed',
            value: dashboard.publishing.failed,
            color: 'var(--destructive)',
        },
        {
            key: 'superseded',
            label: 'Superseded',
            value: dashboard.publishing.superseded,
            color: 'var(--warning)',
        },
        {
            key: 'revoked',
            label: 'Revoked',
            value: dashboard.publishing.revoked,
            color: 'var(--muted-foreground)',
        },
    ];

    const screenRank = [...dashboard.screens]
        .filter(
            (s) => s.availability_percent != null || s.content_play_count > 0,
        )
        .sort(
            (a, b) =>
                (b.availability_percent ?? -1) - (a.availability_percent ?? -1),
        )
        .slice(0, 8)
        .map((s) => ({
            id: s.id,
            label: s.name,
            value: s.availability_percent ?? 0,
        }));

    const overviewScreenRank = screenRank.slice(0, 5);

    const contentRank = dashboard.content.slice(0, 10).map((item) => ({
        id: item.screen_design_version_id,
        label: item.screen_design_name,
        value: item.plays,
    }));

    const overviewContentRank = contentRank.slice(0, 5);

    const playlistRank = dashboard.playlists.slice(0, 10).map((item) => ({
        id: item.playlist_version_id,
        label: item.playlist_name,
        value: item.plays,
    }));

    const errorSeries = dashboard.series.errors.map((row) => ({
        date: row.date,
        plays: row.error_count,
        playback_seconds: 0,
    }));

    const screensTableRows = dashboard.screens.slice(0, 10);

    return (
        <>
            <Head title="Analytics" />
            <div
                className="flex h-full flex-1 flex-col gap-4 p-3 md:p-4"
                data-test="app-analytics"
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="font-display text-xl font-semibold tracking-tight md:text-2xl">
                            Analytics
                        </h1>
                        <p className="text-muted-foreground mt-0.5 max-w-2xl text-sm">
                            Operational metrics from Player heartbeats, playback
                            events, and Deployments. Availability reflects
                            connectivity known to RMSignage.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2.5 sm:flex-row sm:items-end">
                        <div className="space-y-1">
                            <Label htmlFor="analytics-range">Date range</Label>
                            <select
                                id="analytics-range"
                                data-test="analytics-range"
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                value={filters.range}
                                onChange={(e) =>
                                    navigate({ range: e.target.value })
                                }
                            >
                                {RANGES.map((range) => (
                                    <option key={range.id} value={range.id}>
                                        {range.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="analytics-screen">
                                {ProductLabels.displaySingular}
                            </Label>
                            <select
                                id="analytics-screen"
                                data-test="analytics-screen"
                                className="border-input bg-background h-9 min-w-44 rounded-md border px-3 text-sm"
                                value={filters.screen_id ?? ''}
                                onChange={(e) =>
                                    navigate({ screen_id: e.target.value })
                                }
                            >
                                <option value="">{`All ${ProductLabels.displayPlural}`}</option>
                                {screens.map((screen) => (
                                    <option key={screen.id} value={screen.id}>
                                        {screen.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {locations.length > 0 ? (
                            <div className="space-y-1">
                                <Label htmlFor="analytics-location">
                                    Location
                                </Label>
                                <select
                                    id="analytics-location"
                                    data-test="analytics-location"
                                    className="border-input bg-background h-9 min-w-44 rounded-md border px-3 text-sm"
                                    value={filters.location_id ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            location_id: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">All locations</option>
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}
                    </div>
                </div>

                <p className="text-muted-foreground font-mono text-xs">
                    {filters.label} · {filters.from} → {filters.to} (
                    {filters.timezone})
                </p>

                {dashboard.empty ? (
                    <div data-test="analytics-empty">
                        <EmptyState
                            icon={BarChart3}
                            title="No analytics yet"
                            description={`Analytics will appear as your ${ProductLabels.displayPlural} begin playing content and reporting heartbeats.`}
                            className="min-h-[40vh]"
                        />
                    </div>
                ) : (
                    <Tabs
                        value={activeTab}
                        onValueChange={(value) =>
                            navigate({ tab: value as AnalyticsTab })
                        }
                        className="gap-3"
                    >
                        <TabsList
                            data-test="analytics-tabs"
                            className="h-auto flex-wrap"
                        >
                            {TABS.map((tab) => (
                                <TabsTrigger
                                    key={tab.id}
                                    value={tab.id}
                                    data-test={`analytics-tab-${tab.id}`}
                                >
                                    {tab.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        <TabsContent
                            value="overview"
                            className="space-y-3"
                            data-test="analytics-panel-overview"
                        >
                            <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                                <MetricCard
                                    label={ProductLabels.displayPlural}
                                    value={String(
                                        dashboard.overview.total_screens,
                                    )}
                                    hint={`${dashboard.overview.online_now} online · ${dashboard.overview.offline_now} offline`}
                                />
                                <MetricCard
                                    label="Availability"
                                    value={
                                        dashboard.overview
                                            .availability_percent != null
                                            ? `${dashboard.overview.availability_percent}%`
                                            : '—'
                                    }
                                    hint={dashboard.overview.availability_note}
                                />
                                <MetricCard
                                    label="Playback time"
                                    value={formatDuration(
                                        dashboard.overview.playback_seconds,
                                    )}
                                    hint={`${dashboard.overview.content_play_count} content starts`}
                                />
                                <MetricCard
                                    label="Player errors"
                                    value={String(
                                        dashboard.overview.error_count,
                                    )}
                                    hint="From playback telemetry in this range"
                                />
                            </div>

                            <div className="grid gap-2.5 lg:grid-cols-2">
                                <SectionCard
                                    title="Availability trend"
                                    description="Daily connectivity from heartbeats"
                                >
                                    <AvailabilityAreaChart
                                        data={dashboard.series.availability}
                                        height={220}
                                    />
                                </SectionCard>
                                <SectionCard
                                    title="Playback trend"
                                    description="Daily content starts from Players"
                                >
                                    <PlaybackBarChart
                                        data={dashboard.series.playback}
                                        metric="plays"
                                        height={220}
                                    />
                                </SectionCard>
                            </div>

                            <div className="grid gap-2.5 lg:grid-cols-2">
                                <SectionCard
                                    title="Top content"
                                    description={`${ProductLabels.screenDesignPlural} by content starts`}
                                >
                                    <HorizontalRankChart
                                        data={overviewContentRank}
                                        valueLabel="Plays"
                                        height={180}
                                        emptyTitle="No content starts"
                                        emptyDescription="No content starts recorded in this range yet."
                                    />
                                </SectionCard>
                                <SectionCard
                                    title={`${ProductLabels.displaySingular} summary`}
                                    description={`Top availability · ${ProductLabels.displayPlural}`}
                                >
                                    <HorizontalRankChart
                                        data={overviewScreenRank}
                                        valueLabel="Availability %"
                                        formatValue={(v) => `${v}%`}
                                        height={180}
                                        emptyTitle={`No ${ProductLabels.displaySingular} stats yet`}
                                        emptyDescription={`${ProductLabels.displaySingular} rankings appear after daily stats are aggregated.`}
                                    />
                                </SectionCard>
                            </div>
                        </TabsContent>

                        <TabsContent
                            value="tvs"
                            className="space-y-3"
                            data-test="analytics-panel-tvs"
                        >
                            <div className="grid gap-2.5 lg:grid-cols-2">
                                <SectionCard
                                    title={`${ProductLabels.displaySingular} performance`}
                                    description={`Availability by ${ProductLabels.displaySingular}`}
                                >
                                    <HorizontalRankChart
                                        data={screenRank}
                                        valueLabel="Availability %"
                                        formatValue={(v) => `${v}%`}
                                        height={240}
                                        emptyTitle={`No ${ProductLabels.displaySingular} stats yet`}
                                        emptyDescription={`${ProductLabels.displaySingular} rankings appear after daily stats are aggregated.`}
                                    />
                                </SectionCard>

                                <SectionCard
                                    title={`${ProductLabels.displaySingular} detail`}
                                >
                                    <div className="overflow-x-auto">
                                        <table
                                            className="w-full min-w-[640px] text-left text-sm"
                                            data-test="analytics-screens-table"
                                        >
                                            <thead className="bg-muted/40 text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                                <tr>
                                                    <th className="px-3 py-2 font-medium">
                                                        {
                                                            ProductLabels.displaySingular
                                                        }
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Now
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Availability
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Playback
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Plays
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Errors
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {screensTableRows.map(
                                                    (screen) => (
                                                        <tr
                                                            key={screen.id}
                                                            className="border-border/60 border-t"
                                                        >
                                                            <td className="px-3 py-2 font-medium">
                                                                {screen.name}
                                                            </td>
                                                            <td className="px-3 py-2 capitalize">
                                                                <span
                                                                    className={cn(
                                                                        screen.network_state ===
                                                                            'online'
                                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                                            : 'text-muted-foreground',
                                                                    )}
                                                                >
                                                                    {
                                                                        screen.network_state
                                                                    }
                                                                </span>
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {screen.availability_percent !=
                                                                null
                                                                    ? `${screen.availability_percent}%`
                                                                    : '—'}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {formatDuration(
                                                                    screen.playback_seconds,
                                                                )}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {
                                                                    screen.content_play_count
                                                                }
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {
                                                                    screen.error_count
                                                                }
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </SectionCard>
                            </div>
                        </TabsContent>

                        <TabsContent
                            value="content"
                            className="space-y-3"
                            data-test="analytics-panel-content"
                        >
                            {hasAdvancedAnalytics ? (
                                <div className="grid gap-2.5 lg:grid-cols-2">
                                    <SectionCard
                                        title="Top content"
                                        description={`${ProductLabels.screenDesignPlural} by content starts`}
                                    >
                                        <HorizontalRankChart
                                            data={contentRank}
                                            valueLabel="Plays"
                                            height={220}
                                            emptyTitle="No content starts"
                                            emptyDescription="No content starts recorded in this range yet."
                                        />
                                        {dashboard.content.length > 0 ? (
                                            <ul
                                                className="border-border/60 mt-3 divide-y rounded-lg border"
                                                data-test="analytics-content-list"
                                            >
                                                {dashboard.content
                                                    .slice(0, 8)
                                                    .map((item) => (
                                                        <li
                                                            key={
                                                                item.screen_design_version_id
                                                            }
                                                            className="flex items-start justify-between gap-3 px-3 py-2"
                                                        >
                                                            <div>
                                                                <p className="text-sm font-medium">
                                                                    {
                                                                        item.screen_design_name
                                                                    }
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    v
                                                                    {item.version_number ??
                                                                        '—'}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        item.screen_count
                                                                    }{' '}
                                                                    {
                                                                        ProductLabels.displayPlural
                                                                    }
                                                                </p>
                                                            </div>
                                                            <div className="text-right text-sm">
                                                                <p>
                                                                    {item.plays}{' '}
                                                                    plays
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    {formatDuration(
                                                                        item.playback_seconds,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </li>
                                                    ))}
                                            </ul>
                                        ) : null}
                                    </SectionCard>

                                    {dashboard.playlists.length > 0 ? (
                                        <SectionCard
                                            title="Playlist analytics"
                                            description="Playlist versions by content starts"
                                        >
                                            <HorizontalRankChart
                                                data={playlistRank}
                                                valueLabel="Plays"
                                                height={220}
                                            />
                                            <ul
                                                className="border-border/60 mt-3 divide-y rounded-lg border"
                                                data-test="analytics-playlists-list"
                                            >
                                                {dashboard.playlists
                                                    .slice(0, 8)
                                                    .map((item) => (
                                                        <li
                                                            key={
                                                                item.playlist_version_id
                                                            }
                                                            className="flex items-start justify-between gap-3 px-3 py-2"
                                                        >
                                                            <div>
                                                                <p className="text-sm font-medium">
                                                                    {
                                                                        item.playlist_name
                                                                    }
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    v
                                                                    {item.version_number ??
                                                                        '—'}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        item.screen_count
                                                                    }{' '}
                                                                    {
                                                                        ProductLabels.displayPlural
                                                                    }
                                                                </p>
                                                            </div>
                                                            <div className="text-right text-sm">
                                                                <p>
                                                                    {item.plays}{' '}
                                                                    plays
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    {formatDuration(
                                                                        item.playback_seconds,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </li>
                                                    ))}
                                            </ul>
                                        </SectionCard>
                                    ) : (
                                        <SectionCard
                                            title="Playlist analytics"
                                            description="Appears when Players report playlist content starts"
                                        >
                                            <p className="text-muted-foreground text-sm">
                                                No playlist playback in this
                                                range yet.
                                            </p>
                                        </SectionCard>
                                    )}
                                </div>
                            ) : (
                                <SectionCard
                                    title="Advanced Analytics"
                                    description="Content and playlist breakdowns"
                                >
                                    <p
                                        className="text-muted-foreground text-sm"
                                        data-test="analytics-advanced-locked"
                                    >
                                        Advanced Analytics is available on
                                        Business and Enterprise plans.
                                    </p>
                                </SectionCard>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="publishing"
                            className="space-y-3"
                            data-test="analytics-panel-publishing"
                        >
                            <SectionCard
                                title="Publishing"
                                description="Deployment statuses in this range · superseded is not a failure"
                                className="max-w-xl"
                            >
                                <StatusDonutChart
                                    data={publishingSlices}
                                    height={240}
                                    emptyTitle="No deployments"
                                    emptyDescription="Publishing breakdown appears after Deployments are created."
                                />
                                <p
                                    className="text-muted-foreground mt-3 text-xs"
                                    data-test="analytics-publishing"
                                >
                                    {dashboard.publishing.note}
                                </p>
                            </SectionCard>
                        </TabsContent>

                        <TabsContent
                            value="errors"
                            className="space-y-3"
                            data-test="analytics-panel-errors"
                        >
                            <SectionCard
                                title="Error trend"
                                description={`Daily Player errors from ${ProductLabels.displaySingular} daily stats`}
                            >
                                <PlaybackBarChart
                                    data={errorSeries}
                                    metric="plays"
                                    seriesName="Errors"
                                    variant="line"
                                    height={260}
                                    emptyTitle="No errors recorded"
                                    emptyDescription="Error trend stays empty when Players report no errors."
                                />
                            </SectionCard>
                        </TabsContent>
                    </Tabs>
                )}
            </div>
        </>
    );
}

AnalyticsIndex.layout = () => ({
    breadcrumbs: [{ title: 'Analytics', href: analytics.url() }],
});
