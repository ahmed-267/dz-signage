import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    healthBadgeVariant,
    ScreenStateBadges,
} from '@/components/screens/screen-state-badges';
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
import { formatLastSeen } from '@/lib/format-relative-time';
import { cn } from '@/lib/utils';
import { screens as screensIndex } from '@/routes/admin';
import { show as showScreen } from '@/routes/admin/screens';
import type { AdminScreenFilter, AdminScreensIndexProps } from '@/types/screen';

const FILTERS: { id: AdminScreenFilter; label: string }[] = [
    { id: 'all', label: 'All' },
    { id: 'online', label: 'Online' },
    { id: 'offline', label: 'Offline' },
    { id: 'active', label: 'Active' },
    { id: 'inactive', label: 'Inactive' },
    { id: 'connected', label: 'Connected' },
    { id: 'disconnected', label: 'Disconnected' },
    { id: 'attention', label: 'Needs attention' },
];

type FilterPatch = Partial<{
    q: string;
    filter: string;
    sort: string;
    direction: string;
    per_page: number;
    page: number;
}>;

export default function AdminScreensIndex({
    screens,
    filters,
}: AdminScreensIndexProps) {
    const [searchInput, setSearchInput] = useState(filters.q);
    const perPage = filters.per_page ?? screens.meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: screensIndex.url(),
        filters: {
            q: filters.q,
            filter: filters.filter,
            sort: filters.sort,
            direction: filters.direction,
            per_page: perPage,
        },
        defaultSort: 'created',
        defaultDirection: 'desc',
    });

    useEffect(() => {
        setSearchInput(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === filters.q) {
                return;
            }
            navigate({ q: searchInput, page: 1 });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    function navigate(patch: FilterPatch) {
        router.get(
            screensIndex.url(),
            {
                q: patch.q ?? filters.q,
                filter: patch.filter ?? filters.filter,
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
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

    const meta = screens.meta;

    return (
        <>
            <Head title="TVs" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <div className="mb-1">
                        <Badge variant="info">Super Admin</Badge>
                    </div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        TVs
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Platform-wide TV directory (read-only).
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <div className="relative sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-screens-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search TVs or workspaces..."
                            className="pl-9"
                            aria-label="Search TVs"
                        />
                    </div>

                    <div className="flex gap-1.5 overflow-x-auto pb-1">
                        {FILTERS.map((chip) => {
                            const active = filters.filter === chip.id;
                            return (
                                <button
                                    key={chip.id}
                                    type="button"
                                    data-test={`admin-screens-filter-${chip.id}`}
                                    onClick={() =>
                                        navigate({
                                            filter: chip.id,
                                            page: 1,
                                        })
                                    }
                                    aria-pressed={active}
                                    className={cn(
                                        'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border text-muted-foreground hover:text-foreground hover:border-primary/40',
                                    )}
                                >
                                    {chip.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All screens
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total across the platform.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {screens.data.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="admin-screens-empty"
                            >
                                No TVs match this search.
                            </p>
                        ) : null}

                        <div
                            className="hidden md:block"
                            data-test="admin-screens-table"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead aria-sort={ariaSort('name')}>
                                            <SortableTableHeader
                                                label="Screen"
                                                column="name"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('workspace')}
                                        >
                                            <SortableTableHeader
                                                label="Business"
                                                column="workspace"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Health</TableHead>
                                        <TableHead>Current content</TableHead>
                                        <TableHead>Last seen</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {screens.data.map((screen) => (
                                        <TableRow
                                            key={screen.id}
                                            data-test={`admin-screen-row-${screen.id}`}
                                        >
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={showScreen.url({
                                                        screen: screen.id,
                                                    })}
                                                    className="hover:underline"
                                                >
                                                    {screen.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {screen.workspace_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <ScreenStateBadges
                                                    id={screen.id}
                                                    operationalStatus={
                                                        screen.operational_status
                                                    }
                                                    pairingState={
                                                        screen.pairing_state
                                                    }
                                                    networkState={
                                                        screen.network_state
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={healthBadgeVariant(
                                                        screen.health,
                                                    )}
                                                >
                                                    {screen.health_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="max-w-56 truncate text-sm">
                                                {screen.current_design_name ?? (
                                                    <span className="text-muted-foreground">
                                                        No content
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatLastSeen(
                                                    screen.last_seen_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {screens.data.map((screen) => (
                                <div
                                    key={screen.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <Link
                                        href={showScreen.url({
                                            screen: screen.id,
                                        })}
                                        className="font-medium hover:underline"
                                    >
                                        {screen.name}
                                    </Link>
                                    <div className="text-muted-foreground text-sm">
                                        {screen.workspace_name ?? '—'}
                                    </div>
                                    <ScreenStateBadges
                                        id={screen.id}
                                        operationalStatus={
                                            screen.operational_status
                                        }
                                        pairingState={screen.pairing_state}
                                        networkState={screen.network_state}
                                    />
                                    <div className="text-muted-foreground text-xs">
                                        {screen.health_label} ·{' '}
                                        {screen.current_design_name ??
                                            'No content'}{' '}
                                        · Last seen{' '}
                                        {formatLastSeen(screen.last_seen_at)}
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

AdminScreensIndex.layout = {
    breadcrumbs: [
        {
            title: 'TVs',
            href: screensIndex(),
        },
    ],
};
