import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Copy,
    Monitor,
    MoreHorizontal,
    Plus,
    Power,
    PowerOff,
    Search,
    Unplug,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    healthBadgeVariant,
    ScreenStateBadges,
} from '@/components/screens/screen-state-badges';
import { TvContentPreviewDialog } from '@/components/screens/tv-content-preview-dialog';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { useListSort } from '@/hooks/use-list-sort';
import { formatLastSeen } from '@/lib/format-relative-time';
import { ProductLabels, displayLabel } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import {
    playlists as playlistsIndex,
    publishing,
    schedules as schedulesIndex,
    screen_designs as screenDesignsIndex,
    screens as screensIndex,
} from '@/routes/app';
import screenRoutes from '@/routes/app/screens';
import type {
    PublishedDesignOption,
    ScreenCounts,
    ScreenListItem,
    ScreensIndexProps,
    ScreenStateFilter,
} from '@/types/screen';

const REFRESH_MS = 30_000;

const FILTERS: {
    id: ScreenStateFilter;
    label: string;
    countKey: keyof ScreenCounts;
}[] = [
    { id: 'all', label: 'All', countKey: 'all' },
    { id: 'online', label: 'Online', countKey: 'online' },
    { id: 'offline', label: 'Offline', countKey: 'offline' },
    { id: 'active', label: 'Active', countKey: 'active' },
    { id: 'inactive', label: 'Inactive', countKey: 'inactive' },
    { id: 'connected', label: 'Connected', countKey: 'connected' },
    { id: 'disconnected', label: 'Disconnected', countKey: 'disconnected' },
];

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function StateBadges({ screen }: { screen: ScreenListItem }) {
    return (
        <ScreenStateBadges
            id={screen.id}
            operationalStatus={screen.operational_status}
            pairingState={screen.pairing_state}
            networkState={screen.network_state}
        />
    );
}

function CurrentContent({
    screen,
    onPreview,
}: {
    screen: ScreenListItem;
    onPreview?: () => void;
}) {
    const showing = screen.now_showing;
    const name =
        showing?.content_name ??
        screen.content_name ??
        screen.current_design_name;

    if (!name) {
        return (
            <span
                className="text-muted-foreground"
                data-test="tv-now-showing-empty"
            >
                No content
            </span>
        );
    }

    const typeLabel =
        showing?.content_type_label ??
        (showing?.content_source === 'schedule'
            ? 'Schedule-controlled'
            : 'Published');
    const meta: string[] = [];
    if (
        showing?.playlist_item_count != null &&
        showing.playlist_item_count > 0
    ) {
        meta.push(
            `${showing.playlist_item_count} Screen${showing.playlist_item_count === 1 ? '' : 's'}`,
        );
    } else if (showing?.version_number != null) {
        meta.push(`v${showing.version_number}`);
    } else if (screen.current_version_number != null) {
        meta.push(`v${screen.current_version_number}`);
    }
    if (showing?.window_ends_at_local) {
        meta.push(`until ${showing.window_ends_at_local}`);
    }
    if (showing?.ack_label) {
        meta.push(showing.ack_label);
    } else if (screen.content_sync === 'out_of_sync') {
        meta.push('Out of sync');
    } else if (screen.content_sync === 'up_to_date') {
        meta.push('Up to date');
    }

    return (
        <div className="min-w-0" data-test="tv-now-showing">
            <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                Now Showing
            </p>
            {onPreview ? (
                <button
                    type="button"
                    onClick={onPreview}
                    className="text-foreground hover:text-primary focus-visible:ring-ring max-w-full truncate text-left text-sm font-medium underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    data-test={`tv-now-showing-name-${screen.id}`}
                    aria-label={`Preview current content: ${name}`}
                >
                    {name}
                </button>
            ) : (
                <p className="truncate font-medium">{name}</p>
            )}
            <p className="text-muted-foreground text-xs">
                {typeLabel}
                {meta.length > 0 ? ` · ${meta.join(' · ')}` : ''}
            </p>
        </div>
    );
}

