import { Head, Link, router } from '@inertiajs/react';
import { MapPin, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
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
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { locations as locationsIndex } from '@/routes/app';
import locationRoutes from '@/routes/app/locations';
import type { LocationsIndexProps } from '@/types/location';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

type FilterPatch = Partial<{
    q: string;
    status: string;
    sort: string;
    direction: string;
    per_page: number;
    page: number;
}>;

export default function LocationsIndex({
    locations: paginated,
    filters,
    can_manage: canManage,
}: LocationsIndexProps) {
    const [searchInput, setSearchInput] = useState(filters.q);

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: locationsIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
        },
        defaultSort: 'name',
        defaultDirection: 'asc',
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
            locationsIndex.url(),
            {
                q: patch.q ?? filters.q,
                status: patch.status ?? filters.status,
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
                per_page: patch.per_page ?? filters.per_page,
                ...(patch.page ? { page: patch.page } : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }

    const rows = paginated.data;
    const meta = paginated.meta;

    return (
        <>
            <Head title="Locations" />
            <div
                className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="locations-index"
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Locations
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            Group TVs by site.
                        </p>
                    </div>
                    {canManage ? (
                        <Button
                            type="button"
                            asChild
                            data-test="locations-create"
                        >
                            <Link href={locationRoutes.create.url()}>
                                <Plus className="size-4" />
                                Add Location
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            className="pl-9"
                            placeholder="Search locations…"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            data-test="locations-search"
                        />
                    </div>
                    <select
                        className={cn(selectClassName, 'sm:w-40')}
                        value={filters.status}
                        onChange={(e) =>
                            navigate({ status: e.target.value, page: 1 })
                        }
                        data-test="locations-status"
                    >
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All</option>
                    </select>
                </div>

                {rows.length === 0 ? (
                    <EmptyState
                        icon={MapPin}
                        title="No locations yet"
                        description={
                            canManage
                                ? 'Create a location to organise screens by site.'
                                : 'Locations will appear here once they are added.'
                        }
                        action={
                            canManage ? (
                                <Button type="button" asChild>
                                    <Link href={locationRoutes.create.url()}>
                                        <Plus className="size-4" />
                                        Add Location
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="border-border overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead aria-sort={ariaSort('name')}>
                                        <SortableTableHeader
                                            label="Name"
                                            column="name"
                                            currentSort={currentSort}
                                            currentDirection={currentDirection}
                                            onSort={onSort}
                                        />
                                    </TableHead>
                                    <TableHead
                                        className="hidden md:table-cell"
                                        aria-sort={ariaSort('city')}
                                    >
                                        <SortableTableHeader
                                            label="City"
                                            column="city"
                                            currentSort={currentSort}
                                            currentDirection={currentDirection}
                                            onSort={onSort}
                                        />
                                    </TableHead>
                                    <TableHead aria-sort={ariaSort('screens')}>
                                        <SortableTableHeader
                                            label={ProductLabels.displayPlural}
                                            column="screens"
                                            currentSort={currentSort}
                                            currentDirection={currentDirection}
                                            onSort={onSort}
                                        />
                                    </TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Online
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Timezone
                                    </TableHead>
                                    <TableHead
                                        className="hidden sm:table-cell"
                                        aria-sort={ariaSort('updated')}
                                    >
                                        <SortableTableHeader
                                            label="Updated"
                                            column="updated"
                                            currentSort={currentSort}
                                            currentDirection={currentDirection}
                                            onSort={onSort}
                                        />
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((location) => (
                                    <TableRow
                                        key={location.id}
                                        data-test={`location-row-${location.id}`}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                locationRoutes.show.url(
                                                    location.id,
                                                ),
                                            )
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                {location.name}
                                                {location.is_archived ? (
                                                    <Badge variant="secondary">
                                                        Archived
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden md:table-cell">
                                            {location.city ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {location.screen_count}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {location.online_count}/
                                            {location.screen_count}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden lg:table-cell">
                                            {location.timezone}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden sm:table-cell">
                                            {location.updated_at
                                                ? new Date(
                                                      location.updated_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {meta.last_page > 1 || meta.total > (filters.per_page ?? 20) ? (
                    <ListPagination
                        page={meta.current_page}
                        pageCount={meta.last_page}
                        total={meta.total}
                        from={meta.from}
                        to={meta.to}
                        perPage={filters.per_page}
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
