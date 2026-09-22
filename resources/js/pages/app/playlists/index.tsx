import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    Calendar,
    ChevronRight,
    Copy,
    Eye,
    ListVideo,
    Monitor,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AiAgentDialog from '@/components/ai/ai-agent-dialog';
import { toLayoutMediaMap } from '@/components/playlists/player-items';
import { PlaylistPreviewDialog } from '@/components/playlists/playlist-preview-dialog';
import { LayoutRenderer } from '@/components/rendering/layout-renderer';
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
import { Spinner } from '@/components/ui/spinner';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels, displayLabel } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { playlists as playlistsIndex } from '@/routes/app';
import playlistRoutes from '@/routes/app/playlists';
import {
    isLayoutSchema,
    normalizeLayoutSchema,
    type LayoutSchema,
} from '@/types/layout-schema';
import type {
    PlaylistListItem,
    PlaylistPreviewItem,
    PlaylistsIndexProps,
} from '@/types/playlist';

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
] as const;

const SORT_OPTIONS = [
    { value: 'updated_desc', label: 'Recently updated' },
    { value: 'created_desc', label: 'Recently created' },
    { value: 'name_asc', label: 'Name (A–Z)' },
    { value: 'name_desc', label: 'Name (Z–A)' },
] as const;

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function statusBadgeVariant(
    status: string,
): 'success' | 'warning' | 'neutral' | 'secondary' {
    switch (status) {
        case 'published':
            return 'success';
        case 'draft':
            return 'warning';
        case 'archived':
            return 'neutral';
        default:
            return 'secondary';
    }
}

function itemSchema(item: PlaylistPreviewItem): LayoutSchema | null {
    return isLayoutSchema(item.schema)
        ? normalizeLayoutSchema(item.schema)
        : null;
}

function PlaylistSequenceThumb({
    item,
    isPortrait,
    mediaMap,
}: {
    item: PlaylistPreviewItem;
    isPortrait: boolean;
    mediaMap: ReturnType<typeof toLayoutMediaMap>;
}) {
    const schema = itemSchema(item);

    return (
        <div
            className="flex shrink-0 flex-col items-center gap-1"
            data-test="playlist-seq-thumb"
        >
            <div
                className={cn(
                    'bg-muted relative overflow-hidden rounded-lg',
                    isPortrait ? 'h-16 w-10' : 'h-12 w-20',
                )}
            >
                {schema ? (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center p-0.5">
                        <LayoutRenderer
                            schema={schema}
                            mode="preview"
                            fitWidth={isPortrait ? 36 : 76}
                            fitHeight={isPortrait ? 60 : 44}
                            mediaMap={mediaMap}
                            className="rounded-sm shadow-sm"
                        />
                    </div>
                ) : (
                    <div className="text-muted-foreground absolute inset-0 flex items-center justify-center text-[9px]">
                        —
                    </div>
                )}
            </div>
            <p className="text-muted-foreground w-20 truncate text-center font-mono text-[9px]">
                {item.name ?? 'Design'}
            </p>
            <p
                className="text-muted-foreground font-mono text-[9px]"
                data-test="playlist-seq-duration"
            >
                {formatDuration(
                    item.effective_duration_seconds ??
                        item.duration_seconds *
                            Math.max(1, item.loop_count ?? 1),
                )}
            </p>
        </div>
    );
}

