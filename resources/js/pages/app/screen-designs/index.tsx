import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Clapperboard,
    Copy,
    Eye,
    LayoutTemplate,
    Monitor,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AiDesignDialog from '@/components/ai/ai-design-dialog';
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
import { formatRelativeDate } from '@/lib/format-bytes';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { screen_designs as screenDesignsIndex, templates } from '@/routes/app';
import screenDesignRoutes from '@/routes/app/screen_designs';
import {
    isLayoutSchema,
    normalizeLayoutSchema,
    type LayoutSchema,
} from '@/types/layout-schema';
import type {
    ScreenDesignListItem,
    ScreenDesignsIndexProps,
} from '@/types/screen-design';

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'archived', label: 'Archived' },
] as const;

const STATUS_FILTER_VALUES = new Set(
    STATUS_FILTERS.map((filter) => filter.value),
);

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function resolveSchema(item: ScreenDesignListItem): LayoutSchema | null {
    if (isLayoutSchema(item.schema)) {
        return normalizeLayoutSchema(item.schema);
    }

    return null;
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

export default function ScreenDesignsIndex({
    designs: paginated,
    filters,
    can_manage: canManage,
    can_publish_to_screens: canPublishToScreens = false,
    screens: workspaceScreens = [],
}: ScreenDesignsIndexProps) {
    const { ai } = usePage().props;
    const [searchInput, setSearchInput] = useState(filters.q);
    const [createOpen, setCreateOpen] = useState(false);
    const [aiDesignOpen, setAiDesignOpen] = useState(false);
    const [createName, setCreateName] = useState('');
    const [creating, setCreating] = useState(false);
    const [previewDesign, setPreviewDesign] =
        useState<ScreenDesignListItem | null>(null);
    const [renameDesign, setRenameDesign] =
        useState<ScreenDesignListItem | null>(null);
    const [renameValue, setRenameValue] = useState('');
    const [deleteDesign, setDeleteDesign] =
        useState<ScreenDesignListItem | null>(null);
    const [publishToScreensDesign, setPublishToScreensDesign] =
        useState<ScreenDesignListItem | null>(null);
    const [selectedScreenIds, setSelectedScreenIds] = useState<number[]>([]);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);

    const previewHostRef = useRef<HTMLDivElement>(null);
    const [previewFit, setPreviewFit] = useState({ width: 880, height: 520 });

    const visibleIds = useMemo(
        () => paginated.data.map((design) => design.id),
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
            screenDesignRoutes.bulk_destroy.url(),
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

    function navigate(
        patch: Partial<{
            q: string;
            orientation: string;
            status: string;
            sort: string;
            page: number;
        }>,
    ) {
        router.get(
            screenDesignsIndex.url(),
            {
                q: patch.q ?? filters.q,
                orientation: patch.orientation ?? filters.orientation,
                status: patch.status ?? filters.status,
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

    useEffect(() => {
        setSearchInput(filters.q);
    }, [filters.q]);

    useEffect(() => {
        if (
            STATUS_FILTER_VALUES.has(
                filters.status as (typeof STATUS_FILTERS)[number]['value'],
            )
        ) {
            return;
        }

        navigate({ status: 'all' });
        // eslint-disable-next-line react-hooks/exhaustive-deps -- normalise unsupported status once per filters.status
    }, [filters.status]);

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

    useEffect(() => {
        if (!previewDesign) {
            return;
        }

        let frame = 0;
        const measure = () => {
            const host = previewHostRef.current;
            if (!host) {
                return;
            }
            const rect = host.getBoundingClientRect();
            setPreviewFit({
                width: Math.max(120, rect.width - 24),
                height: Math.max(120, rect.height - 24),
            });
        };

        measure();
        frame = window.requestAnimationFrame(() => {
            measure();
            frame = window.requestAnimationFrame(measure);
        });

        const host = previewHostRef.current;
        if (!host) {
            return () => window.cancelAnimationFrame(frame);
        }

        const observer = new ResizeObserver(measure);
        observer.observe(host);
        return () => {
            window.cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [previewDesign]);

    function createBlank(orientation: 'landscape' | 'portrait') {
        const defaultName =
            orientation === 'portrait'
                ? 'New Portrait Design'
                : 'New Landscape Design';
        const name = createName.trim() || defaultName;
        setCreating(true);
        router.post(
            screenDesignRoutes.store_blank.url(),
            {
                name,
                orientation,
            },
            {
                onFinish: () => setCreating(false),
            },
        );
    }

    function handleRename() {
        if (!renameDesign || !renameValue.trim()) {
            return;
        }
        setBusyId(renameDesign.id);
        router.post(
            screenDesignRoutes.rename.url(renameDesign.id),
            { name: renameValue.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setRenameDesign(null);
                },
            },
        );
    }

    function handleDuplicate(design: ScreenDesignListItem) {
        setBusyId(design.id);
        router.post(
            screenDesignRoutes.duplicate.url(design.id),
            {},
            {
                onFinish: () => setBusyId(null),
            },
        );
    }

    function handlePublish(design: ScreenDesignListItem) {
        setBusyId(design.id);
        router.post(
            screenDesignRoutes.publish.url(design.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    }

    function handleDelete() {
        if (!deleteDesign) {
            return;
        }
        setBusyId(deleteDesign.id);
        router.delete(screenDesignRoutes.destroy.url(deleteDesign.id), {
            onFinish: () => {
                setBusyId(null);
                setDeleteDesign(null);
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

    function handlePublishToScreens() {
        if (!publishToScreensDesign || selectedScreenIds.length === 0) {
            return;
        }
        setBusyId(publishToScreensDesign.id);
        router.post(
            screenDesignRoutes.publish_to_screens.url(
                publishToScreensDesign.id,
            ),
            { screen_ids: selectedScreenIds },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setPublishToScreensDesign(null);
                    setSelectedScreenIds([]);
                },
            },
        );
    }

    const previewSchema = previewDesign ? resolveSchema(previewDesign) : null;

    return (
        <>
            <Head title={ProductLabels.screenDesignPlural} />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {ProductLabels.screenDesignPlural}
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Create content for your TVs
                        </p>
                    </div>
                    {canManage ? (
                        <Button
                            type="button"
                            data-test="screen-designs-create"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            {ProductLabels.createScreen}
                        </Button>
                    ) : null}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="screen-designs-search"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder={`Search ${ProductLabels.screenDesignPlural.toLowerCase()}...`}
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
                            data-test="screen-designs-orientation"
                        >
                            <option value="all">All orientations</option>
                            <option value="landscape">Landscape</option>
                            <option value="portrait">Portrait</option>
                        </select>
                        <div
                            className="bg-secondary flex gap-1 rounded-lg p-1"
                            data-test="screen-designs-status-filters"
                        >
                            {STATUS_FILTERS.map((f) => (
                                <button
                                    key={f.value}
                                    type="button"
                                    onClick={() =>
                                        navigate({ status: f.value })
                                    }
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
                        data-test="screen-designs-bulk-bar"
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
                            aria-label={`Select all ${ProductLabels.screenDesignPlural}`}
                            data-test="screen-designs-select-all"
                        />
                        <span className="text-sm font-medium">
                            {selected.length} selected
                        </span>
                        <div className="flex flex-wrap gap-2 sm:ml-auto">
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                data-test="screen-designs-bulk-delete"
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
                        icon={Clapperboard}
                        title={`No ${ProductLabels.screenDesignPlural.toLowerCase()} yet`}
                        description={
                            canManage
                                ? 'Start blank or browse Templates to create your first Screen.'
                                : 'No Screens match your filters.'
                        }
                        action={
                            canManage ? (
                                <div className="flex flex-wrap justify-center gap-2">
                                    <Button
                                        type="button"
                                        data-test="screen-designs-empty-create"
                                        onClick={() => setCreateOpen(true)}
                                    >
                                        <Plus className="size-4" />
                                        {ProductLabels.createScreen}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link href={templates.url()}>
                                            <LayoutTemplate className="size-4" />
                                            Browse Templates
                                        </Link>
                                    </Button>
                                </div>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {paginated.data.map((design) => {
                            const schema = resolveSchema(design);
                            const isPortrait =
                                design.orientation === 'portrait';
                            const busy = busyId === design.id;

                            return (
                                <div
                                    key={design.id}
                                    data-test={`screen-design-card-${design.id}`}
                                    data-orientation={design.orientation}
                                    data-status={design.status}
                                    className={cn(
                                        'border-border bg-card group hover:border-primary/40 overflow-hidden rounded-xl border transition-all duration-200 hover:shadow-xl',
                                        selected.includes(design.id) &&
                                            'border-primary/50 ring-primary/20 ring-2',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'bg-muted relative overflow-hidden',
                                            isPortrait
                                                ? 'aspect-[9/16]'
                                                : 'aspect-video',
                                        )}
                                    >
                                        {canManage ? (
                                            <div
                                                className="absolute top-2 left-2 z-10"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Checkbox
                                                    checked={selected.includes(
                                                        design.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelected(
                                                            design.id,
                                                            Boolean(checked),
                                                        )
                                                    }
                                                    aria-label={`Select ${design.name}`}
                                                    data-test={`screen-design-select-${design.id}`}
                                                    className="bg-card/90 border-border"
                                                />
                                            </div>
                                        ) : null}
                                        {schema ? (
                                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center p-2">
                                                <LayoutRenderer
                                                    schema={schema}
                                                    mode="preview"
                                                    fitWidth={
                                                        isPortrait ? 140 : 220
                                                    }
                                                    fitHeight={
                                                        isPortrait ? 240 : 140
                                                    }
                                                    className="rounded-sm shadow-sm"
                                                />
                                            </div>
                                        ) : (
                                            <div className="text-muted-foreground absolute inset-0 flex items-center justify-center px-3 text-center text-xs">
                                                Preview unavailable
                                            </div>
                                        )}

                                        <div
                                            className={cn(
                                                'absolute left-2 flex flex-col gap-1',
                                                canManage ? 'top-9' : 'top-2',
                                            )}
                                        >
                                            <Badge
                                                variant={statusBadgeVariant(
                                                    design.status,
                                                )}
                                                className="capitalize"
                                            >
                                                {design.status_label}
                                            </Badge>
                                        </div>
                                        <div className="absolute top-2 right-2">
                                            <Badge
                                                variant="neutral"
                                                className="capitalize"
                                            >
                                                {design.orientation_label}
                                            </Badge>
                                        </div>

                                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                            <Button
                                                size="sm"
                                                type="button"
                                                data-test={`screen-design-edit-${design.id}`}
                                                asChild
                                            >
                                                <Link
                                                    href={screenDesignRoutes.edit.url(
                                                        design.id,
                                                    )}
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                type="button"
                                                data-test={`screen-design-preview-${design.id}`}
                                                onClick={() =>
                                                    setPreviewDesign(design)
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Preview
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="p-3">
                                        <div className="flex items-start justify-between gap-1">
                                            <div className="min-w-0">
                                                <p className="truncate text-xs font-medium">
                                                    {design.name}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 font-mono text-[10px]">
                                                    {formatRelativeDate(
                                                        design.updated_at,
                                                    )}
                                                </p>
                                            </div>
                                            {canManage ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7 shrink-0"
                                                            data-test={`screen-design-menu-${design.id}`}
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
                                                            asChild
                                                        >
                                                            <Link
                                                                href={screenDesignRoutes.edit.url(
                                                                    design.id,
                                                                )}
                                                            >
                                                                <Pencil className="size-3.5" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                setPreviewDesign(
                                                                    design,
                                                                )
                                                            }
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Preview
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                setRenameDesign(
                                                                    design,
                                                                );
                                                                setRenameValue(
                                                                    design.name,
                                                                );
                                                            }}
                                                        >
                                                            Rename
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleDuplicate(
                                                                    design,
                                                                )
                                                            }
                                                        >
                                                            <Copy className="size-3.5" />
                                                            Duplicate
                                                        </DropdownMenuItem>
                                                        {design.status !==
                                                        'published' ? (
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    handlePublish(
                                                                        design,
                                                                    )
                                                                }
                                                            >
                                                                <Upload className="size-3.5" />
                                                                Publish
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        {canPublishToScreens &&
                                                        design.status ===
                                                            'published' ? (
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    setPublishToScreensDesign(
                                                                        design,
                                                                    );
                                                                    setSelectedScreenIds(
                                                                        [],
                                                                    );
                                                                }}
                                                                data-test={`screen-design-publish-to-screens-${design.id}`}
                                                            >
                                                                <Monitor className="size-3.5" />
                                                                {
                                                                    ProductLabels.publishTo
                                                                }
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                setDeleteDesign(
                                                                    design,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : null}
                                        </div>
                                        {design.source_template_name ? (
                                            <div className="mt-2 flex items-center gap-1">
                                                <LayoutTemplate className="size-3 text-violet-400" />
                                                <span className="truncate font-mono text-[10px] text-violet-400">
                                                    {
                                                        design.source_template_name
                                                    }
                                                </span>
                                            </div>
                                        ) : null}
                                    </div>
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
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{ProductLabels.createScreen}</DialogTitle>
                        <DialogDescription>
                            Start blank, browse Templates, or create a draft
                            with AI.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="create-name">
                                {ProductLabels.screenName}
                            </Label>
                            <Input
                                id="create-name"
                                data-test="screen-designs-create-name"
                                value={createName}
                                placeholder="e.g. Lobby Welcome"
                                onChange={(e) => setCreateName(e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                Leave blank to use New Landscape / Portrait
                                Screen.
                            </p>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={creating}
                                data-test="screen-designs-create-landscape"
                                onClick={() => createBlank('landscape')}
                            >
                                {creating ? <Spinner /> : null}
                                Start Blank · Landscape
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={creating}
                                data-test="screen-designs-create-portrait"
                                onClick={() => createBlank('portrait')}
                            >
                                {creating ? <Spinner /> : null}
                                Start Blank · Portrait
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            className="w-full"
                            asChild
                        >
                            <Link href={templates.url()}>
                                <LayoutTemplate className="size-4" />
                                Browse Templates
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            className="w-full"
                            data-test="screen-designs-create-ai"
                            onClick={() => {
                                setCreateOpen(false);
                                setAiDesignOpen(true);
                            }}
                        >
                            <Sparkles className="size-4" />
                            Create with AI
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <AiDesignDialog
                open={aiDesignOpen}
                onOpenChange={setAiDesignOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
            />

            <Dialog
                open={previewDesign !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewDesign(null);
                    }
                }}
            >
                <DialogContent className="flex max-h-[90vh] flex-col gap-4 sm:max-w-[960px]">
                    <DialogHeader>
                        <DialogTitle>{previewDesign?.name}</DialogTitle>
                        <DialogDescription>
                            {previewDesign?.orientation_label} ·{' '}
                            {previewDesign?.status_label}
                        </DialogDescription>
                    </DialogHeader>
                    {previewSchema ? (
                        <div
                            ref={previewHostRef}
                            data-test="screen-design-preview-host"
                            className="bg-muted/40 flex w-full items-center justify-center overflow-hidden rounded-lg p-4"
                            style={{
                                height: 'min(70vh, 640px)',
                                maxHeight: 'min(70vh, 640px)',
                            }}
                        >
                            <LayoutRenderer
                                schema={previewSchema}
                                mode="preview"
                                fitWidth={previewFit.width}
                                fitHeight={previewFit.height}
                            />
                        </div>
                    ) : (
                        <div className="text-muted-foreground flex h-40 items-center justify-center text-sm">
                            Preview unavailable
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewDesign(null)}
                        >
                            Close
                        </Button>
                        {previewDesign ? (
                            <Button type="button" asChild>
                                <Link
                                    href={screenDesignRoutes.edit.url(
                                        previewDesign.id,
                                    )}
                                >
                                    Edit
                                </Link>
                            </Button>
                        ) : null}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={renameDesign !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRenameDesign(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Rename Screen</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-1.5">
                        <Label htmlFor="screen-design-rename-input">
                            {ProductLabels.screenName}
                        </Label>
                        <Input
                            id="screen-design-rename-input"
                            data-test="screen-design-rename-input"
                            value={renameValue}
                            onChange={(e) => setRenameValue(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleRename();
                                }
                            }}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRenameDesign(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="screen-design-rename-confirm"
                            onClick={handleRename}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteDesign !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteDesign(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete “{deleteDesign?.name}”?
                        </DialogTitle>
                        <DialogDescription>
                            This permanently deletes the Screen and cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteDesign(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="screen-design-delete-confirm"
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
                            {selected.length === 1
                                ? ProductLabels.screenDesignSingular
                                : ProductLabels.screenDesignPlural}
                            ?
                        </DialogTitle>
                        <DialogDescription>
                            This permanently deletes the selected{' '}
                            {ProductLabels.screenDesignPlural.toLowerCase()}.
                            Items used by playlists or live deployments will be
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
                            data-test="screen-designs-bulk-delete-confirm"
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
                open={publishToScreensDesign !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPublishToScreensDesign(null);
                        setSelectedScreenIds([]);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{ProductLabels.publishTo}</DialogTitle>
                        <DialogDescription>
                            Deploy “{publishToScreensDesign?.name}” to one or
                            more paired TVs.
                        </DialogDescription>
                    </DialogHeader>
                    {workspaceScreens.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No TVs in this business yet. Pair a TV first.
                        </p>
                    ) : (
                        <div
                            className="max-h-64 space-y-2 overflow-y-auto"
                            data-test="publish-to-screens-list"
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
                                            data-test={`publish-screen-option-${screen.id}`}
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
                                setPublishToScreensDesign(null);
                                setSelectedScreenIds([]);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="publish-to-screens-confirm"
                            disabled={selectedScreenIds.length === 0}
                            onClick={handlePublishToScreens}
                        >
                            {ProductLabels.publishToSelected}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ScreenDesignsIndex.layout = {
    breadcrumbs: [
        {
            title: ProductLabels.screenDesignPlural,
            href: '/app/screen-designs',
        },
    ],
};
