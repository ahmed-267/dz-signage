import { Head, Link, router, useForm } from '@inertiajs/react';
import { Layers, Plus, Search } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import InputError from '@/components/input-error';
import { LayoutRenderer } from '@/components/rendering/layout-renderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { ListPagination } from '@/components/ui/list-pagination';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { templates as templatesIndex } from '@/routes/admin';
import templateRoutes from '@/routes/admin/templates';
import type { AdminTemplatesIndexProps } from '@/types/template';
import { isLayoutSchema } from '@/types/layout-schema';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function statusVariant(
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

export default function AdminTemplatesIndex({
    templates,
    media_map: mediaMap,
    filters = {},
    categories,
    orientations,
    themes,
    industries,
}: AdminTemplatesIndexProps) {
    const [industry, setIndustry] = useState('all');
    const [search, setSearch] = useState('');
    const [createOpen, setCreateOpen] = useState(false);
    const meta = templates.meta;
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    const createForm = useForm({
        name: '',
        orientation: orientations[0]?.value ?? 'landscape',
        theme: themes[0]?.value ?? 'blank',
        category: '',
        industry: '',
        description: '',
    });

    const orderedIndustries = useMemo(() => {
        const masjid = industries.filter((i) => i.value === 'masjid');
        const rest = industries
            .filter((i) => i.value !== 'masjid')
            .slice()
            .sort((a, b) => a.label.localeCompare(b.label));
        return [...masjid, ...rest];
    }, [industries]);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return templates.data.filter((row) => {
            const matchIndustry =
                industry === 'all' || row.industry === industry;
            const matchSearch =
                q === '' ||
                row.name.toLowerCase().includes(q) ||
                (row.industry ?? '').toLowerCase().includes(q) ||
                row.category_label.toLowerCase().includes(q);
            return matchIndustry && matchSearch;
        });
    }, [templates.data, industry, search]);

    const counts = useMemo(() => {
        const all = templates.data;
        return {
            total: meta.total,
            published: all.filter((t) => t.status === 'published').length,
            draft: all.filter((t) => t.status === 'draft').length,
            archived: all.filter((t) => t.status === 'archived').length,
        };
    }, [templates.data, meta.total]);

    function navigate(patch: Partial<{ per_page: number; page: number }>) {
        router.get(
            templatesIndex.url(),
            {
                per_page: patch.per_page ?? perPage,
                ...(patch.page ? { page: patch.page } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function submitCreate(event: FormEvent) {
        event.preventDefault();
        createForm.transform((data) => ({
            ...data,
            industry: data.industry || null,
            category: data.category || null,
            description: data.description || null,
        }));
        createForm.post(templateRoutes.store.url(), {
            onSuccess: () => {
                createForm.reset();
                setCreateOpen(false);
            },
            onFinish: () => {
                createForm.transform((data) => data);
            },
        });
    }

    return (
        <>
            <Head title="Platform Templates" />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <AdminPageHeader
                        title="Content"
                        description="Manage platform-wide templates available to all businesses."
                        badge={null}
                    />
                    <Button
                        type="button"
                        data-test="admin-templates-create"
                        onClick={() => setCreateOpen(true)}
                    >
                        <Plus className="size-4" />
                        New Template
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {(
                        [
                            { label: 'Total', value: counts.total },
                            { label: 'Published', value: counts.published },
                            { label: 'Draft', value: counts.draft },
                            { label: 'Archived', value: counts.archived },
                        ] as const
                    ).map((stat) => (
                        <div
                            key={stat.label}
                            className="border-border bg-card rounded-xl border p-4"
                        >
                            <p className="text-muted-foreground mb-2 font-mono text-xs">
                                {stat.label}
                            </p>
                            <p className="font-display text-3xl font-bold">
                                {stat.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search platform templates..."
                            className="pl-9"
                        />
                    </div>
                </div>

                <div className="flex gap-1.5 overflow-x-auto pb-1">
                    <button
                        type="button"
                        onClick={() => setIndustry('all')}
                        className={cn(
                            'h-8 shrink-0 rounded-lg px-3 text-xs font-medium whitespace-nowrap transition-colors',
                            industry === 'all'
                                ? 'bg-primary/15 text-primary border-primary/30 border'
                                : 'bg-secondary text-muted-foreground hover:text-foreground',
                        )}
                    >
                        All
                    </button>
                    {orderedIndustries.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => setIndustry(item.value)}
                            className={cn(
                                'h-8 shrink-0 rounded-lg px-3 text-xs font-medium whitespace-nowrap transition-colors',
                                industry === item.value
                                    ? 'bg-primary/15 text-primary border-primary/30 border'
                                    : 'bg-secondary text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {filtered.length === 0 ? (
                    <EmptyState
                        icon={Layers}
                        title="No platform templates"
                        description="Create a platform template to seed the gallery."
                        action={
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setCreateOpen(true)}
                            >
                                <Plus className="size-3.5" />
                                New Template
                            </Button>
                        }
                    />
                ) : (
                    <div className="border-border bg-card overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Template</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Category
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Industry
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Orientation
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={cn(
                                                        'bg-muted relative shrink-0 overflow-hidden rounded-md',
                                                        row.orientation ===
                                                            'portrait'
                                                            ? 'h-16 w-10'
                                                            : 'h-12 w-20',
                                                    )}
                                                    data-test={`admin-template-preview-${row.id}`}
                                                >
                                                    {isLayoutSchema(
                                                        row.schema,
                                                    ) ? (
                                                        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                            <LayoutRenderer
                                                                schema={
                                                                    row.schema
                                                                }
                                                                mode="preview"
                                                                mediaMap={
                                                                    mediaMap
                                                                }
                                                                fitWidth={
                                                                    row.orientation ===
                                                                    'portrait'
                                                                        ? 40
                                                                        : 80
                                                                }
                                                                fitHeight={
                                                                    row.orientation ===
                                                                    'portrait'
                                                                        ? 64
                                                                        : 48
                                                                }
                                                            />
                                                        </div>
                                                    ) : (
                                                        <div className="text-muted-foreground flex h-full items-center justify-center px-1 text-center text-[10px]">
                                                            No preview
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="font-medium">
                                                        {row.name}
                                                    </div>
                                                    {row.description ? (
                                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                                                            {row.description}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden md:table-cell">
                                            {row.category_label}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {row.industry ? (
                                                <Badge variant="neutral">
                                                    {row.industry}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-sm capitalize lg:table-cell">
                                            {row.orientation}
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
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={templateRoutes.builder.url(
                                                            row.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                                {row.status !== 'published' ? (
                                                    <Button
                                                        size="sm"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                templateRoutes.publish.url(
                                                                    row.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Publish
                                                    </Button>
                                                ) : null}
                                                {row.status !== 'archived' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                templateRoutes.archive.url(
                                                                    row.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Archive
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {meta.last_page > 1 || meta.total > perPage ? (
                    <ListPagination
                        page={meta.current_page}
                        pageCount={meta.last_page}
                        total={meta.total}
                        from={meta.from}
                        to={meta.to}
                        perPage={perPage}
                        onPageChange={(page) => navigate({ page })}
                        onPerPageChange={(per_page) =>
                            navigate({ per_page, page: 1 })
                        }
                    />
                ) : null}
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New platform template</DialogTitle>
                        <DialogDescription>
                            Creates a draft platform template and opens the
                            editor.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="admin-template-name">Name</Label>
                            <Input
                                id="admin-template-name"
                                data-test="admin-templates-create-name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="admin-orientation">
                                    Orientation
                                </Label>
                                <select
                                    id="admin-orientation"
                                    className={selectClassName}
                                    value={createForm.data.orientation}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'orientation',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {orientations.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="admin-theme">Theme</Label>
                                <select
                                    id="admin-theme"
                                    className={selectClassName}
                                    value={createForm.data.theme}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'theme',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {themes.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="admin-industry">Industry</Label>
                                <select
                                    id="admin-industry"
                                    className={selectClassName}
                                    value={createForm.data.industry}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'industry',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">None</option>
                                    {orderedIndustries.map((i) => (
                                        <option key={i.value} value={i.value}>
                                            {i.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="admin-category">Category</Label>
                                <select
                                    id="admin-category"
                                    className={selectClassName}
                                    value={createForm.data.category}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'category',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Default</option>
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
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
                                type="submit"
                                data-test="admin-templates-create-submit"
                                disabled={createForm.processing}
                            >
                                {createForm.processing ? <Spinner /> : null}
                                Create
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminTemplatesIndex.layout = {
    breadcrumbs: [{ title: 'Templates', href: templatesIndex.url() }],
};