export default function ScreensIndex({
    screens,
    filters,
    counts,
    published_designs: publishedDesigns,
    published_playlists: publishedPlaylists = [],
    locations = [],
    require_location: requireLocation = false,
    can_manage: canManage,
    can_publish: canPublish,
    licences,
    workspace_name: workspaceName,
    player_url: playerUrl,
}: ScreensIndexProps) {
    const [searchInput, setSearchInput] = useState(filters.q);

    const [addOpen, setAddOpen] = useState(false);
    const [pairStep, setPairStep] = useState<'form' | 'success'>('form');
    const [pairCode, setPairCode] = useState('');
    const [pairName, setPairName] = useState('');
    const [pairOrientation, setPairOrientation] = useState('');
    const [pairLocationId, setPairLocationId] = useState('');
    const [pairing, setPairing] = useState(false);
    const [copiedPlayerUrl, copyPlayerUrl] = useClipboard();

    const [renameScreen, setRenameScreen] = useState<ScreenListItem | null>(
        null,
    );
    const [renameValue, setRenameValue] = useState('');

    const [publishScreen, setPublishScreen] = useState<ScreenListItem | null>(
        null,
    );
    const [publishDesignId, setPublishDesignId] = useState<string>('');
    const [publishPlaylistId, setPublishPlaylistId] = useState<string>('');
    const [publishKind, setPublishKind] = useState<
        'screen_design' | 'playlist' | 'schedule'
    >('screen_design');

    const [bulkPublishOpen, setBulkPublishOpen] = useState(false);
    const [bulkDesignId, setBulkDesignId] = useState<string>('');

    const [unpairScreen, setUnpairScreen] = useState<ScreenListItem | null>(
        null,
    );
    const [busyId, setBusyId] = useState<number | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [previewScreen, setPreviewScreen] = useState<ScreenListItem | null>(
        null,
    );

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: screensIndex.url(),
        filters: {
            q: filters.q,
            filter: filters.filter,
            sort: filters.sort,
            direction: filters.direction,
            location: filters.location,
        },
        defaultSort: 'updated',
        defaultDirection: 'desc',
    });

    const visibleIds = useMemo(() => screens.map((s) => s.id), [screens]);

    const dialogOpen =
        addOpen ||
        bulkPublishOpen ||
        renameScreen !== null ||
        publishScreen !== null ||
        unpairScreen !== null;

    useEffect(() => {
        setSearchInput(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === filters.q) {
                return;
            }
            navigate({ q: searchInput });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    // Drop selections for screens that left the current filter/page.
    useEffect(() => {
        setSelected((prev) => prev.filter((id) => visibleIds.includes(id)));
    }, [visibleIds]);

    // Presence changes server-side; refresh the list quietly.
    useEffect(() => {
        const handle = window.setInterval(() => {
            if (dialogOpen || selected.length > 0) {
                return;
            }
            router.reload({ only: ['screens', 'counts'] });
        }, REFRESH_MS);

        return () => window.clearInterval(handle);
    }, [dialogOpen, selected.length]);

    function navigate(
        patch: Partial<{
            q: string;
            filter: string;
            sort: string;
            direction: string;
            location: string;
        }>,
    ) {
        router.get(
            screensIndex.url(),
            {
                q: patch.q ?? filters.q,
                filter: patch.filter ?? filters.filter,
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
                location:
                    patch.location !== undefined
                        ? patch.location || undefined
                        : filters.location
                          ? String(filters.location)
                          : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function openAdd() {
        setPairCode('');
        setPairName(`${workspaceName} TV`);
        setPairOrientation('');
        setPairLocationId(
            locations.length === 1 ? String(locations[0].id) : '',
        );
        setPairStep('form');
        setAddOpen(true);
    }

    function handlePair() {
        if (!pairCode.trim() || !pairName.trim()) {
            return;
        }
        if (requireLocation && !pairLocationId) {
            return;
        }
        setPairing(true);
        router.post(
            screenRoutes.pair.store.url(),
            {
                code: pairCode.trim(),
                name: pairName.trim(),
                orientation: pairOrientation || null,
                location_id: pairLocationId ? Number(pairLocationId) : null,
            },
            {
                onFinish: () => setPairing(false),
                onSuccess: () => setPairStep('success'),
            },
        );
    }

    function handleRename() {
        if (!renameScreen || !renameValue.trim()) {
            return;
        }
        setBusyId(renameScreen.id);
        router.post(
            screenRoutes.rename.url(renameScreen.id),
            { name: renameValue.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setRenameScreen(null);
                },
            },
        );
    }

    function handlePublish() {
        if (!publishScreen) {
            return;
        }
        if (publishKind === 'schedule') {
            router.visit('/app/schedules/create');
            return;
        }
        if (publishKind === 'playlist') {
            if (!publishPlaylistId) {
                return;
            }
            setBusyId(publishScreen.id);
            router.post(
                screenRoutes.publish.url(publishScreen.id),
                {
                    content_kind: 'playlist',
                    playlist_id: Number(publishPlaylistId),
                },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        setBusyId(null);
                        setPublishScreen(null);
                        setPublishPlaylistId('');
                        setPublishKind('screen_design');
                    },
                },
            );
            return;
        }
        if (!publishDesignId) {
            return;
        }
        setBusyId(publishScreen.id);
        router.post(
            screenRoutes.publish.url(publishScreen.id),
            {
                content_kind: 'screen_design',
                screen_design_id: Number(publishDesignId),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setPublishScreen(null);
                    setPublishDesignId('');
                    setPublishKind('screen_design');
                },
            },
        );
    }

    function handleStatus(screen: ScreenListItem, next: 'active' | 'inactive') {
        setBusyId(screen.id);
        router.post(
            screenRoutes.status.url(screen.id),
            { operational_status: next },
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    }

    function handleUnpair() {
        if (!unpairScreen) {
            return;
        }
        setBusyId(unpairScreen.id);
        router.delete(screenRoutes.destroy.url(unpairScreen.id), {
            onFinish: () => {
                setBusyId(null);
                setUnpairScreen(null);
            },
        });
    }

    function handleBulkStatus(next: 'active' | 'inactive') {
        if (selected.length === 0) {
            return;
        }
        router.post(
            screenRoutes.bulk_status.url(),
            { screen_ids: selected, operational_status: next },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    }

    function handleBulkPublish() {
        if (selected.length === 0 || !bulkDesignId) {
            return;
        }
        router.post(
            screenRoutes.bulk_publish.url(),
            {
                screen_ids: selected,
                screen_design_id: Number(bulkDesignId),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulkPublishOpen(false);
                    setBulkDesignId('');
                },
            },
        );
    }

    function toggleSelected(id: number, checked: boolean) {
        setSelected((prev) =>
            checked
                ? [...new Set([...prev, id])]
                : prev.filter((x) => x !== id),
        );
    }

    const allSelected =
        screens.length > 0 && selected.length === screens.length;

    return (
        <>
            <Head title={ProductLabels.pairedNav} />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {ProductLabels.pairedNav}
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Pair TVs and browsers, then publish designs to them.
                        </p>
                        {licences ? (
                            <p
                                className="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-x-2 text-xs"
                                data-test="screens-licences"
                            >
                                <span>
                                    <span className="text-foreground font-mono">
                                        {licences.used} / {licences.licensed}
                                    </span>{' '}
                                    {ProductLabels.licences} used
                                </span>
                                <Link
                                    href={licences.manage_url}
                                    className="hover:text-foreground underline underline-offset-2"
                                    data-test="screens-licences-manage"
                                >
                                    Manage licences
                                </Link>
                            </p>
                        ) : null}
                    </div>
                    {canManage ? (
                        <Button
                            type="button"
                            data-test="screens-add"
                            data-tour="pair-tv"
                            onClick={openAdd}
                        >
                            <Plus className="size-4" />
                            {ProductLabels.pairAction}
                        </Button>
                    ) : null}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="screens-search"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder={`Search ${ProductLabels.displayPlural.toLowerCase()}...`}
                            className="pl-9"
                            aria-label={`Search ${ProductLabels.displayPlural}`}
                        />
                    </div>
                    {locations.length > 0 ? (
                        <select
                            className={cn(selectClassName, 'sm:w-52')}
                            value={
                                filters.location ? String(filters.location) : ''
                            }
                            onChange={(e) =>
                                navigate({ location: e.target.value })
                            }
                            data-test="screens-location-filter"
                            aria-label="Filter by location"
                        >
                            <option value="">All locations</option>
                            {locations.map((location) => (
                                <option
                                    key={location.id}
                                    value={String(location.id)}
                                >
                                    {location.name}
                                    {location.city ? ` · ${location.city}` : ''}
                                </option>
                            ))}
                        </select>
                    ) : null}
                </div>

                <div className="flex gap-1.5 overflow-x-auto pb-1">
                    {FILTERS.map((chip) => {
                        const active = filters.filter === chip.id;
                        return (
                            <button
                                key={chip.id}
                                type="button"
                                data-test={`screens-filter-${chip.id}`}
                                onClick={() => navigate({ filter: chip.id })}
                                aria-pressed={active}
                                className={cn(
                                    'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:text-foreground hover:border-primary/40',
                                )}
                            >
                                {chip.label}
                                <span className="ml-1.5 font-mono">
                                    {counts[chip.countKey]}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {selected.length > 0 && (canManage || canPublish) ? (
                    <div
                        data-test="screens-bulk-bar"
                        className="border-border bg-card flex flex-wrap items-center gap-2 rounded-lg border p-3"
                    >
                        <span className="text-sm font-medium">
                            {selected.length} selected
                        </span>
                        <div className="flex flex-wrap gap-2 sm:ml-auto">
                            {canManage ? (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        data-test="screens-bulk-activate"
                                        onClick={() =>
                                            handleBulkStatus('active')
                                        }
                                    >
                                        <Power className="size-3.5" />
                                        Activate
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        data-test="screens-bulk-deactivate"
                                        onClick={() =>
                                            handleBulkStatus('inactive')
                                        }
                                    >
                                        <PowerOff className="size-3.5" />
                                        Deactivate
                                    </Button>
                                </>
                            ) : null}
                            {canPublish ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    data-test="screens-bulk-publish"
                                    onClick={() => {
                                        setBulkDesignId('');
                                        setBulkPublishOpen(true);
                                    }}
                                >
                                    <Upload className="size-3.5" />
                                    Publish
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelected([])}
                            >
                                Clear
                            </Button>
                        </div>
                    </div>
                ) : null}

                {screens.length === 0 ? (
                    counts.all === 0 ? (
                        <EmptyState
                            icon={Monitor}
                            title={`No ${ProductLabels.displayPlural} paired yet`}
                            description="Open the Player URL on a TV or browser, enter the pairing code shown there, then name the TV and assign a location. After pairing, publish content from Publishing."
                            action={
                                canManage ? (
                                    <div className="flex flex-col items-center gap-3">
                                        <Button
                                            type="button"
                                            data-test="screens-empty-add"
                                            onClick={openAdd}
                                        >
                                            <Plus className="size-4" />
                                            {ProductLabels.pairAction}
                                        </Button>
                                        {playerUrl ? (
                                            <p className="text-muted-foreground max-w-sm text-center text-xs">
                                                Player URL:{' '}
                                                <span className="text-foreground font-mono break-all">
                                                    {playerUrl}
                                                </span>
                                            </p>
                                        ) : null}
                                    </div>
                                ) : undefined
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Search}
                            title={`No ${ProductLabels.displayPlural} match`}
                            description="Try a different search term or filter."
                            action={
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        navigate({ q: '', filter: 'all' })
                                    }
                                >
                                    Clear filters
                                </Button>
                            }
                        />
                    )
                ) : (
                    <>
                        <div className="border-border bg-card hidden overflow-hidden rounded-xl border md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {canManage || canPublish ? (
                                            <TableHead className="w-10">
                                                <Checkbox
                                                    checked={allSelected}
                                                    onCheckedChange={(value) =>
                                                        setSelected(
                                                            value === true
                                                                ? visibleIds
                                                                : [],
                                                        )
                                                    }
                                                    aria-label={`Select all ${ProductLabels.displayPlural}`}
                                                    data-test="screens-select-all"
                                                />
                                            </TableHead>
                                        ) : null}
                                        <TableHead aria-sort={ariaSort('name')}>
                                            <SortableTableHeader
                                                label={
                                                    ProductLabels.displaySingular
                                                }
                                                column="name"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Health</TableHead>
                                        <TableHead>Current content</TableHead>
                                        <TableHead aria-sort={ariaSort('seen')}>
                                            <SortableTableHeader
                                                label="Last seen"
                                                column="seen"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {screens.map((screen) => (
                                        <TableRow
                                            key={screen.id}
                                            data-test={`screen-row-${screen.id}`}
                                        >
                                            {canManage || canPublish ? (
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selected.includes(
                                                            screen.id,
                                                        )}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleSelected(
                                                                screen.id,
                                                                value === true,
                                                            )
                                                        }
                                                        aria-label={`Select ${screen.name}`}
                                                        data-test={`screen-select-${screen.id}`}
                                                    />
                                                </TableCell>
                                            ) : null}
                                            <TableCell>
                                                <Link
                                                    href={screenRoutes.show.url(
                                                        screen.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {screen.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs capitalize">
                                                    {screen.orientation ??
                                                        'No orientation'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StateBadges screen={screen} />
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col items-start gap-1">
                                                    <Badge
                                                        variant={healthBadgeVariant(
                                                            screen.health,
                                                        )}
                                                    >
                                                        {screen.health_label}
                                                    </Badge>
                                                    {screen.orientation_mismatch ? (
                                                        <span className="text-warning flex items-center gap-1 text-xs">
                                                            <AlertTriangle className="size-3" />
                                                            Orientation mismatch
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-56 text-sm">
                                                <CurrentContent
                                                    screen={screen}
                                                    onPreview={() =>
                                                        setPreviewScreen(screen)
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatLastSeen(
                                                    screen.last_seen_at,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <RowActions
                                                    screen={screen}
                                                    busy={busyId === screen.id}
                                                    canManage={canManage}
                                                    canPublish={canPublish}
                                                    testId={`screen-menu-${screen.id}`}
                                                    onRename={() => {
                                                        setRenameScreen(screen);
                                                        setRenameValue(
                                                            screen.name,
                                                        );
                                                    }}
                                                    onPublish={() => {
                                                        setPublishScreen(
                                                            screen,
                                                        );
                                                        setPublishDesignId('');
                                                    }}
                                                    onStatus={handleStatus}
                                                    onUnpair={() =>
                                                        setUnpairScreen(screen)
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="grid gap-3 md:hidden">
                            {screens.map((screen) => (
                                <div
                                    key={screen.id}
                                    data-test={`screen-card-${screen.id}`}
                                    className="border-border bg-card overflow-hidden rounded-xl border"
                                >
                                    <div className="flex items-start justify-between gap-2 p-4 pb-3">
                                        <div className="flex min-w-0 items-start gap-3">
                                            {canManage || canPublish ? (
                                                <Checkbox
                                                    className="mt-1"
                                                    checked={selected.includes(
                                                        screen.id,
                                                    )}
                                                    onCheckedChange={(value) =>
                                                        toggleSelected(
                                                            screen.id,
                                                            value === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${screen.name}`}
                                                />
                                            ) : null}
                                            <div className="min-w-0">
                                                <Link
                                                    href={screenRoutes.show.url(
                                                        screen.id,
                                                    )}
                                                    className="truncate font-medium hover:underline"
                                                >
                                                    {screen.name}
                                                </Link>
                                                <p className="text-muted-foreground mt-0.5 text-xs capitalize">
                                                    {screen.orientation ??
                                                        'No orientation'}
                                                </p>
                                            </div>
                                        </div>
                                        <RowActions
                                            screen={screen}
                                            busy={busyId === screen.id}
                                            canManage={canManage}
                                            canPublish={canPublish}
                                            testId={`screen-card-menu-${screen.id}`}
                                            onRename={() => {
                                                setRenameScreen(screen);
                                                setRenameValue(screen.name);
                                            }}
                                            onPublish={() => {
                                                setPublishScreen(screen);
                                                setPublishDesignId('');
                                                setPublishPlaylistId('');
                                                setPublishKind('screen_design');
                                            }}
                                            onStatus={handleStatus}
                                            onUnpair={() =>
                                                setUnpairScreen(screen)
                                            }
                                        />
                                    </div>

                                    <div className="px-4">
                                        <StateBadges screen={screen} />
                                    </div>

                                    <div className="text-muted-foreground space-y-1 px-4 pt-3 pb-4 text-xs">
                                        <p>
                                            Health:{' '}
                                            <span className="text-foreground">
                                                {screen.health_label}
                                            </span>
                                        </p>
                                        <div className="text-foreground">
                                            <CurrentContent
                                                screen={screen}
                                                onPreview={() =>
                                                    setPreviewScreen(screen)
                                                }
                                            />
                                        </div>
                                        <p>
                                            Last seen:{' '}
                                            {formatLastSeen(
                                                screen.last_seen_at,
                                            )}
                                        </p>
                                        {screen.orientation_mismatch ? (
                                            <p className="text-warning flex items-center gap-1">
                                                <AlertTriangle className="size-3" />
                                                Orientation mismatch
                                            </p>
                                        ) : null}
                                        <div className="flex flex-wrap gap-2 pt-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={screenRoutes.show.url(
                                                        screen.id,
                                                    )}
                                                    data-test={`tv-view-content-${screen.id}`}
                                                >
                                                    View TV
                                                </Link>
                                            </Button>
                                            {canPublish ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() => {
                                                        setPublishScreen(
                                                            screen,
                                                        );
                                                        setPublishDesignId('');
                                                        setPublishPlaylistId(
                                                            '',
                                                        );
                                                        setPublishKind(
                                                            'screen_design',
                                                        );
                                                    }}
                                                    data-test={`tv-change-content-${screen.id}`}
                                                >
                                                    Change Content
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>

            <Dialog
                open={addOpen}
                onOpenChange={(open) => {
                    setAddOpen(open);
                    if (!open) {
                        setPairStep('form');
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    {pairStep === 'success' ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>TV paired</DialogTitle>
                                <DialogDescription>
                                    {pairName.trim() || 'Your TV'} is connected.
                                    Publish a Screen or Playlist so it has
                                    something to show.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col items-center gap-4 py-4">
                                <div className="bg-success/15 text-success flex size-14 items-center justify-center rounded-full">
                                    <Check
                                        className="size-7"
                                        strokeWidth={2.5}
                                    />
                                </div>
                            </div>
                            <DialogFooter className="flex-col gap-2 sm:flex-col sm:space-x-0">
                                <p className="text-muted-foreground w-full text-center text-sm">
                                    What would you like to show?
                                </p>
                                <div className="flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
                                    {canPublish ? (
                                        <>
                                            <Button type="button" asChild>
                                                <Link
                                                    href={screenDesignsIndex.url()}
                                                    data-test="screens-pair-publish-screen"
                                                >
                                                    Publish a Screen
                                                </Link>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                asChild
                                            >
                                                <Link
                                                    href={playlistsIndex.url()}
                                                    data-test="screens-pair-publish-playlist"
                                                >
                                                    Publish a Playlist
                                                </Link>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={schedulesIndex.url()}
                                                    data-test="screens-pair-setup-schedule"
                                                >
                                                    Set up a Schedule
                                                </Link>
                                            </Button>
                                        </>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={publishing.url()}>
                                                Open Publishing
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full"
                                    onClick={() => {
                                        setAddOpen(false);
                                        setPairStep('form');
                                    }}
                                    data-test="screens-pair-done"
                                >
                                    Done
                                </Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {ProductLabels.pairAction}
                                </DialogTitle>
                                <DialogDescription>
                                    Follow the steps on the TV, then enter the
                                    pairing code below. Use a browser on the
                                    display, or the Fire TV APK that wraps the
                                    same hosted Player. There is no separate
                                    native TV application.
                                </DialogDescription>
                            </DialogHeader>
                            <ol className="bg-muted/40 border-border space-y-3 rounded-xl border p-4 text-sm">
                                <li className="flex gap-3">
                                    <span className="text-muted-foreground font-mono text-xs font-semibold">
                                        1
                                    </span>
                                    <div className="min-w-0 flex-1 space-y-2">
                                        <p className="font-medium">
                                            Open the Player URL on the TV
                                        </p>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <code
                                                className="bg-background border-border block max-w-full truncate rounded-md border px-2 py-1 font-mono text-xs"
                                                data-test="screens-player-url"
                                            >
                                                {playerUrl}
                                            </code>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    void copyPlayerUrl(
                                                        playerUrl,
                                                    )
                                                }
                                                data-test="screens-player-url-copy"
                                            >
                                                {copiedPlayerUrl ===
                                                playerUrl ? (
                                                    <Check className="size-3.5" />
                                                ) : (
                                                    <Copy className="size-3.5" />
                                                )}
                                                Copy
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                                <li className="flex gap-3">
                                    <span className="text-muted-foreground font-mono text-xs font-semibold">
                                        2
                                    </span>
                                    <p>
                                        <span className="font-medium">
                                            The TV shows a QR code and PIN.
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            Keep that screen open until pairing
                                            finishes.
                                        </span>
                                    </p>
                                </li>
                                <li className="flex gap-3">
                                    <span className="text-muted-foreground font-mono text-xs font-semibold">
                                        3
                                    </span>
                                    <p>
                                        <span className="font-medium">
                                            Enter the PIN here
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            — or scan the QR with another device
                                            to open the claim link.
                                        </span>
                                    </p>
                                </li>
                                <li className="flex gap-3">
                                    <span className="text-muted-foreground font-mono text-xs font-semibold">
                                        4
                                    </span>
                                    <p>
                                        <span className="font-medium">
                                            Name the TV and pick a location
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            so your team can find it later.
                                        </span>
                                    </p>
                                </li>
                                <li className="flex gap-3">
                                    <span className="text-muted-foreground font-mono text-xs font-semibold">
                                        5
                                    </span>
                                    <p className="text-muted-foreground">
                                        After a successful pair, publish content
                                        so the TV has something to display.
                                    </p>
                                </li>
                            </ol>
                            <div className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="pair-code">
                                        Pairing code
                                    </Label>
                                    <Input
                                        id="pair-code"
                                        data-test="screens-pair-code"
                                        value={pairCode}
                                        onChange={(e) =>
                                            setPairCode(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="ABCD-1234"
                                        autoComplete="off"
                                        className="font-mono tracking-widest uppercase"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="pair-name">TV name</Label>
                                    <Input
                                        id="pair-name"
                                        data-test="screens-pair-name"
                                        value={pairName}
                                        onChange={(e) =>
                                            setPairName(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="pair-orientation">
                                        Orientation (optional)
                                    </Label>
                                    <select
                                        id="pair-orientation"
                                        className={selectClassName}
                                        value={pairOrientation}
                                        onChange={(e) =>
                                            setPairOrientation(e.target.value)
                                        }
                                        data-test="screens-pair-orientation"
                                    >
                                        <option value="">Not set</option>
                                        <option value="landscape">
                                            Landscape
                                        </option>
                                        <option value="portrait">
                                            Portrait
                                        </option>
                                    </select>
                                </div>
                                {locations.length > 0 ? (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="pair-location">
                                            Location
                                            {requireLocation
                                                ? ''
                                                : ' (optional)'}
                                        </Label>
                                        <select
                                            id="pair-location"
                                            className={selectClassName}
                                            value={pairLocationId}
                                            onChange={(e) =>
                                                setPairLocationId(
                                                    e.target.value,
                                                )
                                            }
                                            data-test="screens-pair-location"
                                            required={requireLocation}
                                        >
                                            <option value="">
                                                {requireLocation
                                                    ? 'Select a location'
                                                    : 'No location'}
                                            </option>
                                            {locations.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={String(location.id)}
                                                >
                                                    {location.name}
                                                    {location.city
                                                        ? ` · ${location.city}`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ) : null}
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAddOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    data-test="screens-pair-confirm"
                                    disabled={
                                        pairing ||
                                        !pairCode.trim() ||
                                        !pairName.trim() ||
                                        (requireLocation && !pairLocationId)
                                    }
                                    onClick={handlePair}
                                >
                                    {pairing ? <Spinner /> : null}
                                    Confirm Pair
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={renameScreen !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRenameScreen(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Rename screen</DialogTitle>
                    </DialogHeader>
                    <Input
                        data-test="screen-rename-input"
                        value={renameValue}
                        onChange={(e) => setRenameValue(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                handleRename();
                            }
                        }}
                        aria-label="TV name"
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRenameScreen(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="screen-rename-confirm"
                            onClick={handleRename}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <PublishContentDialog
                open={publishScreen !== null}
                designs={publishedDesigns}
                playlists={publishedPlaylists}
                designId={publishDesignId}
                playlistId={publishPlaylistId}
                kind={publishKind}
                onKindChange={setPublishKind}
                onDesignIdChange={setPublishDesignId}
                onPlaylistIdChange={setPublishPlaylistId}
                onClose={() => {
                    setPublishScreen(null);
                    setPublishDesignId('');
                    setPublishPlaylistId('');
                    setPublishKind('screen_design');
                }}
                onConfirm={handlePublish}
                title="Change Content"
                description={`What do you want to show on ${publishScreen?.name ?? `this ${ProductLabels.displaySingular}`}?`}
                currentName={
                    publishScreen?.now_showing?.content_name ??
                    publishScreen?.current_design_name
                }
                scheduleControlled={
                    publishScreen?.now_showing?.content_source === 'schedule'
                }
                scheduleName={publishScreen?.now_showing?.schedule_name}
            />

            <PublishContentDialog
                open={bulkPublishOpen}
                designs={publishedDesigns}
                designId={bulkDesignId}
                onDesignIdChange={setBulkDesignId}
                onClose={() => {
                    setBulkPublishOpen(false);
                    setBulkDesignId('');
                }}
                onConfirm={handleBulkPublish}
                title={ProductLabels.publishToSelected}
                description={`Choose a published design for ${selected.length} ${displayLabel(selected.length)}.`}
                confirmTestId="screens-bulk-publish-confirm"
                selectTestId="screens-bulk-publish-design"
            />

            <Dialog
                open={unpairScreen !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUnpairScreen(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Unpair “{unpairScreen?.name}”?
                        </DialogTitle>
                        <DialogDescription>
                            The device will need a new pairing code to
                            reconnect. Current content stays on the screen
                            record until republished.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setUnpairScreen(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="screen-unpair-confirm"
                            onClick={handleUnpair}
                        >
                            Unpair
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <TvContentPreviewDialog
                screenId={previewScreen?.id ?? null}
                screenName={previewScreen?.name}
                onClose={() => setPreviewScreen(null)}
            />
        </>
    );
}

function RowActions({
    screen,
    busy,
    canManage,
    canPublish,
    testId,
    onRename,
    onPublish,
    onStatus,
    onUnpair,
}: {
    screen: ScreenListItem;
    busy: boolean;
    canManage: boolean;
    canPublish: boolean;
    testId: string;
    onRename: () => void;
    onPublish: () => void;
    onStatus: (screen: ScreenListItem, next: 'active' | 'inactive') => void;
    onUnpair: () => void;
}) {
    const isActive = screen.operational_status === 'active';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-8 shrink-0"
                    data-test={testId}
                    disabled={busy}
                    aria-label={`Actions for ${screen.name}`}
                >
                    {busy ? <Spinner /> : <MoreHorizontal className="size-4" />}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={screenRoutes.show.url(screen.id)}>View</Link>
                </DropdownMenuItem>
                {canManage ? (
                    <DropdownMenuItem onClick={onRename}>
                        Rename
                    </DropdownMenuItem>
                ) : null}
                {canPublish ? (
                    <DropdownMenuItem onClick={onPublish}>
                        <Upload className="size-3.5" />
                        Publish Content
                    </DropdownMenuItem>
                ) : null}
                {canManage ? (
                    <>
                        <DropdownMenuSeparator />
                        {isActive ? (
                            <DropdownMenuItem
                                onClick={() => onStatus(screen, 'inactive')}
                            >
                                <PowerOff className="size-3.5" />
                                Deactivate
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onClick={() => onStatus(screen, 'active')}
                            >
                                <Power className="size-3.5" />
                                Activate
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={onUnpair}
                        >
                            <Unplug className="size-3.5" />
                            Unpair
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function PublishContentDialog({
    open,
    designs,
    playlists = [],
    designId,
    playlistId = '',
    kind = 'screen_design',
    onKindChange,
    onDesignIdChange,
    onPlaylistIdChange,
    onClose,
    onConfirm,
    title,
    description,
    currentName,
    scheduleControlled = false,
    scheduleName,
    confirmTestId = 'screen-publish-confirm',
    selectTestId = 'screen-publish-design',
}: {
    open: boolean;
    designs: PublishedDesignOption[];
    playlists?: PublishedDesignOption[];
    designId: string;
    playlistId?: string;
    kind?: 'screen_design' | 'playlist' | 'schedule';
    onKindChange?: (kind: 'screen_design' | 'playlist' | 'schedule') => void;
    onDesignIdChange: (id: string) => void;
    onPlaylistIdChange?: (id: string) => void;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    description: string;
    currentName?: string | null;
    scheduleControlled?: boolean;
    scheduleName?: string | null;
    confirmTestId?: string;
    selectTestId?: string;
}) {
    const showKinds = onKindChange != null;
    const canConfirm =
        kind === 'schedule' ||
        (kind === 'playlist' ? Boolean(playlistId) : Boolean(designId));

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {scheduleControlled ? (
                    <div
                        className="border-border bg-muted/40 rounded-lg border p-3 text-sm"
                        data-test="change-content-schedule-note"
                    >
                        <p className="font-medium">Controlled by Schedule</p>
                        <p className="text-muted-foreground mt-1">
                            {scheduleName ?? 'An active schedule'} is driving
                            this TV right now. Publishing sets the always-on
                            fallback — scheduled content still wins while its
                            window matches.
                        </p>
                    </div>
                ) : null}
                {currentName ? (
                    <p className="text-muted-foreground text-sm">
                        Current:{' '}
                        <span className="text-foreground font-medium">
                            {currentName}
                        </span>
                    </p>
                ) : null}
                {showKinds ? (
                    <div className="space-y-2" data-test="change-content-kind">
                        {(
                            [
                                ['screen_design', 'Screen'],
                                ['playlist', 'Playlist'],
                                ['schedule', 'Use Schedule'],
                            ] as const
                        ).map(([value, label]) => (
                            <label
                                key={value}
                                className="border-border hover:bg-muted/40 flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            >
                                <input
                                    type="radio"
                                    name="change-content-kind"
                                    value={value}
                                    checked={kind === value}
                                    onChange={() => onKindChange?.(value)}
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                ) : null}
                {kind === 'screen_design' ? (
                    designs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No published Screens yet. Publish a Screen first.
                        </p>
                    ) : (
                        <div className="space-y-1.5">
                            <Label htmlFor={selectTestId}>Replace with</Label>
                            <select
                                id={selectTestId}
                                className={selectClassName}
                                value={designId}
                                onChange={(e) =>
                                    onDesignIdChange(e.target.value)
                                }
                                data-test={selectTestId}
                            >
                                <option value="">Select a Screen…</option>
                                {designs.map((design) => (
                                    <option key={design.id} value={design.id}>
                                        {design.name} ({design.orientation})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )
                ) : null}
                {kind === 'playlist' ? (
                    playlists.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No published Playlists yet.
                        </p>
                    ) : (
                        <div className="space-y-1.5">
                            <Label htmlFor="screen-publish-playlist">
                                Replace with
                            </Label>
                            <select
                                id="screen-publish-playlist"
                                className={selectClassName}
                                value={playlistId}
                                onChange={(e) =>
                                    onPlaylistIdChange?.(e.target.value)
                                }
                                data-test="screen-publish-playlist"
                            >
                                <option value="">Select a Playlist…</option>
                                {playlists.map((playlist) => (
                                    <option
                                        key={playlist.id}
                                        value={playlist.id}
                                    >
                                        {playlist.name} ({playlist.orientation})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )
                ) : null}
                {kind === 'schedule' ? (
                    <p className="text-muted-foreground text-sm">
                        Continue to Schedules to pin a published Playlist to
                        this TV on a timetable. Schedules outrank direct
                        publishes while they match.
                    </p>
                ) : null}
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        data-test={confirmTestId}
                        disabled={!canConfirm}
                        onClick={onConfirm}
                    >
                        {kind === 'schedule'
                            ? 'Open Schedules'
                            : 'Publish to TV'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ScreensIndex.layout = {
    breadcrumbs: [{ title: ProductLabels.pairedNav, href: screensIndex.url() }],
};