export default function PlaylistsIndex({
    playlists: paginated,
    filters,
    can_manage: canManage,
    can_publish_to_screens: canPublishToScreens = false,
    screens: workspaceScreens = [],
    media_map: mediaMap,
}: PlaylistsIndexProps) {
    const { ai } = usePage().props;
    const [searchInput, setSearchInput] = useState(filters.q);
    const [createOpen, setCreateOpen] = useState(false);
    const [aiAgentOpen, setAiAgentOpen] = useState(false);
    const [createName, setCreateName] = useState('Untitled Playlist');
    const [createDescription, setCreateDescription] = useState('');
    const [creating, setCreating] = useState(false);
    const [previewPlaylist, setPreviewPlaylist] =
        useState<PlaylistListItem | null>(null);
    const [deletePlaylist, setDeletePlaylist] =
        useState<PlaylistListItem | null>(null);
    const [publishToScreensPlaylist, setPublishToScreensPlaylist] =
        useState<PlaylistListItem | null>(null);
    const [selectedScreenIds, setSelectedScreenIds] = useState<number[]>([]);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);

    const layoutMediaMap = toLayoutMediaMap(mediaMap);
    const visibleIds = useMemo(
        () => paginated.data.map((playlist) => playlist.id),
        [paginated.data],
    );

    useEffect(() => {
        setSelected((prev) => prev.filter((id) => visibleIds.includes(id)));
    }, [visibleIds]);

    function toggleSelected(id: number, checked: boolean) {
        setSelected((prev) =>
            checked
                ? [...new Set([...prev, id])]
                : prev.filter((x) => x !== id),
        );
    }

    const allSelected =
        paginated.data.length > 0 && selected.length === paginated.data.length;

    function handleBulkDelete() {
        if (selected.length === 0) {
            return;
        }
        setBulkDeleting(true);
        router.post(
            playlistRoutes.bulk_destroy.url(),
            { ids: selected },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBulkDeleting(false);
                    setBulkDeleteOpen(false);
                    setSelected([]);
                },
            },
        );
    }

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

    function navigate(
        patch: Partial<{
            q: string;
            status: string;
            orientation: string;
            sort: string;
            page: number;
        }>,
    ) {
        router.get(
            playlistsIndex.url(),
            {
                q: patch.q ?? filters.q,
                status: patch.status ?? filters.status,
                orientation: patch.orientation ?? filters.orientation,
                sort: patch.sort ?? filters.sort,
                ...(patch.page ? { page: patch.page } : {}),
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    }

    function handleCreate() {
        setCreating(true);
        router.post(
            playlistRoutes.store.url(),
            {
                name: createName.trim() || 'Untitled Playlist',
                description: createDescription.trim() || null,
            },
            {
                onFinish: () => setCreating(false),
            },
        );
    }

    function handleDuplicate(playlist: PlaylistListItem) {
        setBusyId(playlist.id);
        router.post(
            playlistRoutes.duplicate.url(playlist.id),
            {},
            { onFinish: () => setBusyId(null) },
        );
    }

    function handlePublish(playlist: PlaylistListItem) {
        setBusyId(playlist.id);
        router.post(
            playlistRoutes.publish.url(playlist.id),
            {},
            { preserveScroll: true, onFinish: () => setBusyId(null) },
        );
    }

    function handleArchive(playlist: PlaylistListItem) {
        setBusyId(playlist.id);
        router.post(
            playlistRoutes.archive.url(playlist.id),
            {},
            { preserveScroll: true, onFinish: () => setBusyId(null) },
        );
    }

    function handleDelete() {
        if (!deletePlaylist) {
            return;
        }
        setBusyId(deletePlaylist.id);
        router.delete(playlistRoutes.destroy.url(deletePlaylist.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusyId(null);
                setDeletePlaylist(null);
            },
        });
    }

    function toggleScreenId(id: number) {
        setSelectedScreenIds((prev) =>
            prev.includes(id)
                ? prev.filter((screenId) => screenId !== id)
                : [...prev, id],
        );
    }

    function openAssign(playlist: PlaylistListItem) {
        setPublishToScreensPlaylist(playlist);
        setSelectedScreenIds([]);
    }

    function handlePublishToScreens() {
        if (!publishToScreensPlaylist || selectedScreenIds.length === 0) {
            return;
        }
        setBusyId(publishToScreensPlaylist.id);
        router.post(
            playlistRoutes.publish_to_screens.url(publishToScreensPlaylist.id),
            { screen_ids: selectedScreenIds },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setPublishToScreensPlaylist(null);
                    setSelectedScreenIds([]);
                },
            },
        );
    }

    return (
        <>
            <Head title="Playlists" />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Playlists
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Rotate multiple {ProductLabels.screenDesignPlural}{' '}
                            on your {ProductLabels.displayPlural}
                        </p>
                    </div>
                    {canManage ? (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                data-test="playlists-create-ai"
                                onClick={() => setAiAgentOpen(true)}
                            >
                                <Sparkles className="size-4" />
                                Create with AI
                            </Button>
                            <Button
                                type="button"
                                data-test="playlists-create"
                                onClick={() => setCreateOpen(true)}
                            >
                                <Plus className="size-4" />
                                Create Playlist
                            </Button>
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="playlists-search"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder="Search playlists..."
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <select
                            className={cn(selectClassName, 'w-auto min-w-36')}
                            value={filters.orientation}
                            onChange={(e) =>
                                navigate({ orientation: e.target.value })
                            }
                            aria-label="Orientation"
                            data-test="playlists-orientation"
                        >
                            <option value="all">All orientations</option>
                            <option value="landscape">Landscape</option>
                            <option value="portrait">Portrait</option>
                        </select>
                        <select
                            className={cn(selectClassName, 'w-auto min-w-40')}
                            value={filters.sort}
                            onChange={(e) => navigate({ sort: e.target.value })}
                            aria-label="Sort playlists"
                            data-test="playlists-sort"
                        >
                            {SORT_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <div
                            className="bg-secondary flex gap-1 rounded-lg p-1"
                            data-test="playlists-status-filters"
                        >
                            {STATUS_FILTERS.map((f) => (
                                <button
                                    key={f.value}
                                    type="button"
                                    onClick={() =>
                                        navigate({ status: f.value })
                                    }
                                    aria-pressed={filters.status === f.value}
                                    className={cn(
                                        'h-7 rounded-md px-3 text-xs font-medium transition-colors',
                                        filters.status === f.value
                                            ? 'bg-card text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {f.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {selected.length > 0 && canManage ? (
                    <div
                        data-test="playlists-bulk-bar"
                        className="border-border bg-card sticky top-2 z-10 flex flex-wrap items-center gap-2 rounded-lg border p-3"
                    >
                        <Checkbox
                            checked={allSelected}
                            onCheckedChange={(checked) => {
                                if (checked) {
                                    setSelected(visibleIds);
                                } else {
                                    setSelected([]);
                                }
                            }}
                            aria-label="Select all playlists"
                            data-test="playlists-select-all"
                        />
                        <span className="text-sm font-medium">
                            {selected.length} selected
                        </span>
                        <div className="flex flex-wrap gap-2 sm:ml-auto">
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                data-test="playlists-bulk-delete"
                                onClick={() => setBulkDeleteOpen(true)}
                            >
                                <Trash2 className="size-3.5" />
                                Delete
                            </Button>
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

                {paginated.data.length === 0 ? (
                    <EmptyState
                        icon={ListVideo}
                        title="No playlists yet"
                        description={
                            canManage
                                ? `Create a playlist to rotate multiple ${ProductLabels.screenDesignPlural} in sequence.`
                                : 'No playlists match your filters.'
                        }
                        action={
                            canManage ? (
                                <Button
                                    type="button"
                                    data-test="playlists-empty-create"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    <Plus className="size-4" />
                                    Create Playlist
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-4" data-test="playlists-list">
                        {paginated.data.map((playlist) => {
                            const isPortrait =
                                playlist.orientation === 'portrait';
                            const busy = busyId === playlist.id;
                            const previewItems = playlist.preview_items ?? [];
                            const remaining =
                                Math.max(
                                    playlist.active_item_count ||
                                        playlist.item_count,
                                    0,
                                ) - previewItems.length;

                            return (
                                <div
                                    key={playlist.id}
                                    data-test={`playlist-card-${playlist.id}`}
                                    data-status={playlist.status}
                                    data-orientation={
                                        playlist.orientation ?? 'none'
                                    }
                                    className={cn(
                                        'border-border bg-card hover:border-primary/30 overflow-hidden rounded-xl border transition-colors',
                                        selected.includes(playlist.id) &&
                                            'border-primary/50 ring-primary/20 ring-2',
                                    )}
                                >
                                    <div className="border-border flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                        <div className="flex min-w-0 flex-1 items-start gap-3">
                                            {canManage ? (
                                                <Checkbox
                                                    checked={selected.includes(
                                                        playlist.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelected(
                                                            playlist.id,
                                                            Boolean(checked),
                                                        )
                                                    }
                                                    aria-label={`Select ${playlist.name}`}
                                                    data-test={`playlist-select-${playlist.id}`}
                                                    className="mt-1"
                                                />
                                            ) : null}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="font-display truncate text-base font-semibold">
                                                        {playlist.name}
                                                    </h2>
                                                    <Badge
                                                        variant={statusBadgeVariant(
                                                            playlist.status,
                                                        )}
                                                    >
                                                        {playlist.status_label}
                                                    </Badge>
                                                    {playlist.status ===
                                                        'published' &&
                                                    playlist.has_unpublished_changes ? (
                                                        <Badge variant="warning">
                                                            Unpublished changes
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-xs">
                                                    <span
                                                        data-test={`playlist-items-${playlist.id}`}
                                                    >
                                                        {playlist.item_count}{' '}
                                                        {playlist.item_count ===
                                                        1
                                                            ? ProductLabels.screenDesignSingular
                                                            : ProductLabels.screenDesignPlural}
                                                    </span>
                                                    <span
                                                        data-test={`playlist-duration-${playlist.id}`}
                                                    >
                                                        {formatDuration(
                                                            playlist.total_duration_seconds,
                                                        )}{' '}
                                                        runtime
                                                    </span>
                                                    <span
                                                        className="inline-flex items-center gap-1"
                                                        data-test={`playlist-tvs-${playlist.id}`}
                                                    >
                                                        <Monitor className="size-3" />
                                                        {
                                                            playlist.assigned_tv_count
                                                        }{' '}
                                                        {displayLabel(
                                                            playlist.assigned_tv_count,
                                                        )}
                                                    </span>
                                                    <span
                                                        className="inline-flex items-center gap-1"
                                                        data-test={`playlist-schedules-${playlist.id}`}
                                                    >
                                                        <Calendar className="size-3" />
                                                        {
                                                            playlist.schedule_count
                                                        }{' '}
                                                        schedule
                                                        {playlist.schedule_count ===
                                                        1
                                                            ? ''
                                                            : 's'}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                            playlist.status ===
                                                                'published'
                                                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                                : 'bg-zinc-500/15 text-zinc-500',
                                                        )}
                                                        data-test={`playlist-active-${playlist.id}`}
                                                    >
                                                        {playlist.status ===
                                                        'published'
                                                            ? 'Active'
                                                            : playlist.status ===
                                                                'archived'
                                                              ? 'Inactive'
                                                              : 'Draft'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 flex-wrap items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                data-test={`playlist-preview-${playlist.id}`}
                                                onClick={() =>
                                                    setPreviewPlaylist(playlist)
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Preview
                                            </Button>
                                            {canPublishToScreens &&
                                            playlist.status === 'published' ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    data-test={`playlist-publish-to-screens-${playlist.id}`}
                                                    onClick={() =>
                                                        openAssign(playlist)
                                                    }
                                                >
                                                    <Monitor className="size-3.5" />
                                                    Assign
                                                </Button>
                                            ) : null}
                                            {canManage ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        data-test={`playlist-edit-${playlist.id}`}
                                                        asChild
                                                    >
                                                        <Link
                                                            href={playlistRoutes.edit.url(
                                                                playlist.id,
                                                            )}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8"
                                                                aria-label={`Actions for ${playlist.name}`}
                                                                data-test={`playlist-menu-${playlist.id}`}
                                                                disabled={busy}
                                                            >
                                                                {busy ? (
                                                                    <Spinner />
                                                                ) : (
                                                                    <MoreHorizontal className="size-4" />
                                                                )}
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    handleDuplicate(
                                                                        playlist,
                                                                    )
                                                                }
                                                            >
                                                                <Copy className="size-3.5" />
                                                                Duplicate
                                                            </DropdownMenuItem>
                                                            {playlist.status !==
                                                            'published' ? (
                                                                <DropdownMenuItem
                                                                    data-test={`playlist-publish-${playlist.id}`}
                                                                    onClick={() =>
                                                                        handlePublish(
                                                                            playlist,
                                                                        )
                                                                    }
                                                                >
                                                                    <Upload className="size-3.5" />
                                                                    Publish
                                                                </DropdownMenuItem>
                                                            ) : playlist.has_unpublished_changes ? (
                                                                <DropdownMenuItem
                                                                    data-test={`playlist-publish-${playlist.id}`}
                                                                    onClick={() =>
                                                                        handlePublish(
                                                                            playlist,
                                                                        )
                                                                    }
                                                                >
                                                                    <Upload className="size-3.5" />
                                                                    Publish
                                                                    version
                                                                </DropdownMenuItem>
                                                            ) : null}
                                                            {playlist.status !==
                                                            'archived' ? (
                                                                <DropdownMenuItem
                                                                    data-test={`playlist-archive-${playlist.id}`}
                                                                    onClick={() =>
                                                                        handleArchive(
                                                                            playlist,
                                                                        )
                                                                    }
                                                                >
                                                                    <Archive className="size-3.5" />
                                                                    Archive
                                                                </DropdownMenuItem>
                                                            ) : null}
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    setDeletePlaylist(
                                                                        playlist,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </>
                                            ) : null}
                                        </div>
                                    </div>

                                    {previewItems.length > 0 ? (
                                        <div
                                            className="flex gap-2 overflow-x-auto px-4 py-3 sm:px-5"
                                            data-test={`playlist-sequence-${playlist.id}`}
                                        >
                                            {previewItems.map((item, idx) => (
                                                <div
                                                    key={`${item.screen_design_id}-${idx}`}
                                                    className="flex shrink-0 items-center gap-2"
                                                >
                                                    <PlaylistSequenceThumb
                                                        item={item}
                                                        isPortrait={isPortrait}
                                                        mediaMap={
                                                            layoutMediaMap
                                                        }
                                                    />
                                                    {idx <
                                                        previewItems.length -
                                                            1 ||
                                                    remaining > 0 ? (
                                                        <ChevronRight className="text-muted-foreground size-3.5 shrink-0" />
                                                    ) : null}
                                                </div>
                                            ))}
                                            {remaining > 0 ? (
                                                <div className="text-muted-foreground flex shrink-0 items-center self-center font-mono text-[10px]">
                                                    +{remaining} more
                                                </div>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div className="px-5 py-6 text-center">
                                            <p className="text-muted-foreground text-xs">
                                                No{' '}
                                                {ProductLabels.screenDesignPlural.toLowerCase()}{' '}
                                                yet.
                                                {canManage
                                                    ? ` Click Edit to add ${ProductLabels.screenDesignPlural.toLowerCase()}.`
                                                    : ''}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {paginated.meta.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-sm">
                            Page {paginated.meta.current_page} of{' '}
                            {paginated.meta.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!paginated.links.prev}
                                onClick={() =>
                                    navigate({
                                        page: paginated.meta.current_page - 1,
                                    })
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!paginated.links.next}
                                onClick={() =>
                                    navigate({
                                        page: paginated.meta.current_page + 1,
                                    })
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent
                    className="sm:max-w-md"
                    data-test="playlists-create-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>Create Playlist</DialogTitle>
                        <DialogDescription>
                            Name your playlist, then add published{' '}
                            {ProductLabels.screenDesignPlural} in the editor.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="playlist-create-name">
                                {ProductLabels.playlistName}
                            </Label>
                            <Input
                                id="playlist-create-name"
                                data-test="playlists-create-name"
                                value={createName}
                                onChange={(e) => setCreateName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !creating) {
                                        handleCreate();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="playlist-create-description">
                                Description (optional)
                            </Label>
                            <Input
                                id="playlist-create-description"
                                data-test="playlists-create-description"
                                value={createDescription}
                                onChange={(e) =>
                                    setCreateDescription(e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCreateOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={creating}
                            data-test="playlists-create-submit"
                            onClick={handleCreate}
                        >
                            {creating ? <Spinner /> : null}
                            Create Playlist
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <PlaylistPreviewDialog
                playlistId={previewPlaylist?.id ?? null}
                playlistName={previewPlaylist?.name}
                onClose={() => setPreviewPlaylist(null)}
            />

            <Dialog
                open={deletePlaylist !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletePlaylist(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete “{deletePlaylist?.name}”?
                        </DialogTitle>
                        <DialogDescription>
                            This permanently deletes the playlist. The{' '}
                            {ProductLabels.screenDesignPlural} it uses are not
                            affected.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeletePlaylist(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="playlist-delete-confirm"
                            onClick={handleDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={bulkDeleteOpen}
                onOpenChange={(open) => {
                    if (!open && !bulkDeleting) {
                        setBulkDeleteOpen(false);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete {selected.length}{' '}
                            {selected.length === 1 ? 'Playlist' : 'Playlists'}?
                        </DialogTitle>
                        <DialogDescription>
                            This permanently deletes the selected playlists.
                            Playlists that are live or used by schedules will be
                            skipped and reported.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={bulkDeleting}
                            onClick={() => setBulkDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="playlists-bulk-delete-confirm"
                            disabled={bulkDeleting}
                            onClick={handleBulkDelete}
                        >
                            {bulkDeleting ? <Spinner /> : null}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={publishToScreensPlaylist !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPublishToScreensPlaylist(null);
                        setSelectedScreenIds([]);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{ProductLabels.publishTo}</DialogTitle>
                        <DialogDescription>
                            Deploy “{publishToScreensPlaylist?.name}” to one or
                            more paired {ProductLabels.displayPlural}.
                        </DialogDescription>
                    </DialogHeader>
                    {workspaceScreens.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No {ProductLabels.displayPlural} in this business
                            yet. {ProductLabels.pairAction} first.
                        </p>
                    ) : (
                        <div
                            className="max-h-64 space-y-2 overflow-y-auto"
                            data-test="playlist-publish-screens-list"
                        >
                            {workspaceScreens.map((screen) => {
                                const checked = selectedScreenIds.includes(
                                    screen.id,
                                );
                                return (
                                    <label
                                        key={screen.id}
                                        className="hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            data-test={`playlist-publish-screen-option-${screen.id}`}
                                            onCheckedChange={() =>
                                                toggleScreenId(screen.id)
                                            }
                                            aria-label={`Select ${screen.name}`}
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium">
                                                {screen.name}
                                            </span>
                                            <span className="text-muted-foreground block text-xs capitalize">
                                                {screen.orientation ??
                                                    'No orientation'}{' '}
                                                · {screen.operational_status}
                                            </span>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setPublishToScreensPlaylist(null);
                                setSelectedScreenIds([]);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="playlist-publish-to-screens-confirm"
                            disabled={selectedScreenIds.length === 0}
                            onClick={handlePublishToScreens}
                        >
                            {ProductLabels.publishToSelected}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AiAgentDialog
                open={aiAgentOpen}
                onOpenChange={setAiAgentOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                preferredIntent="playlist"
                title="Create playlist with AI"
            />
        </>
    );
}

PlaylistsIndex.layout = {
    breadcrumbs: [{ title: 'Playlists', href: '/app/playlists' }],
};
