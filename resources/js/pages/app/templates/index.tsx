import { Head, router } from '@inertiajs/react';
import { Eye, LayoutTemplate, Search, Star } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { LayoutRenderer } from '@/components/rendering/layout-renderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ProductLabels } from '@/lib/product-labels';
import { templates as templatesIndex } from '@/routes/app';
import templateRoutes from '@/routes/app/templates';
import {
    isLayoutSchema,
    normalizeLayoutSchema,
    type LayoutSchema,
} from '@/types/layout-schema';
import type {
    TemplateListItem,
    TemplateTab,
    TemplatesIndexProps,
} from '@/types/template';

const TABS: {
    id: TemplateTab;
    label: string;
    countKey: keyof TemplatesIndexProps['counts'];
}[] = [
    { id: 'all', label: 'All', countKey: 'all' },
    { id: 'dz', label: 'RMSignage', countKey: 'dz' },
    { id: 'favourites', label: 'Favourites', countKey: 'favourites' },
];

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function resolveSchema(
    template: TemplateListItem,
    _themes: TemplatesIndexProps['themes'],
): LayoutSchema | null {
    if (isLayoutSchema(template.schema)) {
        return normalizeLayoutSchema(template.schema);
    }

    return null;
}

export default function TemplatesIndex({
    templates: paginated,
    filters,
    counts,
    use_template_available: useTemplateAvailable,
    categories,
    orientations,
    themes,
    industries,
}: TemplatesIndexProps) {
    const [searchInput, setSearchInput] = useState(filters.q);
    const [previewTemplate, setPreviewTemplate] =
        useState<TemplateListItem | null>(null);
    const previewHostRef = useRef<HTMLDivElement>(null);
    const [previewFit, setPreviewFit] = useState({ width: 880, height: 520 });

    const orderedIndustries = useMemo(() => {
        const masjid = industries.filter((i) => i.value === 'masjid');
        const rest = industries
            .filter((i) => i.value !== 'masjid')
            .slice()
            .sort((a, b) => a.label.localeCompare(b.label));
        return [...masjid, ...rest];
    }, [industries]);

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

    useEffect(() => {
        if (!previewTemplate) {
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

        // Dialog layout settles after paint; measure twice.
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
    }, [previewTemplate]);

    function navigate(
        patch: Partial<{
            tab: string;
            q: string;
            industry: string;
            category: string;
            orientation: string;
            theme: string;
            page: number;
        }>,
    ) {
        const query = {
            tab: patch.tab ?? filters.tab,
            q: patch.q ?? filters.q,
            industry: patch.industry ?? filters.industry,
            category: patch.category ?? filters.category,
            orientation: patch.orientation ?? filters.orientation,
            theme: patch.theme ?? filters.theme,
            ...(patch.page ? { page: patch.page } : {}),
        };

        router.get(templatesIndex.url(), query, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }

    function toggleFavourite(template: TemplateListItem) {
        router.post(
            templateRoutes.favourite.url(template.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }

    function useTemplate(template: TemplateListItem) {
        if (!useTemplateAvailable) {
            return;
        }
        router.post(templateRoutes.use.url(template.id));
    }

    const activeTab = (filters.tab as TemplateTab) || 'all';
    const previewSchema = previewTemplate
        ? resolveSchema(previewTemplate, themes)
        : null;

    return (
        <>
            <Head title="Templates" />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Templates
                    </h1>
                    <p className="text-muted-foreground mt-0.5 text-sm">
                        Browse platform layouts to use in{' '}
                        {ProductLabels.screenDesignPlural}
                    </p>
                </div>

                <div className="border-border flex gap-1 overflow-x-auto border-b">
                    {TABS.map((tab) => {
                        const active = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                data-test={`templates-tab-${tab.id}`}
                                onClick={() =>
                                    navigate({
                                        tab: tab.id,
                                        q: '',
                                        industry: 'all',
                                        category: 'all',
                                        orientation: 'all',
                                        theme: 'all',
                                    })
                                }
                                className={cn(
                                    'shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                                    active
                                        ? 'border-primary text-primary'
                                        : 'text-muted-foreground hover:text-foreground border-transparent',
                                )}
                            >
                                {tab.label}
                                <span className="text-muted-foreground ml-1.5 font-mono text-xs">
                                    {counts[tab.countKey]}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="templates-search"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder="Search templates..."
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <select
                            className={cn(selectClassName, 'w-auto min-w-36')}
                            value={filters.category}
                            onChange={(e) =>
                                navigate({ category: e.target.value })
                            }
                            aria-label="Category"
                        >
                            <option value="all">All categories</option>
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className={cn(selectClassName, 'w-auto min-w-36')}
                            value={filters.orientation}
                            onChange={(e) =>
                                navigate({ orientation: e.target.value })
                            }
                            aria-label="Orientation"
                        >
                            <option value="all">All sizes</option>
                            {orientations.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className={cn(selectClassName, 'w-auto min-w-36')}
                            value={filters.theme}
                            onChange={(e) =>
                                navigate({ theme: e.target.value })
                            }
                            aria-label="Theme"
                        >
                            <option value="all">All themes</option>
                            {themes.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        type="button"
                        onClick={() => navigate({ industry: 'all' })}
                        className={cn(
                            'h-8 shrink-0 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap transition-colors',
                            filters.industry === 'all'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-secondary text-muted-foreground hover:text-foreground',
                        )}
                    >
                        All
                    </button>
                    {orderedIndustries.map((industry) => (
                        <button
                            key={industry.value}
                            type="button"
                            onClick={() =>
                                navigate({ industry: industry.value })
                            }
                            className={cn(
                                'h-8 shrink-0 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap transition-colors',
                                filters.industry === industry.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-secondary text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {industry.label}
                        </button>
                    ))}
                </div>

                {paginated.data.length === 0 ? (
                    <EmptyState
                        icon={LayoutTemplate}
                        title={
                            activeTab === 'favourites'
                                ? 'No favourites yet'
                                : 'No templates found'
                        }
                        description={
                            activeTab === 'favourites'
                                ? 'Star templates to save them here.'
                                : 'Try adjusting search or filters.'
                        }
                    />
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {paginated.data.map((template) => {
                            const schema = resolveSchema(template, themes);
                            const isPortrait =
                                template.orientation === 'portrait';

                            return (
                                <div
                                    key={template.id}
                                    data-test={`template-card-${template.id}`}
                                    data-orientation={template.orientation}
                                    className="border-border bg-card group hover:border-primary/40 overflow-hidden rounded-xl border transition-all duration-200 hover:shadow-xl"
                                >
                                    <div
                                        className={cn(
                                            'bg-muted relative overflow-hidden',
                                            isPortrait
                                                ? 'aspect-[9/16]'
                                                : 'aspect-video',
                                        )}
                                    >
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

                                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                            <Button
                                                size="sm"
                                                type="button"
                                                data-test={`template-preview-${template.id}`}
                                                onClick={() =>
                                                    setPreviewTemplate(template)
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Preview
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                type="button"
                                                data-test={`template-use-${template.id}`}
                                                disabled={!useTemplateAvailable}
                                                title={
                                                    useTemplateAvailable
                                                        ? 'Use this template'
                                                        : 'Use Template requires Screen permissions'
                                                }
                                                onClick={() =>
                                                    useTemplate(template)
                                                }
                                            >
                                                Use Template
                                            </Button>
                                        </div>

                                        <button
                                            type="button"
                                            aria-label={
                                                template.is_favourited
                                                    ? 'Remove favourite'
                                                    : 'Add favourite'
                                            }
                                            onClick={() =>
                                                toggleFavourite(template)
                                            }
                                            className={cn(
                                                'absolute top-2 right-2 rounded-full p-1.5 transition-colors',
                                                template.is_favourited
                                                    ? 'bg-rose-500 text-white'
                                                    : 'bg-black/40 text-white hover:bg-black/60',
                                            )}
                                        >
                                            <Star
                                                className={cn(
                                                    'size-3',
                                                    template.is_favourited &&
                                                        'fill-current',
                                                )}
                                            />
                                        </button>

                                        <span className="absolute right-2 bottom-2 rounded bg-black/60 px-1.5 py-0.5 font-mono text-[10px] text-white capitalize">
                                            {isPortrait ? '9:16' : '16:9'}
                                        </span>
                                    </div>

                                    <div className="p-3">
                                        <p className="truncate text-xs font-medium">
                                            {template.name}
                                        </p>
                                        <div className="mt-1 flex items-center justify-between gap-2">
                                            <p className="text-muted-foreground truncate font-mono text-[10px]">
                                                {template.industry ??
                                                    template.category_label}
                                            </p>
                                            <Badge
                                                variant="neutral"
                                                className="shrink-0 text-[10px]"
                                            >
                                                {template.theme_label}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {paginated.meta.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-sm">
                            Showing {paginated.meta.from ?? 0}–
                            {paginated.meta.to ?? 0} of {paginated.meta.total}
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

            <Dialog
                open={previewTemplate !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewTemplate(null);
                    }
                }}
            >
                <DialogContent className="flex max-h-[90vh] flex-col gap-4 sm:max-w-[960px]">
                    <DialogHeader>
                        <DialogTitle>{previewTemplate?.name}</DialogTitle>
                        <DialogDescription>
                            {previewTemplate?.orientation_label} ·{' '}
                            {previewTemplate?.theme_label}
                        </DialogDescription>
                    </DialogHeader>
                    {previewSchema ? (
                        <div
                            ref={previewHostRef}
                            data-test="template-preview-host"
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
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewTemplate(null)}
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            disabled={!useTemplateAvailable || !previewTemplate}
                            title={
                                useTemplateAvailable
                                    ? 'Use this template'
                                    : 'Use Template requires Screen permissions'
                            }
                            onClick={() => {
                                if (previewTemplate) {
                                    useTemplate(previewTemplate);
                                }
                            }}
                        >
                            Use Template
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

TemplatesIndex.layout = {
    breadcrumbs: [{ title: 'Templates', href: '/app/templates' }],
};
