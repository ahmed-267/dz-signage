import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
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
import { audit_log as auditLogIndex } from '@/routes/admin';

type AuditRow = {
    id: number;
    action?: string | null;
    actor_name?: string | null;
    actor_email?: string | null;
    subject_type?: string | null;
    subject_id?: number | string | null;
    subject?: string | null;
    workspace_name?: string | null;
    ip_address?: string | null;
    created_at?: string | null;
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
    entries?: Paginated<AuditRow>;
    filters?: {
        q?: string;
        action?: string;
        entity_type?: string;
        workspace_id?: number | null;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    actions?: FilterOption[];
};

type FilterPatch = Partial<{
    q: string;
    action: string;
    sort: string;
    direction: string;
    per_page: number;
    page: number;
}>;

const emptyMeta: PaginatedMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 50,
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

export default function AdminAuditLogIndex({
    entries = { data: [], meta: emptyMeta },
    filters = {},
    actions = [],
}: Props) {
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const meta = entries.meta ?? emptyMeta;
    const perPage = filters.per_page ?? meta.per_page ?? 50;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: auditLogIndex.url(),
        filters: {
            q: filters.q,
            action: filters.action,
            entity_type: filters.entity_type,
            workspace_id: filters.workspace_id,
            sort: filters.sort,
            direction: filters.direction,
            per_page: perPage,
        },
        defaultSort: 'created',
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
            auditLogIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                action:
                    patch.action !== undefined
                        ? patch.action === 'all'
                            ? ''
                            : patch.action
                        : (filters.action ?? ''),
                sort: patch.sort ?? filters.sort ?? 'created',
                direction: patch.direction ?? filters.direction ?? 'desc',
                per_page: patch.per_page ?? perPage,
                ...(filters.entity_type
                    ? { entity_type: filters.entity_type }
                    : {}),
                ...(filters.workspace_id
                    ? { workspace_id: filters.workspace_id }
                    : {}),
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
            <Head title="Audit Log" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-audit-log"
            >
                <AdminPageHeader
                    title="Audit Log"
                    description="Immutable platform audit trail. Entries cannot be edited or deleted."
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-audit-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search audit events..."
                            className="pl-9"
                            aria-label="Search audit log"
                        />
                    </div>
                    {actions.length > 0 ? (
                        <select
                            className={cn(adminSelectClassName, 'sm:w-56')}
                            value={filters.action || 'all'}
                            aria-label="Action filter"
                            onChange={(event) =>
                                navigate({
                                    action: event.target.value,
                                    page: 1,
                                })
                            }
                        >
                            <option value="all">All actions</option>
                            {actions.map((option) => (
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
                            Events
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total. Read-only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {entries.data.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No audit events match these filters.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead
                                                aria-sort={ariaSort('created')}
                                            >
                                                <SortableTableHeader
                                                    label="When"
                                                    column="created"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead
                                                aria-sort={ariaSort('action')}
                                            >
                                                <SortableTableHeader
                                                    label="Action"
                                                    column="action"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead>Actor</TableHead>
                                            <TableHead
                                                aria-sort={ariaSort(
                                                    'entity_type',
                                                )}
                                            >
                                                <SortableTableHeader
                                                    label="Subject"
                                                    column="entity_type"
                                                    currentSort={currentSort}
                                                    currentDirection={
                                                        currentDirection
                                                    }
                                                    onSort={onSort}
                                                />
                                            </TableHead>
                                            <TableHead>Workspace</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {entries.data.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell className="text-muted-foreground text-sm whitespace-nowrap">
                                                    {formatWhen(row.created_at)}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {row.action ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        {row.actor_name ?? '—'}
                                                    </div>
                                                    {row.actor_email ? (
                                                        <div className="text-muted-foreground text-xs">
                                                            {row.actor_email}
                                                        </div>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell>
                                                    {row.subject ??
                                                        (row.subject_type
                                                            ? `${row.subject_type}${
                                                                  row.subject_id !=
                                                                  null
                                                                      ? ` #${row.subject_id}`
                                                                      : ''
                                                              }`
                                                            : '—')}
                                                </TableCell>
                                                <TableCell>
                                                    {row.workspace_name ?? '—'}
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
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminAuditLogIndex.layout = {
    breadcrumbs: [
        {
            title: 'Audit Log',
            href: auditLogIndex(),
        },
    ],
};
