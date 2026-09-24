import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_OPERATIONS_TABS } from '@/components/admin/admin-section-header';
import { Badge } from '@/components/ui/badge';
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
import { formatRelativeDate } from '@/lib/format-bytes';
import { cn } from '@/lib/utils';
import { publishing_jobs as publishingJobs } from '@/routes/admin';

type Row = {
    id: number;
    workspace_name: string | null;
    screen_name: string | null;
    content_name: string | null;
    content_type_label: string;
    version_number: number | null;
    status: string;
    status_label: string;
    sync_state: string | null;
    sync_label: string | null;
    deployed_by_name: string | null;
    deployed_at: string | null;
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
    deployments: {
        data: Row[];
        meta: PaginatedMeta;
    };
    filters: {
        q: string;
        status: string;
        per_page?: number;
    };
    statuses: { value: string; label: string }[];
};

type FilterPatch = Partial<{
    q: string;
    status: string;
    per_page: number;
    page: number;
}>;

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);

export default function AdminPublishingJobs({
    deployments,
    filters,
    statuses,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const meta = deployments.meta;
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (search === filters.q) {
                return;
            }
            navigate({ q: search, page: 1 });
        }, 300);
        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [search]);

    function navigate(patch: FilterPatch) {
        router.get(
            publishingJobs.url(),
            {
                q: patch.q ?? filters.q,
                status: patch.status ?? filters.status,
                per_page: patch.per_page ?? perPage,
                ...(patch.page ? { page: patch.page } : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Publishing Jobs" />
            <div
                className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6"
                data-test="admin-publishing-jobs"
            >
                <AdminPageHeader
                    title="Operations"
                    description="Read-only Deployment activity across workspaces. Sync state comes from Player heartbeats."
                    badge={null}
                    tabs={ADMIN_OPERATIONS_TABS}
                    activeTab="publishing"
                />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                        <Input
                            className="pl-9"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search workspace, screen, content"
                            data-test="admin-publishing-search"
                        />
                    </div>
                    <select
                        className={cn(selectClassName, 'sm:w-44')}
                        value={filters.status}
                        aria-label="Status filter"
                        onChange={(e) =>
                            navigate({
                                status: e.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="all">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="border-border overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Workspace</TableHead>
                                <TableHead>Screen</TableHead>
                                <TableHead>Content</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Sync
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    When
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {deployments.data.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell>{row.workspace_name}</TableCell>
                                    <TableCell>{row.screen_name}</TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="text-sm font-medium">
                                                {row.content_name ?? '—'}
                                            </p>
                                            <p className="text-muted-foreground font-mono text-xs">
                                                {row.content_type_label} v
                                                {row.version_number ?? '—'}
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {row.status_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        {row.sync_label ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground hidden text-sm lg:table-cell">
                                        {row.deployed_by_name ?? '—'}
                                        <div className="font-mono text-xs">
                                            {row.deployed_at
                                                ? formatRelativeDate(
                                                      row.deployed_at,
                                                  )
                                                : '—'}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
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
            </div>
        </>
    );
}
