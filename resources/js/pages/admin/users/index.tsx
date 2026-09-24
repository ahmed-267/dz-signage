import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_CUSTOMERS_TABS } from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';
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
import { users as usersIndex } from '@/routes/admin';
import { show as showUser } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    workspace_count?: number;
    company?: string | null;
    companies?: string[];
    membership_summaries?: string[];
    is_admin?: boolean;
    platform_role?: string | null;
    platform_role_label?: string | null;
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
    users: Paginated<UserRow>;
    filters?: {
        q?: string;
        platform_role?: string;
        workspace_id?: number | null;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
    platform_roles?: FilterOption[];
    workspaces?: FilterOption[];
};

type FilterPatch = Partial<{
    q: string;
    platform_role: string;
    workspace_id: string;
    sort: string;
    direction: string;
    per_page: number;
    page: number;
}>;

export default function AdminUsersIndex({
    users,
    filters = {},
    platform_roles = [],
    workspaces = [],
}: Props) {
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const perPage = filters.per_page ?? users.meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: usersIndex.url(),
        filters: {
            q: filters.q,
            platform_role: filters.platform_role,
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
            usersIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                platform_role:
                    patch.platform_role ?? filters.platform_role ?? 'all',
                workspace_id:
                    patch.workspace_id !== undefined
                        ? patch.workspace_id
                        : (filters.workspace_id ?? ''),
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

    const meta = users.meta;

    return (
        <>
            <Head title="Users" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-users"
            >
                <AdminPageHeader
                    title="Customers"
                    description="Businesses, users and paired TVs across the platform."
                    badge={null}
                    tabs={ADMIN_CUSTOMERS_TABS}
                    activeTab="users"
                />

                <div className="flex flex-col gap-3 lg:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-users-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search name or email..."
                            className="pl-9"
                            aria-label="Search users"
                        />
                    </div>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-56')}
                        value={
                            filters.workspace_id
                                ? String(filters.workspace_id)
                                : ''
                        }
                        aria-label="Company filter"
                        data-test="admin-users-company"
                        onChange={(event) =>
                            navigate({
                                workspace_id: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">All companies</option>
                        {workspaces.map((workspace) => (
                            <option
                                key={workspace.value}
                                value={workspace.value}
                            >
                                {workspace.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className={cn(adminSelectClassName, 'lg:w-48')}
                        value={filters.platform_role ?? 'all'}
                        aria-label="Platform role filter"
                        data-test="admin-users-platform-role"
                        onChange={(event) =>
                            navigate({
                                platform_role: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All roles</option>
                        <option value="none">User</option>
                        {platform_roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All users
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
                                            aria-sort={ariaSort('email')}
                                        >
                                            <SortableTableHeader
                                                label="Email"
                                                column="email"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>Company</TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('workspaces')}
                                        >
                                            <SortableTableHeader
                                                label="Businesses"
                                                column="workspaces"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead aria-sort={ariaSort('role')}>
                                            <SortableTableHeader
                                                label="Role"
                                                column="role"
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
                                                label="Joined"
                                                column="created"
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
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={showUser.url(user.id)}
                                                    className="hover:underline"
                                                >
                                                    {user.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell className="max-w-[14rem] truncate">
                                                {user.company ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {user.workspace_count ?? 0}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    {user.platform_role_label ? (
                                                        <Badge variant="info">
                                                            {
                                                                user.platform_role_label
                                                            }
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            No platform role
                                                        </Badge>
                                                    )}
                                                    {(
                                                        user.membership_summaries ??
                                                        []
                                                    ).length > 0 ? (
                                                        <span className="text-muted-foreground text-xs">
                                                            {user.membership_summaries?.join(
                                                                '; ',
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">
                                                            {user.workspace_count ??
                                                                0}{' '}
                                                            Business memberships
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {user.created_at
                                                    ? new Date(
                                                          user.created_at,
                                                      ).toLocaleDateString()
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {users.data.map((user) => (
                                <div
                                    key={user.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <Link
                                        href={showUser.url(user.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {user.name}
                                    </Link>
                                    <div className="text-muted-foreground text-sm">
                                        {user.email}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {user.company ?? 'No company'}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {user.platform_role_label ? (
                                            <Badge variant="info">
                                                {user.platform_role_label}
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                No platform role
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground text-xs">
                                            {(
                                                user.membership_summaries ?? []
                                            ).join('; ') ||
                                                `${user.workspace_count ?? 0} Business memberships`}
                                        </span>
                                    </div>
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
                                onPerPageChange={(nextPerPage) =>
                                    navigate({
                                        per_page: nextPerPage,
                                        page: 1,
                                    })
                                }
                            />
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
};
