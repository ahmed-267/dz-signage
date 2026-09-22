import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ListPaginationProps = {
    page: number;
    pageCount: number;
    total: number;
    from: number | null;
    to: number | null;
    perPage: number;
    perPageOptions?: number[];
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    className?: string;
    'data-test'?: string;
};

/**
 * Leads-style list pagination: range summary, per-page select, first/prev/next/last.
 */
export function ListPagination({
    page,
    pageCount,
    total,
    from,
    to,
    perPage,
    perPageOptions = [10, 20, 50],
    onPageChange,
    onPerPageChange,
    className,
    'data-test': dataTest = 'list-pagination',
}: ListPaginationProps) {
    const safePageCount = Math.max(pageCount, 1);

    return (
        <div
            data-test={dataTest}
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-muted-foreground text-sm" data-test={`${dataTest}-summary`}>
                Showing {from ?? 0}–{to ?? 0} of {total}
            </p>

            <div className="flex flex-wrap items-center gap-3">
                <label className="text-muted-foreground flex items-center gap-2 text-sm">
                    <span>Per page</span>
                    <select
                        className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                        value={perPage}
                        aria-label="Rows per page"
                        data-test={`${dataTest}-per-page`}
                        onChange={(e) =>
                            onPerPageChange(Number.parseInt(e.target.value, 10))
                        }
                    >
                        {perPageOptions.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </label>

                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={page <= 1}
                        aria-label="First page"
                        data-test={`${dataTest}-first`}
                        onClick={() => onPageChange(1)}
                    >
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={page <= 1}
                        aria-label="Previous page"
                        data-test={`${dataTest}-prev`}
                        onClick={() => onPageChange(page - 1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span
                        className="text-muted-foreground px-2 font-mono text-xs"
                        data-test={`${dataTest}-page`}
                    >
                        {page} / {safePageCount}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={page >= safePageCount}
                        aria-label="Next page"
                        data-test={`${dataTest}-next`}
                        onClick={() => onPageChange(page + 1)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={page >= safePageCount}
                        aria-label="Last page"
                        data-test={`${dataTest}-last`}
                        onClick={() => onPageChange(safePageCount)}
                    >
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
