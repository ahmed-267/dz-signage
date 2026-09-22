import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';

export type SortDirection = 'asc' | 'desc';

export type SortableTableHeaderProps = {
    label: string;
    column: string;
    currentSort: string;
    currentDirection: SortDirection;
    onSort: (column: string) => void;
    className?: string;
};

export function SortableTableHeader({
    label,
    column,
    currentSort,
    currentDirection,
    onSort,
    className,
}: SortableTableHeaderProps) {
    const active = currentSort === column;
    const ariaLabel = active
        ? currentDirection === 'asc'
            ? `${label}: sorted ascending. Activate to sort descending.`
            : `${label}: sorted descending. Activate to sort ascending.`
        : `${label}: activate to sort ascending.`;

    return (
        <button
            type="button"
            onClick={() => onSort(column)}
            className={cn(
                'inline-flex items-center gap-1 font-medium transition-colors',
                active
                    ? 'text-foreground'
                    : 'text-muted-foreground hover:text-foreground',
                className,
            )}
            aria-label={ariaLabel}
            title={
                active
                    ? currentDirection === 'asc'
                        ? 'Sorted ascending'
                        : 'Sorted descending'
                    : 'Sort'
            }
        >
            <span>{label}</span>
            {active ? (
                currentDirection === 'asc' ? (
                    <ArrowUp className="size-3.5 shrink-0" aria-hidden />
                ) : (
                    <ArrowDown className="size-3.5 shrink-0" aria-hidden />
                )
            ) : (
                <ArrowUpDown
                    className="size-3.5 shrink-0 opacity-40"
                    aria-hidden
                />
            )}
        </button>
    );
}

export function ariaSortValue(
    column: string,
    currentSort: string,
    currentDirection: SortDirection,
): 'ascending' | 'descending' | 'none' {
    if (currentSort !== column) {
        return 'none';
    }

    return currentDirection === 'asc' ? 'ascending' : 'descending';
}
