import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Clapperboard,
    ListVideo,
    Monitor,
    Radio,
    RotateCcw,
    Search,
    Send,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SortableTableHeader } from '@/components/ui/sortable-table-header';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListSort } from '@/hooks/use-list-sort';
import { formatRelativeDate } from '@/lib/format-bytes';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import {
    publishing as publishingIndex,
    screens as screensIndex,
} from '@/routes/app';
import publishingRoutes from '@/routes/app/publishing';
import type {
    PublishingHistoryRow,
    PublishingIndexProps,
    PublishingLiveRow,
} from '@/types/publishing';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);

function ackVariant(
    ack: string,
): 'success' | 'warning' | 'info' | 'neutral' | 'secondary' {
    switch (ack) {
        case 'live':
            return 'success';
        case 'updating':
        case 'publishing':
            return 'info';
        case 'waiting':
            return 'warning';
        case 'scheduled':
            return 'secondary';
        default:
            return 'neutral';
    }
}

function statusVariant(
    status: string,
): 'success' | 'warning' | 'neutral' | 'secondary' | 'destructive' {
    switch (status) {
        case 'active':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
            return 'destructive';
        case 'superseded':
        case 'revoked':
            return 'neutral';
        default:
            return 'secondary';
    }
}

function LiveCard({ row }: { row: PublishingLiveRow }) {
    return (
        <div
            className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4"
            data-test={`publishing-live-${row.screen_id}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={`/app/screens/${row.screen_id}`}
                        className="font-medium hover:underline"
                    >
                        {row.screen_name}
                    </Link>
                    <p className="text-muted-foreground mt-0.5 text-xs capitalize">
                        {row.network_state} · {row.operational_status}
                    </p>
                </div>
                <Badge variant={ackVariant(row.ack_state)}>
                    {row.ack_label}
                </Badge>
            </div>
            <div>
                <p className="truncate text-sm font-medium">
                    {row.content_name ?? 'No content'}
                </p>
                <p className="text-muted-foreground mt-0.5 font-mono text-xs">
                    {row.content_source === 'schedule'
                        ? `Schedule · ${row.schedule_name ?? '—'}`
                        : row.content_type === 'playlist'
                          ? `Playlist v${row.version_number ?? '—'}`
                          : row.content_type === 'screen_design'
                            ? `Screen v${row.version_number ?? '—'}`
                            : '—'}
                </p>
            </div>
            {row.deployed_at ? (
                <p className="text-muted-foreground text-xs">
                    Since {formatRelativeDate(row.deployed_at)}
                </p>
            ) : null}
            {row.next_schedule ? (
                <p className="text-muted-foreground text-xs">
                    Next: {row.next_schedule.name} —{' '}
                    <span className="font-mono">
                        {row.next_schedule.starts_at_local}
                    </span>
                </p>
            ) : null}
        </div>
    );
}

export default function PublishingIndex({
    live,
    scheduled,
    history,
    filters,
    screens,
    designs,
    playlists,
    can_publish: canPublish,
    statuses,
}: PublishingIndexProps) {
    const { flash } = usePage().props as {
        flash?: { success?: string; publish_warnings?: string[] };
    };
    const [tab, setTab] = useState<'live' | 'scheduled' | 'history'>('live');
    const [publishOpen, setPublishOpen] = useState(false);
    const [search, setSearch] = useState(filters.q);
    const [busyId, setBusyId] = useState<number | null>(null);

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: publishingIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            type: filters.type,
            screen: filters.screen,
            sort: filters.sort,
            direction: filters.direction,
        },
        defaultSort: 'deployed',
        defaultDirection: 'desc',
    });

    const form = useForm({
        content_type: 'screen_design' as 'screen_design' | 'playlist',
        content_id: '' as string,
        screen_ids: [] as number[],
    });

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (search === filters.q) {
                return;
            }
            router.get(
                publishingIndex.url({
                    query: {
                        ...filters,
                        q: search || undefined,
                        screen: filters.screen ?? undefined,
                    },
                }),
                {},
                { preserveState: true, replace: true },
            );
        }, 300);
        return () => window.clearTimeout(handle);
    }, [search, filters]);

    const contentOptions = useMemo(
        () => (form.data.content_type === 'playlist' ? playlists : designs),
        [form.data.content_type, designs, playlists],
    );

    const selectedContent = contentOptions.find(
        (c) => String(c.id) === form.data.content_id,
    );

    const orientationWarnings = useMemo(() => {
        if (!selectedContent?.orientation) {
            return [] as string[];
        }
        return screens
            .filter((s) => form.data.screen_ids.includes(s.id))
            .filter(
                (s) =>
                    s.orientation &&
                    s.orientation !== selectedContent.orientation,
            )
            .map(
                (s) =>
                    `This content is ${selectedContent.orientation} but ${s.name} is ${s.orientation}.`,
            );
    }, [selectedContent, screens, form.data.screen_ids]);

    const inactiveWarnings = useMemo(() => {
        return screens
            .filter((s) => form.data.screen_ids.includes(s.id) && s.is_inactive)
            .map(
                (s) =>
                    `${s.name} is Inactive. Content will be assigned but playback stays inactive until reactivation.`,
            );
    }, [screens, form.data.screen_ids]);

    function toggleScreen(id: number) {
        form.setData(
            'screen_ids',
            form.data.screen_ids.includes(id)
                ? form.data.screen_ids.filter((x) => x !== id)
                : [...form.data.screen_ids, id],
        );
    }

    const [publishing, setPublishing] = useState(false);

    function submitPublish() {
        setPublishing(true);
        router.post(
            publishingRoutes.store.url(),
            {
                content_type: form.data.content_type,
                content_id: Number(form.data.content_id),
                screen_ids: form.data.screen_ids,
            },
            {
                onFinish: () => setPublishing(false),
                onSuccess: () => {
                    setPublishOpen(false);
                    form.setData({
                        content_type: 'screen_design',
                        content_id: '',
                        screen_ids: [],
                    });
                },
            },
        );
    }

    function republish(row: PublishingHistoryRow) {
        setBusyId(row.id);
        router.post(
            publishingRoutes.republish.url(row.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    }

    return (
        <>
            <Head title="Publishing" />
            <div
                className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6"
                data-test="publishing-page"
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Publishing
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            See what is live, what is scheduled, and recent
                            deployments.
                        </p>
                    </div>
                    {canPublish ? (
                        <Button
                            data-test="publishing-open"
                            onClick={() => setPublishOpen(true)}
                        >
                            <Send className="size-4" />
                            Publish
                        </Button>
                    ) : null}
                </div>

                {flash?.success ? (
                    <div
                        className="border-border bg-muted/40 rounded-xl border px-4 py-3 text-sm"
                        data-test="publishing-flash"
                    >
                        {flash.success}
                    </div>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    {(
                        [
                            ['live', 'Live Now'],
                            ['scheduled', 'Scheduled'],
                            ['history', 'History'],
                        ] as const
                    ).map(([id, label]) => (
                        <Button
                            key={id}
                            type="button"
                            size="sm"
                            variant={tab === id ? 'default' : 'outline'}
                            data-test={`publishing-tab-${id}`}
                            onClick={() => setTab(id)}
                        >
                            {label}
                        </Button>
                    ))}
                </div>

                {tab === 'live' ? (
                    live.length === 0 ? (
                        <EmptyState
                            icon={Monitor}
                            title={`No ${ProductLabels.displayPlural} yet`}
                            description={`Pair a ${ProductLabels.displaySingular}, then publish a design or playlist.`}
                            action={
                                <Button asChild>
                                    <Link href={screensIndex.url()}>
                                        {`Go to ${ProductLabels.pairedNav}`}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {live.map((row) => (
                                <LiveCard key={row.screen_id} row={row} />
                            ))}
                        </div>
                    )
                ) : null}

                {tab === 'scheduled' ? (
                    scheduled.length === 0 ? (
                        <EmptyState
                            icon={CalendarClock}
                            title={`No scheduled ${ProductLabels.displayPlural}`}
                            description="Active schedules that are currently matching appear here."
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {scheduled.map((row) => (
                                <LiveCard key={row.screen_id} row={row} />
                            ))}
                        </div>
                    )
                ) : null}

                {tab === 'history' ? (
                    <div className="space-y-4">
                        <div className="flex flex-col gap-3 lg:flex-row">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                                <Input
                                    className="pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder={`Search content or ${ProductLabels.displaySingular.toLowerCase()}`}
                                    data-test="publishing-history-search"
                                />
                            </div>
                            <select
                                className={cn(selectClassName, 'lg:w-44')}
                                value={filters.status}
                                onChange={(e) =>
                                    router.get(
                                        publishingIndex.url({
                                            query: {
                                                ...filters,
                                                status: e.target.value,
                                                q: filters.q || undefined,
                                                screen:
                                                    filters.screen ?? undefined,
                                            },
                                        }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                aria-label="Status filter"
                            >
                                <option value="all">All statuses</option>
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={cn(selectClassName, 'lg:w-44')}
                                value={filters.type}
                                onChange={(e) =>
                                    router.get(
                                        publishingIndex.url({
                                            query: {
                                                ...filters,
                                                type: e.target.value,
                                                q: filters.q || undefined,
                                                screen:
                                                    filters.screen ?? undefined,
                                            },
                                        }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                aria-label="Type filter"
                            >
                                <option value="all">All types</option>
                                <option value="screen_design">Screen</option>
                                <option value="playlist">Playlist</option>
                            </select>
                        </div>

                        {history.data.length === 0 ? (
                            <EmptyState
                                icon={Radio}
                                title="No deployments yet"
                                description="Publish a design or playlist to create deployment history."
                            />
                        ) : (
                            <div className="border-border overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead
                                                aria-sort={ariaSort('type')}
                                            >
                                                <SortableTableHeader
                                                    label="Content"
                                                    column="type"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead className="hidden sm:table-cell">
                                                {ProductLabels.displaySingular}
                                            </TableHead>
                                            <TableHead
                                                className="hidden md:table-cell"
                                                aria-sort={ariaSort('deployed')}
                                            >
                                                <SortableTableHeader
                                                    label="By"
                                                    column="deployed"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead
                                                aria-sort={ariaSort('status')}
                                            >
                                                <SortableTableHeader
                                                    label="Status"
                                                    column="status"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead className="w-28" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {history.data.map((row) => (
                                            <TableRow
                                                key={row.id}
                                                data-test={`publishing-history-${row.id}`}
                                            >
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {row.content_type ===
                                                        'playlist' ? (
                                                            <ListVideo className="text-muted-foreground size-4 shrink-0" />
                                                        ) : (
                                                            <Clapperboard className="text-muted-foreground size-4 shrink-0" />
                                                        )}
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {row.content_name ??
                                                                    '—'}
                                                            </p>
                                                            <p className="text-muted-foreground font-mono text-xs">
                                                                {
                                                                    row.content_type_label
                                                                }{' '}
                                                                v
                                                                {row.version_number ??
                                                                    '—'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="hidden sm:table-cell">
                                                    {row.screen_name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">
                                                    {row.deployed_by_name ??
                                                        '—'}
                                                    <div className="font-mono text-xs">
                                                        {row.deployed_at
                                                            ? formatRelativeDate(
                                                                  row.deployed_at,
                                                              )
                                                            : '—'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={statusVariant(
                                                            row.status,
                                                        )}
                                                    >
                                                        {row.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {canPublish &&
                                                    row.can_republish ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                busyId ===
                                                                row.id
                                                            }
                                                            data-test={`publishing-republish-${row.id}`}
                                                            onClick={() =>
                                                                republish(row)
                                                            }
                                                        >
                                                            {busyId ===
                                                            row.id ? (
                                                                <Spinner />
                                                            ) : (
                                                                <RotateCcw className="size-3.5" />
                                                            )}
                                                            Republish
                                                        </Button>
                                                    ) : null}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {history.meta.last_page > 1 ? (
                            <div className="flex justify-between gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!history.links.prev}
                                    onClick={() =>
                                        history.links.prev &&
                                        router.get(history.links.prev)
                                    }
                                >
                                    Previous
                                </Button>
                                <span className="text-muted-foreground text-sm">
                                    Page {history.meta.current_page} of{' '}
                                    {history.meta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!history.links.next}
                                    onClick={() =>
                                        history.links.next &&
                                        router.get(history.links.next)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <Dialog open={publishOpen} onOpenChange={setPublishOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Publish now</DialogTitle>
                        <DialogDescription>
                            Deploy a published Screen or Playlist to one or more
                            TVs. Draft content cannot be published.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Content type</Label>
                            <div className="flex gap-2">
                                {(
                                    [
                                        ['screen_design', 'Screen'],
                                        ['playlist', 'Playlist'],
                                    ] as const
                                ).map(([value, label]) => (
                                    <Button
                                        key={value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            form.data.content_type === value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        data-test={`publish-type-${value}`}
                                        onClick={() =>
                                            form.setData({
                                                ...form.data,
                                                content_type: value,
                                                content_id: '',
                                            })
                                        }
                                    >
                                        {label}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="publish-content">Content</Label>
                            <select
                                id="publish-content"
                                className={selectClassName}
                                data-test="publish-content"
                                value={form.data.content_id}
                                onChange={(e) =>
                                    form.setData('content_id', e.target.value)
                                }
                            >
                                <option value="">Select…</option>
                                {contentOptions.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                        {c.version_number
                                            ? ` (v${c.version_number})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-2">
                            <Label>{ProductLabels.displayPlural}</Label>
                            <div className="max-h-56 space-y-2 overflow-y-auto">
                                {screens.map((screen) => (
                                    <label
                                        key={screen.id}
                                        className="border-border hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                                        data-test={`publish-screen-${screen.id}`}
                                    >
                                        <Checkbox
                                            checked={form.data.screen_ids.includes(
                                                screen.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleScreen(screen.id)
                                            }
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {screen.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs capitalize">
                                                {screen.orientation ??
                                                    'orientation unset'}{' '}
                                                · {screen.network_state} ·{' '}
                                                {screen.operational_status}
                                            </p>
                                            {screen.current_content ? (
                                                <p className="text-muted-foreground truncate text-xs">
                                                    Now:{' '}
                                                    {screen.current_content}
                                                </p>
                                            ) : null}
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {[...orientationWarnings, ...inactiveWarnings].map(
                            (warning) => (
                                <div
                                    key={warning}
                                    className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300"
                                    data-test="publish-warning"
                                >
                                    {warning}
                                </div>
                            ),
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPublishOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="publish-confirm"
                            disabled={
                                publishing ||
                                !form.data.content_id ||
                                form.data.screen_ids.length === 0
                            }
                            onClick={submitPublish}
                        >
                            {publishing ? <Spinner /> : null}
                            Publish now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
