import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ListPagination } from '@/components/ui/list-pagination';
import { SortableTableHeader } from '@/components/ui/sortable-table-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListSort } from '@/hooks/use-list-sort';
import { adminSelectClassName } from '@/lib/admin-select-class';
import { cn } from '@/lib/utils';
import { support as supportIndex } from '@/routes/admin';
import { show as showSupport } from '@/routes/admin/support';

type SupportRow = {
    id: number;
    subject?: string | null;
    category?: string | null;
    category_label?: string | null;
    workspace_name?: string | null;
    user_name?: string | null;
    requester_name?: string | null;
    status?: string | null;
    status_label?: string | null;
    priority?: string | null;
    priority_label?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
};

type PaginatedMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Paginated<T> = {
    data: T[];
    meta: PaginatedMeta;
};

type FilterOption = { value: string; label: string };

type Props = {
    requests?: Paginated<SupportRow>;
    filters?: {
        q?: string;
        status?: string;
        priority?: string;
        category?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    statuses?: FilterOption[];
    priorities?: FilterOption[];
    categories?: FilterOption[];
};

type FilterPatch = Partial<{
    q: string;
    status: string;
    priority: string;
    category: string;
    sort: string;
    direction: string;
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

export default function AdminSupportIndex({
    requests = { data: [], meta: emptyMeta },
    filters = {},
    statuses = [],
    priorities = [],
    categories = [],
}: Props) {
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const meta = requests.meta ?? emptyMeta;
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: supportIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            priority: filters.priority,
            category: filters.category,
            sort: filters.sort,
            direction: filters.direction,
            per_page: perPage,
        },
        defaultSort: 'updated',
        defaultDirection: 'desc',
    });

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
            supportIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                status: patch.status ?? filters.status ?? 'all',
                priority: patch.priority ?? filters.priority ?? 'all',
                category: patch.category ?? filters.category ?? 'all',
                sort: patch.sort ?? filters.sort ?? 'updated',
                direction: patch.direction ?? filters.direction ?? 'desc',
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

    return (
        <>
            <Head title="Support Requests" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-support"
            >
                <AdminPageHeader
                    title="Support"
                    description="Customer support requests across the platform."
                    badge={null}
                />

                <div className="flex flex-col gap-3 lg:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-support-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search requests..."
                            className="pl-9"
                            aria-label="Search support requests"
                        />
                    </div>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-44')}
                        value={filters.status ?? 'all'}
                        aria-label="Status filter"
                        onChange={(event) =>
                            navigate({
                                status: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All statuses</option>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-44')}
                        value={filters.priority ?? 'all'}
                        aria-label="Priority filter"
                        onChange={(event) =>
                            navigate({
                                priority: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All priorities</option>
                        {priorities.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {categories.length > 0 ? (
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
                    ) : null}
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Requests
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total across the platform.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {requests.data.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No support requests match these filters.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead
                                            aria-sort={ariaSort('subject')}
                                        >
                                            <SortableTableHeader
                                                label="Subject"
                                                column="subject"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>Workspace</TableHead>
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
                                        <TableHead
                                            aria-sort={ariaSort('priority')}
                                        >
                                            <SortableTableHeader
                                                label="Priority"
                                                column="priority"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('updated')}
                                        >
                                            <SortableTableHeader
                                                label="Updated"
                                                column="updated"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.data.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link
                                                    href={showSupport.url(
                                                        row.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.subject ??
                                                        `Request #${row.id}`}
                                                </Link>
                                                <div className="text-muted-foreground text-xs">
                                                    {row.category_label ??
                                                        row.category ??
                                                        ''}
                                                    {row.user_name ||
                                                    row.requester_name
                                                        ? ` · ${row.user_name ?? row.requester_name}`
                                                        : ''}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {row.workspace_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={
                                                        row.status_label ??
                                                        row.status ??
                                                        '—'
                                                    }
                                                    tone={row.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={
                                                        row.priority_label ??
                                                        row.priority ??
                                                        '—'
                                                    }
                                                    tone={
                                                        row.priority ===
                                                            'high' ||
                                                        row.priority ===
                                                            'urgent'
                                                            ? 'degraded'
                                                            : 'neutral'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatWhen(
                                                    row.updated_at ??
                                                        row.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
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

AdminSupportIndex.layout = {
    breadcrumbs: [
        {
            title: 'Support Requests',
            href: supportIndex(),
        },
    ],
};
