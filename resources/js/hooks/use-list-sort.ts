import { router } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    ariaSortValue,
    type SortDirection,
} from '@/components/ui/sortable-table-header';

export type ListSortFilters = Record<
    string,
    string | number | boolean | null | undefined
>;

function resolveDirection(
    direction: string | null | undefined,
    fallback: SortDirection,
): SortDirection {
    if (direction === 'asc' || direction === 'desc') {
        return direction;
    }

    return fallback;
}

function nextDirection(
    currentSort: string,
    column: string,
    currentDirection: SortDirection,
): SortDirection {
    return currentSort === column && currentDirection === 'asc'
        ? 'desc'
        : 'asc';
}

function cleanQuery(filters: ListSortFilters): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        if (typeof value === 'boolean') {
            query[key] = value ? 1 : 0;
            continue;
        }

        query[key] = value;
    }

    return query;
}

/**
 * Server-side list sorting via `sort` + `direction` query params.
 * Merges existing filters and resets pagination to page 1.
 */
export function useListSort(options: {
    baseUrl: string;
    filters: ListSortFilters;
    defaultSort?: string;
    defaultDirection?: SortDirection;
    preserveScroll?: boolean;
}): {
    currentSort: string;
    currentDirection: SortDirection;
    onSort: (column: string) => void;
    ariaSort: (column: string) => 'ascending' | 'descending' | 'none';
} {
    const {
        baseUrl,
        filters,
        defaultSort = 'updated',
        defaultDirection = 'desc',
        preserveScroll = true,
    } = options;

    const currentSort =
        typeof filters.sort === 'string' && filters.sort !== ''
            ? filters.sort
            : defaultSort;
    const currentDirection = resolveDirection(
        typeof filters.direction === 'string' ? filters.direction : undefined,
        defaultDirection,
    );

    const onSort = useCallback(
        (column: string) => {
            router.get(
                baseUrl,
                {
                    ...cleanQuery(filters),
                    sort: column,
                    direction: nextDirection(
                        currentSort,
                        column,
                        currentDirection,
                    ),
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll,
                },
            );
        },
        [baseUrl, filters, currentSort, currentDirection, preserveScroll],
    );

    const ariaSort = useCallback(
        (column: string) =>
            ariaSortValue(column, currentSort, currentDirection),
        [currentSort, currentDirection],
    );

    return {
        currentSort,
        currentDirection,
        onSort,
        ariaSort,
    };
}
