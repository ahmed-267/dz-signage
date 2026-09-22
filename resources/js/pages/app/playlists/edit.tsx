import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    Clock,
    Copy,
    Eye,
    GripVertical,
    Layers,
    Plus,
    Repeat,
    Save,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    resolveItemSchema,
    toLayoutMediaMap,
} from '@/components/playlists/player-items';
import {
    PlaylistPreviewPlayer,
    type PlaylistPlayerItem,
} from '@/components/playlists/playlist-preview-player';
import {
    LayoutRenderer,
    type LayoutMediaMap,
} from '@/components/rendering/layout-renderer';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { playlists as playlistsIndex } from '@/routes/app';
import playlistRoutes from '@/routes/app/playlists';
import type {
    PlaylistDesignOption,
    PlaylistEditProps,
    PlaylistItem,
    PlaylistTransition,
    PlaylistTransitionSpeed,
    PublishedDesignPage,
} from '@/types/playlist';

type EditorItem = {
    /** Stable client key — survives reordering and unsaved items. */
    key: string;
    screen_design_id: number;
    screen_design_version_id: number | null;
    design_name: string;
    orientation: string | null;
    duration_seconds: number;
    loop_count: number;
    transition: PlaylistTransition;
    transition_speed: PlaylistTransitionSpeed;
    is_active: boolean;
    schema: PlaylistItem['schema'];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

let clientKeyCounter = 0;

function nextClientKey(): string {
    clientKeyCounter += 1;
    return `item-${clientKeyCounter}`;
}

function toEditorItems(items: PlaylistItem[]): EditorItem[] {
    return items.map((item) => ({
        key: `saved-${item.id}`,
        screen_design_id: item.screen_design_id,
        screen_design_version_id: item.screen_design_version_id,
        design_name: item.name ?? `Design ${item.screen_design_id}`,
        orientation: item.orientation,
        duration_seconds: item.duration_seconds,
        loop_count: Math.max(1, item.loop_count ?? 1),
        transition: item.transition,
        transition_speed: item.transition_speed,
        is_active: item.is_active,
        schema: item.schema,
    }));
}

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

function snapshotOf(
    name: string,
    description: string,
    items: EditorItem[],
): string {
    return JSON.stringify({
        name,
        description,
        items: items.map((item) => ({
            screen_design_id: item.screen_design_id,
            screen_design_version_id: item.screen_design_version_id,
            duration_seconds: item.duration_seconds,
            loop_count: item.loop_count,
            transition: item.transition,
            transition_speed: item.transition_speed,
            is_active: item.is_active,
        })),
    });
}

export default function PlaylistEdit({
    playlist,
    config,
    can_edit: canEdit,
    published_designs: publishedDesigns,
    media_map: mediaMap,
}: PlaylistEditProps) {
    const [name, setName] = useState(playlist.name);
    const [description, setDescription] = useState(playlist.description ?? '');
    const [items, setItems] = useState<EditorItem[]>(() =>
        toEditorItems(playlist.items),
    );
    const [savedSnapshot, setSavedSnapshot] = useState(() =>
        snapshotOf(
            playlist.name,
            playlist.description ?? '',
            toEditorItems(playlist.items),
        ),
    );

    const [saving, setSaving] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    /** Set when the picker replaces an existing item instead of adding one. */
    const [replaceKey, setReplaceKey] = useState<string | null>(null);
    const [pickerSearch, setPickerSearch] = useState('');
    const [pickerOrientation, setPickerOrientation] = useState('all');
    const [designs, setDesigns] = useState<PlaylistDesignOption[]>(
        publishedDesigns.data,
    );
    const [designsLoading, setDesignsLoading] = useState(false);
    const [mediaMapState, setMediaMapState] = useState<LayoutMediaMap>(() => ({
        ...toLayoutMediaMap(mediaMap),
        ...toLayoutMediaMap(publishedDesigns.media_map),
    }));
    const [dragKey, setDragKey] = useState<string | null>(null);
    const [dragOverKey, setDragOverKey] = useState<string | null>(null);
    const [draggableKey, setDraggableKey] = useState<string | null>(null);

    const handleRefs = useRef(new Map<string, HTMLButtonElement | null>());

    const dirty = useMemo(
        () => snapshotOf(name, description, items) !== savedSnapshot,
        [description, items, name, savedSnapshot],
    );

    const activeItems = useMemo(
        () => items.filter((item) => item.is_active),
        [items],
    );

    const totalSeconds = useMemo(
        () =>
            activeItems.reduce(
                (total, item) =>
                    total +
                    item.duration_seconds * Math.max(1, item.loop_count),
                0,
            ),
        [activeItems],
    );

    /**
     * A playlist is single-orientation: the first item fixes it and the
     * backend rejects mixed items, so the editor warns before saving.
     */
    const lockedOrientation =
        playlist.orientation ?? items[0]?.orientation ?? null;

    const orientationMismatch = items.some(
        (item) =>
            lockedOrientation !== null &&
            item.orientation !== null &&
            item.orientation !== lockedOrientation,
    );

    const playerItems = useMemo((): PlaylistPlayerItem[] => {
        const result: PlaylistPlayerItem[] = [];
        for (const item of items) {
            const schema = resolveItemSchema(item.schema);
            if (!schema) {
                continue;
            }
            result.push({
                key: item.key,
                name: item.design_name,
                schema,
                duration_seconds: item.duration_seconds,
                loop_count: Math.max(1, item.loop_count),
                transition: item.transition,
                transition_speed: item.transition_speed,
                is_active: item.is_active,
            });
        }
        return result;
    }, [items]);

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        const remove = router.on('before', (event) => {
            if (!dirty) {
                return;
            }

            // Save / publish / delete must not trigger the leave prompt.
            if (event.detail.visit.method !== 'get') {
                return;
            }

            if (
                !window.confirm(
                    'You have unsaved changes. Leave this page anyway?',
                )
            ) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            remove();
        };
    }, [dirty]);

    // Design picker search runs server-side — the library can outgrow a page.
    useEffect(() => {
        if (!pickerOpen) {
            return;
        }

        const controller = new AbortController();
        const handle = window.setTimeout(() => {
            setDesignsLoading(true);
            void (async () => {
                try {
                    const response = await fetch(
                        playlistRoutes.published_designs.url({
                            query: {
                                q: pickerSearch.trim() || undefined,
                                orientation:
                                    pickerOrientation === 'all'
                                        ? undefined
                                        : pickerOrientation,
                            },
                        }),
                        {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                            signal: controller.signal,
                        },
                    );

                    if (!response.ok) {
                        return;
                    }

                    const page = (await response.json()) as PublishedDesignPage;
                    setDesigns(page.data);
                    setMediaMapState((prev) => ({
                        ...prev,
                        ...toLayoutMediaMap(page.media_map),
                    }));
                } catch {
                    // Keep the current list on transient errors.
                } finally {
                    if (!controller.signal.aborted) {
                        setDesignsLoading(false);
                    }
                }
            })();
        }, 300);

        return () => {
            window.clearTimeout(handle);
            controller.abort();
        };
    }, [pickerOpen, pickerOrientation, pickerSearch]);

    function updateItem(key: string, patch: Partial<EditorItem>) {
        setItems((prev) =>
            prev.map((item) =>
                item.key === key ? { ...item, ...patch } : item,
            ),
        );
    }

    function moveItem(from: number, to: number) {
        setItems((prev) => {
            if (
                from === to ||
                from < 0 ||
                to < 0 ||
                from >= prev.length ||
                to >= prev.length
            ) {
                return prev;
            }
            const next = [...prev];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);
            return next;
        });
    }

    function moveByKey(key: string, direction: -1 | 1) {
        const index = items.findIndex((item) => item.key === key);
        if (index < 0) {
            return;
        }
        moveItem(index, index + direction);
        // Keep the handle focused so arrow keys can move the item again.
        window.requestAnimationFrame(() => {
            handleRefs.current.get(key)?.focus();
        });
    }

    function addDesign(option: PlaylistDesignOption) {
        setMediaMapState((prev) => ({
            ...prev,
            ...toLayoutMediaMap(publishedDesigns.media_map),
        }));

        if (replaceKey) {
            updateItem(replaceKey, {
                screen_design_id: option.id,
                screen_design_version_id: option.published_version_id,
                design_name: option.name,
                orientation: option.orientation,
                schema: option.schema,
            });
            setReplaceKey(null);
            setPickerOpen(false);
            return;
        }

        setItems((prev) => [
            ...prev,
            {
                key: nextClientKey(),
                screen_design_id: option.id,
                screen_design_version_id: option.published_version_id,
                design_name: option.name,
                orientation: option.orientation,
                duration_seconds: config.default_duration_seconds,
                loop_count: config.default_loop_count,
                transition: config.default_transition,
                transition_speed: config.default_transition_speed,
                is_active: true,
                schema: option.schema,
            },
        ]);
        setPickerOpen(false);
    }

    function duplicateItem(key: string) {
        const index = items.findIndex((item) => item.key === key);
        if (index < 0) {
            return;
        }
        const source = items[index];
        setItems((prev) => [
            ...prev.slice(0, index + 1),
            { ...source, key: nextClientKey() },
            ...prev.slice(index + 1),
        ]);
    }

    function removeItem(key: string) {
        setItems((prev) => prev.filter((item) => item.key !== key));
    }

    function handleSave(onSuccess?: () => void) {
        if (!canEdit) {
            return;
        }
        setSaving(true);
        router.patch(
            playlistRoutes.update.url(playlist.id),
            {
                name: name.trim() || playlist.name,
                description: description.trim() || null,
                items: items.map((item) => ({
                    screen_design_id: item.screen_design_id,
                    screen_design_version_id: item.screen_design_version_id,
                    duration_seconds: item.duration_seconds,
                    loop_count: item.loop_count,
                    transition: item.transition,
                    transition_speed: item.transition_speed,
                    is_active: item.is_active,
                })),
            } as never,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavedSnapshot(snapshotOf(name, description, items));
                    onSuccess?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    function handlePublish() {
        if (!canEdit) {
            return;
        }

        const runPublish = () => {
            setPublishing(true);
            router.post(
                playlistRoutes.publish.url(playlist.id),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => setPublishing(false),
                },
            );
        };

        if (dirty) {
            handleSave(runPublish);
            return;
        }

        runPublish();
    }

    return (
        <>
            <Head title={`Edit · ${playlist.name}`} />
            <div
                className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 p-4 md:p-6"
                data-test="playlist-editor"
            >
                <div className="border-border bg-card flex flex-wrap items-center gap-2 rounded-xl border p-2 sm:gap-3">
                    <Button type="button" variant="ghost" size="sm" asChild>
                        <Link href={playlistsIndex.url()}>
                            <ArrowLeft className="size-4" />
                            <span className="hidden sm:inline">Back</span>
                        </Link>
                    </Button>
                    <div className="bg-border hidden h-5 w-px sm:block" />
                    <Input
                        data-test="playlist-editor-name"
                        aria-label={ProductLabels.playlistName}
                        value={name}
                        disabled={!canEdit}
                        onChange={(e) => setName(e.target.value)}
                        className="focus-visible:border-border h-8 max-w-56 border-transparent bg-transparent px-1 text-sm font-medium shadow-none focus-visible:ring-0"
                    />
                    <Badge
                        variant={statusBadgeVariant(playlist.status)}
                        data-test="playlist-editor-status"
                    >
                        {playlist.status_label}
                    </Badge>
                    {lockedOrientation ? (
                        <Badge variant="neutral" className="capitalize">
                            {lockedOrientation}
                        </Badge>
                    ) : null}
                    <span
                        className={cn(
                            'font-mono text-[10px]',
                            dirty
                                ? 'text-muted-foreground'
                                : 'text-emerald-400',
                        )}
                        data-test="playlist-save-state"
                    >
                        {saving ? (
                            'Saving…'
                        ) : dirty ? (
                            'Unsaved'
                        ) : (
                            <span className="inline-flex items-center gap-1">
                                <Check className="size-3" /> Saved
                            </span>
                        )}
                    </span>
                    <div className="ml-auto flex flex-wrap items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-test="playlist-preview"
                            onClick={() => setPreviewOpen(true)}
                        >
                            <Eye className="size-3.5" />
                            Preview
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={!canEdit || saving}
                            data-test="playlist-save"
                            onClick={() => handleSave()}
                        >
                            {saving ? (
                                <Spinner />
                            ) : (
                                <Save className="size-3.5" />
                            )}
                            Save
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                !canEdit ||
                                publishing ||
                                saving ||
                                items.length === 0
                            }
                            data-test="playlist-publish"
                            onClick={handlePublish}
                        >
                            {publishing ? (
                                <Spinner />
                            ) : (
                                <Upload className="size-3.5" />
                            )}
                            Publish
                        </Button>
                    </div>
                </div>

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div className="flex min-w-0 flex-col gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h1 className="font-display text-lg font-semibold tracking-tight">
                                    Playlist items
                                </h1>
                                <p className="text-muted-foreground text-xs">
                                    Drag the handle (or focus it and press the
                                    arrow keys) to change playback order.
                                </p>
                            </div>
                            {canEdit ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    data-test="playlist-add-design"
                                    onClick={() => {
                                        setReplaceKey(null);
                                        setPickerOpen(true);
                                    }}
                                >
                                    <Plus className="size-4" />
                                    Add Screen
                                </Button>
                            ) : null}
                        </div>

                        {orientationMismatch ? (
                            <div
                                className="border-warning/40 bg-warning/10 flex items-start gap-2 rounded-lg border px-3 py-2 text-xs"
                                data-test="playlist-orientation-warning"
                                role="status"
                            >
                                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    Every Screen in a playlist must share one
                                    orientation. Remove or replace the items
                                    that are not {lockedOrientation} before
                                    saving.
                                </span>
                            </div>
                        ) : null}

                        {items.length === 0 ? (
                            <div
                                className="border-border text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center text-sm"
                                data-test="playlist-items-empty"
                            >
                                <Layers className="size-5" aria-hidden />
                                <p>
                                    No Screens in this playlist yet. Add a
                                    published Screen to get started.
                                </p>
                                {canEdit ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        data-test="playlist-add-design-empty"
                                        onClick={() => {
                                            setReplaceKey(null);
                                            setPickerOpen(true);
                                        }}
                                    >
                                        <Plus className="size-4" />
                                        Add Screen
                                    </Button>
                                ) : null}
                            </div>
                        ) : (
                            <ol
                                className="flex flex-col gap-2"
                                data-test="playlist-items"
                            >
                                {items.map((item, index) => {
                                    const schema = resolveItemSchema(
                                        item.schema,
                                    );
                                    const isPortrait =
                                        item.orientation === 'portrait';

                                    return (
                                        <li
                                            key={item.key}
                                            data-test={`playlist-item-${index}`}
                                            data-design-name={item.design_name}
                                            data-active={
                                                item.is_active
                                                    ? 'true'
                                                    : 'false'
                                            }
                                            draggable={
                                                canEdit &&
                                                draggableKey === item.key
                                            }
                                            onDragStart={(event) => {
                                                setDragKey(item.key);
                                                event.dataTransfer.effectAllowed =
                                                    'move';
                                                event.dataTransfer.setData(
                                                    'text/plain',
                                                    item.key,
                                                );
                                            }}
                                            onDragOver={(event) => {
                                                if (!dragKey) {
                                                    return;
                                                }
                                                event.preventDefault();
                                                event.dataTransfer.dropEffect =
                                                    'move';
                                                if (dragOverKey !== item.key) {
                                                    setDragOverKey(item.key);
                                                }
                                            }}
                                            onDragLeave={() => {
                                                if (dragOverKey === item.key) {
                                                    setDragOverKey(null);
                                                }
                                            }}
                                            onDrop={(event) => {
                                                event.preventDefault();
                                                const sourceKey =
                                                    dragKey ??
                                                    event.dataTransfer.getData(
                                                        'text/plain',
                                                    );
                                                moveItem(
                                                    items.findIndex(
                                                        (candidate) =>
                                                            candidate.key ===
                                                            sourceKey,
                                                    ),
                                                    index,
                                                );
                                                setDragKey(null);
                                                setDragOverKey(null);
                                                setDraggableKey(null);
                                            }}
                                            onDragEnd={() => {
                                                setDragKey(null);
                                                setDragOverKey(null);
                                                setDraggableKey(null);
                                            }}
                                            className={cn(
                                                'border-border bg-card flex flex-col gap-3 rounded-xl border p-3 transition-colors sm:flex-row sm:items-center',
                                                dragKey === item.key &&
                                                    'opacity-60',
                                                dragOverKey === item.key &&
                                                    dragKey !== item.key &&
                                                    'border-primary',
                                                !item.is_active &&
                                                    'bg-muted/40',
                                            )}
                                        >
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    ref={(node) => {
                                                        handleRefs.current.set(
                                                            item.key,
                                                            node,
                                                        );
                                                    }}
                                                    disabled={!canEdit}
                                                    data-test={`playlist-item-handle-${index}`}
                                                    aria-label={`Reorder ${item.design_name}. Position ${index + 1} of ${items.length}. Use the arrow up and arrow down keys to move it.`}
                                                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 cursor-grab rounded-md p-1 focus-visible:ring-2 focus-visible:outline-none active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-50"
                                                    onPointerDown={() =>
                                                        setDraggableKey(
                                                            item.key,
                                                        )
                                                    }
                                                    onPointerUp={() =>
                                                        setDraggableKey(null)
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (!canEdit) {
                                                            return;
                                                        }
                                                        if (
                                                            event.key ===
                                                            'ArrowUp'
                                                        ) {
                                                            event.preventDefault();
                                                            moveByKey(
                                                                item.key,
                                                                -1,
                                                            );
                                                        }
                                                        if (
                                                            event.key ===
                                                            'ArrowDown'
                                                        ) {
                                                            event.preventDefault();
                                                            moveByKey(
                                                                item.key,
                                                                1,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <GripVertical className="size-4" />
                                                </button>
                                                <span className="text-muted-foreground w-5 shrink-0 font-mono text-xs">
                                                    {index + 1}
                                                </span>
                                                <div
                                                    className={cn(
                                                        'bg-muted relative flex shrink-0 items-center justify-center overflow-hidden rounded-md',
                                                        isPortrait
                                                            ? 'h-16 w-12'
                                                            : 'h-14 w-24',
                                                    )}
                                                >
                                                    {schema ? (
                                                        <LayoutRenderer
                                                            schema={schema}
                                                            mode="preview"
                                                            fitWidth={
                                                                isPortrait
                                                                    ? 44
                                                                    : 92
                                                            }
                                                            fitHeight={
                                                                isPortrait
                                                                    ? 60
                                                                    : 52
                                                            }
                                                            mediaMap={
                                                                mediaMapState
                                                            }
                                                        />
                                                    ) : (
                                                        <span className="text-muted-foreground px-1 text-center text-[9px]">
                                                            No preview
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">
                                                        {item.design_name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs capitalize">
                                                        {item.orientation ??
                                                            'unknown'}{' '}
                                                        ·{' '}
                                                        {formatDuration(
                                                            item.duration_seconds,
                                                        )}
                                                        {item.loop_count > 1
                                                            ? ` ×${item.loop_count}`
                                                            : ''}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex flex-1 flex-wrap items-end gap-2 sm:justify-end">
                                                <div className="w-24 space-y-1">
                                                    <Label
                                                        htmlFor={`duration-${item.key}`}
                                                        className="text-[10px]"
                                                    >
                                                        Duration (s)
                                                    </Label>
                                                    <Input
                                                        id={`duration-${item.key}`}
                                                        type="number"
                                                        inputMode="numeric"
                                                        min={
                                                            config.min_duration_seconds
                                                        }
                                                        max={
                                                            config.max_duration_seconds
                                                        }
                                                        disabled={!canEdit}
                                                        data-test={`playlist-item-duration-${index}`}
                                                        className="h-9"
                                                        value={
                                                            item.duration_seconds
                                                        }
                                                        onChange={(e) => {
                                                            const parsed =
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                );
                                                            updateItem(
                                                                item.key,
                                                                {
                                                                    duration_seconds:
                                                                        Number.isFinite(
                                                                            parsed,
                                                                        )
                                                                            ? Math.min(
                                                                                  config.max_duration_seconds,
                                                                                  Math.max(
                                                                                      config.min_duration_seconds,
                                                                                      Math.round(
                                                                                          parsed,
                                                                                      ),
                                                                                  ),
                                                                              )
                                                                            : config.default_duration_seconds,
                                                                },
                                                            );
                                                        }}
                                                    />
                                                </div>
                                                <div className="w-20 space-y-1">
                                                    <Label
                                                        htmlFor={`loop-${item.key}`}
                                                        className="text-[10px]"
                                                    >
                                                        Loops
                                                    </Label>
                                                    <Input
                                                        id={`loop-${item.key}`}
                                                        type="number"
                                                        inputMode="numeric"
                                                        min={
                                                            config.min_loop_count
                                                        }
                                                        max={
                                                            config.max_loop_count
                                                        }
                                                        disabled={!canEdit}
                                                        data-test={`playlist-item-loop-${index}`}
                                                        className="h-9"
                                                        value={item.loop_count}
                                                        onChange={(e) => {
                                                            const parsed =
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                );
                                                            updateItem(
                                                                item.key,
                                                                {
                                                                    loop_count:
                                                                        Number.isFinite(
                                                                            parsed,
                                                                        )
                                                                            ? Math.min(
                                                                                  config.max_loop_count,
                                                                                  Math.max(
                                                                                      config.min_loop_count,
                                                                                      Math.round(
                                                                                          parsed,
                                                                                      ),
                                                                                  ),
                                                                              )
                                                                            : config.default_loop_count,
                                                                },
                                                            );
                                                        }}
                                                    />
                                                </div>
                                                <div className="w-32 space-y-1">
                                                    <Label
                                                        htmlFor={`transition-${item.key}`}
                                                        className="text-[10px]"
                                                    >
                                                        Transition
                                                    </Label>
                                                    <select
                                                        id={`transition-${item.key}`}
                                                        className={
                                                            selectClassName
                                                        }
                                                        disabled={!canEdit}
                                                        data-test={`playlist-item-transition-${index}`}
                                                        value={item.transition}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                item.key,
                                                                {
                                                                    transition:
                                                                        e.target
                                                                            .value as PlaylistTransition,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {config.transitions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                                <div className="w-28 space-y-1">
                                                    <Label
                                                        htmlFor={`speed-${item.key}`}
                                                        className="text-[10px]"
                                                    >
                                                        Speed
                                                    </Label>
                                                    <select
                                                        id={`speed-${item.key}`}
                                                        className={
                                                            selectClassName
                                                        }
                                                        disabled={
                                                            !canEdit ||
                                                            item.transition ===
                                                                'none'
                                                        }
                                                        data-test={`playlist-item-speed-${index}`}
                                                        value={
                                                            item.transition_speed
                                                        }
                                                        onChange={(e) =>
                                                            updateItem(
                                                                item.key,
                                                                {
                                                                    transition_speed:
                                                                        e.target
                                                                            .value as PlaylistTransitionSpeed,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {config.transition_speeds.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>

                                                <label className="flex h-9 items-center gap-2 text-xs">
                                                    <Checkbox
                                                        checked={item.is_active}
                                                        disabled={!canEdit}
                                                        data-test={`playlist-item-active-${index}`}
                                                        aria-label={`Play ${item.design_name} in this playlist`}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            updateItem(
                                                                item.key,
                                                                {
                                                                    is_active:
                                                                        checked ===
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <span
                                                        className={cn(
                                                            'font-medium',
                                                            !item.is_active &&
                                                                'text-muted-foreground',
                                                        )}
                                                    >
                                                        {item.is_active
                                                            ? 'Active'
                                                            : 'Skipped'}
                                                    </span>
                                                </label>

                                                {canEdit ? (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8"
                                                            aria-label={`Move ${item.design_name} up`}
                                                            data-test={`playlist-item-up-${index}`}
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onClick={() =>
                                                                moveByKey(
                                                                    item.key,
                                                                    -1,
                                                                )
                                                            }
                                                        >
                                                            <ArrowUp className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8"
                                                            aria-label={`Move ${item.design_name} down`}
                                                            data-test={`playlist-item-down-${index}`}
                                                            disabled={
                                                                index ===
                                                                items.length - 1
                                                            }
                                                            onClick={() =>
                                                                moveByKey(
                                                                    item.key,
                                                                    1,
                                                                )
                                                            }
                                                        >
                                                            <ArrowDown className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8"
                                                            aria-label={`Replace design for item ${index + 1}`}
                                                            data-test={`playlist-item-replace-${index}`}
                                                            onClick={() => {
                                                                setReplaceKey(
                                                                    item.key,
                                                                );
                                                                setPickerOpen(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            <Repeat className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-8"
                                                            aria-label={`Duplicate ${item.design_name}`}
                                                            data-test={`playlist-item-duplicate-${index}`}
                                                            onClick={() =>
                                                                duplicateItem(
                                                                    item.key,
                                                                )
                                                            }
                                                        >
                                                            <Copy className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-destructive size-8"
                                                            aria-label={`Remove ${item.design_name}`}
                                                            data-test={`playlist-item-remove-${index}`}
                                                            onClick={() =>
                                                                removeItem(
                                                                    item.key,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </div>
                                                ) : null}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </div>

                    <aside className="border-border bg-card h-fit space-y-4 rounded-xl border p-4">
                        <div>
                            <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                Summary
                            </p>
                            <dl className="mt-2 space-y-1.5 text-sm">
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-muted-foreground">
                                        Items
                                    </dt>
                                    <dd data-test="playlist-summary-items">
                                        {items.length}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-muted-foreground">
                                        Active
                                    </dt>
                                    <dd data-test="playlist-summary-active">
                                        {activeItems.length}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-muted-foreground">
                                        Loop duration
                                    </dt>
                                    <dd
                                        className="inline-flex items-center gap-1 font-mono"
                                        data-test="playlist-summary-duration"
                                    >
                                        <Clock className="size-3" />
                                        {formatDuration(totalSeconds)}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-muted-foreground">
                                        Total seconds
                                    </dt>
                                    <dd
                                        className="font-mono"
                                        data-test="playlist-summary-seconds"
                                    >
                                        {totalSeconds}s
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="playlist-description">
                                Description
                            </Label>
                            <textarea
                                id="playlist-description"
                                data-test="playlist-editor-description"
                                className={cn(selectClassName, 'min-h-20 py-2')}
                                disabled={!canEdit}
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                            />
                        </div>

                        <div className="text-muted-foreground space-y-1 text-xs">
                            <p>
                                Version {playlist.latest_version_number ?? '—'}
                                {playlist.latest_published
                                    ? ' · published'
                                    : ' · draft'}
                            </p>
                            <p>
                                Publishing a playlist finalises this order for
                                deployment. It does not send content to a screen
                                by itself.
                            </p>
                        </div>

                        {!canEdit ? (
                            <p className="text-muted-foreground text-xs">
                                View only — your role cannot change playlists.
                            </p>
                        ) : null}
                    </aside>
                </div>
            </div>

            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent
                    className="flex max-h-[90vh] flex-col sm:max-w-[960px]"
                    data-test="playlist-preview-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>Preview · {name}</DialogTitle>
                        <DialogDescription>
                            Sequential playback of active items using their
                            durations and transitions.
                        </DialogDescription>
                    </DialogHeader>
                    <PlaylistPreviewPlayer
                        items={playerItems}
                        mediaMap={mediaMapState}
                        className="h-[min(60vh,520px)]"
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={pickerOpen}
                onOpenChange={(open) => {
                    setPickerOpen(open);
                    if (!open) {
                        setReplaceKey(null);
                    }
                }}
            >
                <DialogContent
                    className="flex max-h-[85vh] flex-col sm:max-w-2xl"
                    data-test="playlist-design-picker"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {replaceKey ? 'Replace Screen' : 'Add Screen'}
                        </DialogTitle>
                        <DialogDescription>
                            Only published Screens can be added to a playlist
                            {lockedOrientation
                                ? `, and this playlist is ${lockedOrientation}.`
                                : '.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                data-test="playlist-picker-search"
                                value={pickerSearch}
                                onChange={(e) =>
                                    setPickerSearch(e.target.value)
                                }
                                placeholder={`Search ${ProductLabels.screenDesignPlural.toLowerCase()}...`}
                                className="pl-9"
                            />
                        </div>
                        <select
                            className={cn(selectClassName, 'sm:w-44')}
                            aria-label="Orientation"
                            data-test="playlist-picker-orientation"
                            value={pickerOrientation}
                            onChange={(e) =>
                                setPickerOrientation(e.target.value)
                            }
                        >
                            <option value="all">All orientations</option>
                            <option value="landscape">Landscape</option>
                            <option value="portrait">Portrait</option>
                        </select>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {designsLoading && designs.length === 0 ? (
                            <div className="flex justify-center py-10">
                                <Spinner className="size-5" />
                            </div>
                        ) : designs.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                No published Screens match. Publish a Screen
                                first.
                            </p>
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {designs.map((design) => {
                                    const schema = resolveItemSchema(
                                        design.schema,
                                    );
                                    const isPortrait =
                                        design.orientation === 'portrait';
                                    const mismatched =
                                        lockedOrientation !== null &&
                                        design.orientation !==
                                            lockedOrientation;

                                    return (
                                        <button
                                            key={design.id}
                                            type="button"
                                            disabled={mismatched}
                                            title={
                                                mismatched
                                                    ? `This playlist is ${lockedOrientation}`
                                                    : undefined
                                            }
                                            data-test={`playlist-picker-design-${design.id}`}
                                            onClick={() => addDesign(design)}
                                            className="border-border hover:border-primary focus-visible:ring-ring/50 overflow-hidden rounded-lg border text-left focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <div
                                                className={cn(
                                                    'bg-muted relative flex items-center justify-center overflow-hidden',
                                                    isPortrait
                                                        ? 'aspect-[4/3]'
                                                        : 'aspect-video',
                                                )}
                                            >
                                                {schema ? (
                                                    <LayoutRenderer
                                                        schema={schema}
                                                        mode="preview"
                                                        fitWidth={
                                                            isPortrait
                                                                ? 90
                                                                : 160
                                                        }
                                                        fitHeight={
                                                            isPortrait
                                                                ? 120
                                                                : 100
                                                        }
                                                        mediaMap={mediaMapState}
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground text-[10px]">
                                                        No preview
                                                    </span>
                                                )}
                                            </div>
                                            <div className="p-2">
                                                <p className="truncate text-xs font-medium">
                                                    {design.name}
                                                </p>
                                                <p className="text-muted-foreground text-[10px]">
                                                    {design.orientation_label}
                                                    {mismatched
                                                        ? ' · orientation mismatch'
                                                        : ''}
                                                </p>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

PlaylistEdit.layout = {
    breadcrumbs: [
        { title: 'Playlists', href: '/app/playlists' },
        { title: 'Edit', href: '#' },
    ],
};
