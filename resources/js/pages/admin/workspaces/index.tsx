import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_CUSTOMERS_TABS } from '@/components/admin/admin-section-header';
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
import { workspaces as workspacesIndex } from '@/routes/admin';
import { show as showWorkspace } from '@/routes/admin/workspaces';

type WorkspaceRow = {
    id: number;
    name: string;
    industry?: string | null;
    industry_label?: string | null;
    owner?: string | null;
    owner_email?: string | null;
    member_count?: number;
    screen_count?: number;
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
    workspaces: Paginated<WorkspaceRow>;
    filters?: {
        q?: string;
        industry?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    industries?: FilterOption[];
};

type FilterPatch = Partial<{
    q: string;
    industry: string;
    sort: string;
    direction: string;
    per_page: number;
    page: number;
}>;

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleDateString();
    } catch {
        return value;
    }
}

export default function AdminWorkspacesIndex({
    workspaces,
    filters = {},
    industries = [],
}: Props) {
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const perPage = filters.per_page ?? workspaces.meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: workspacesIndex.url(),
        filters: {
            q: filters.q,
            industry: filters.industry,
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
            workspacesIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                industry: patch.industry ?? filters.industry ?? 'all',
                sort: patch.sort ?? filters.sort ?? 'created',
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

    const meta = workspaces.meta;

    return (
        <>
            <Head title="Businesses" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-workspaces"
            >
                <AdminPageHeader
                    title="Customers"
                    description="Businesses, users and paired TVs across the platform."
                    badge={null}
                    tabs={ADMIN_CUSTOMERS_TABS}
                    activeTab="businesses"
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-workspaces-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search businesses..."
                            className="pl-9"
                            aria-label="Search businesses"
                        />
                    </div>
                    {industries.length > 0 ? (
                        <select
                            className={cn(adminSelectClassName, 'sm:w-52')}
                            value={filters.industry ?? 'all'}
                            aria-label="Industry filter"
                            data-test="admin-workspaces-industry"
                            onChange={(event) =>
                                navigate({
                                    industry: event.target.value,
                                    page: 1,
                                })
                            }
                        >
                            <option value="all">All industries</option>
                            {industries.map((option) => (
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
                            All workspaces
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total across the platform.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead aria-sort={ariaSort('name')}>
                                            <SortableTableHeader
                                                label="Name"
                                                column="name"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('industry')}
                                        >
                                            <SortableTableHeader
                                                label="Industry"
                                                column="industry"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('members')}
                                        >
                                            <SortableTableHeader
                                                label="Members"
                                                column="members"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('screens')}
                                        >
                                            <SortableTableHeader
                                                label="TVs"
                                                column="screens"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('created')}
                                        >
                                            <SortableTableHeader
                                                label="Created"
                                                column="created"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Open
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {workspaces.data.map((workspace) => (
                                        <TableRow key={workspace.id}>
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={showWorkspace.url({
                                                        workspace: workspace.id,
                                                    })}
                                                    className="hover:underline"
                                                >
                                                    {workspace.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {workspace.industry_label ??
                                                    workspace.industry ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {workspace.owner ?? '—'}
                                                </div>
                                                {workspace.owner_email ? (
                                                    <div className="text-muted-foreground text-xs">
                                                        {workspace.owner_email}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {workspace.member_count ?? 0}
                                            </TableCell>
                                            <TableCell>
                                                {workspace.screen_count ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    workspace.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showWorkspace.url(
                                                            {
                                                                workspace:
                                                                    workspace.id,
                                                            },
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {workspaces.data.map((workspace) => (
                                <div
                                    key={workspace.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <div className="font-medium">
                                        {workspace.name}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {workspace.industry_label ??
                                            workspace.industry ??
                                            '—'}{' '}
                                        · {workspace.member_count ?? 0} members
                                        {workspace.screen_count != null
                                            ? ` · ${workspace.screen_count} screens`
                                            : ''}
                                    </div>
                                    <div className="text-sm">
                                        Owner: {workspace.owner ?? '—'}
                                    </div>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={showWorkspace.url({
                                                workspace: workspace.id,
                                            })}
                                        >
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>

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

AdminWorkspacesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Businesses',
            href: workspacesIndex(),
        },
    ],
};
