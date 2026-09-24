import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_OPERATIONS_TABS } from '@/components/admin/admin-section-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ListPagination } from '@/components/ui/list-pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { adminSelectClassName } from '@/lib/admin-select-class';
import { cn } from '@/lib/utils';
import { errors as errorsIndex } from '@/routes/admin';
import { resolve as resolveErrorRoute } from '@/routes/admin/errors';

type ErrorRow = {
    id: number;
    category?: string | null;
    category_label?: string | null;
    workspace_name?: string | null;
    screen_name?: string | null;
    message?: string | null;
    occurred_at?: string | null;
    resolved_at?: string | null;
    resolved_by_name?: string | null;
    resolved?: boolean;
};

type PaginatedMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    errors?: {
        data: ErrorRow[];
        meta: PaginatedMeta;
    };
    filters?: {
        q?: string;
        category?: string;
        resolved?: string;
        per_page?: number;
    };
    categories?: { value: string; label: string }[];
};

type FilterPatch = Partial<{
    q: string;
    category: string;
    resolved: string;
    per_page: number;
    page: number;
}>;

const emptyMeta: PaginatedMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
};

function formatWhen(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

export default function AdminErrorsIndex({
    errors = { data: [], meta: emptyMeta },
    filters = {},
    categories = [],
}: Props) {
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const [resolvingId, setResolvingId] = useState<number | null>(null);
    const meta = errors.meta ?? emptyMeta;
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    useEffect(() => {
        setSearchInput(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === (filters.q ?? '')) {
                return;
            }
            navigate({ q: searchInput, page: 1 });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    function navigate(patch: FilterPatch) {
        router.get(
            errorsIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                category: patch.category ?? filters.category ?? 'all',
                resolved: patch.resolved ?? filters.resolved ?? 'unresolved',
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

    function resolveError(id: number) {
        setResolvingId(id);
        router.post(
            resolveErrorRoute.url(id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setResolvingId(null),
            },
        );
    }

    return (
        <>
            <Head title="Errors" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-errors"
            >
                <AdminPageHeader
                    title="Operations"
                    description="Monitor TVs, deployments and platform health."
                    badge={null}
                    tabs={ADMIN_OPERATIONS_TABS}
                    activeTab="errors"
                />

                <div className="flex flex-col gap-3 lg:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-errors-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search errors..."
                            className="pl-9"
                            aria-label="Search errors"
                        />
                    </div>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-48')}
                        value={filters.category ?? 'all'}
                        aria-label="Category filter"
                        onChange={(event) =>
                            navigate({
                                category: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All categories</option>
                        {categories.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-44')}
                        value={filters.resolved ?? 'unresolved'}
                        aria-label="Resolved filter"
                        onChange={(event) =>
                            navigate({
                                resolved: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All</option>
                        <option value="unresolved">Unresolved</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Error log
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total matching these filters.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {errors.data.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No errors match these filters.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Category</TableHead>
                                            <TableHead>Workspace</TableHead>
                                            <TableHead>Screen</TableHead>
                                            <TableHead>Message</TableHead>
                                            <TableHead>Occurred</TableHead>
                                            <TableHead>Resolved</TableHead>
                                            <TableHead className="text-right">
                                                Action
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {errors.data.map((row) => {
                                            const resolved =
                                                row.resolved === true ||
                                                Boolean(row.resolved_at);

                                            return (
                                                <TableRow key={row.id}>
                                                    <TableCell>
                                                        {row.category_label ??
                                                            row.category ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.workspace_name ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.screen_name ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="max-w-xs truncate text-sm">
                                                        {row.message ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">
                                                        {formatWhen(
                                                            row.occurred_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {resolved ? (
                                                            <StatusBadge
                                                                label="Resolved"
                                                                tone="healthy"
                                                            />
                                                        ) : (
                                                            <StatusBadge
                                                                label="Open"
                                                                tone="degraded"
                                                            />
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {!resolved ? (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    resolvingId ===
                                                                    row.id
                                                                }
                                                                onClick={() =>
                                                                    resolveError(
                                                                        row.id,
                                                                    )
                                                                }
                                                                data-test={`admin-error-resolve-${row.id}`}
                                                            >
                                                                Resolve
                                                            </Button>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">
                                                                {row.resolved_by_name
                                                                    ? `${row.resolved_by_name} · `
                                                                    : ''}
                                                                {formatWhen(
                                                                    row.resolved_at,
                                                                )}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
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
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminErrorsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Errors',
            href: errorsIndex(),
        },
    ],
};
