import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type ChartSkeletonProps = {
    className?: string;
    height?: number;
};

/**
 * Loading placeholder matching chart card proportions.
 */
export function ChartSkeleton({ className, height = 220 }: ChartSkeletonProps) {
    return (
        <div
            className={cn('flex flex-col gap-3', className)}
            style={{ minHeight: height }}
            data-slot="chart-skeleton"
            aria-hidden
        >
            <div
                className="flex items-end gap-2"
                style={{ height: height - 40 }}
            >
                {Array.from({ length: 8 }).map((_, i) => (
                    <Skeleton
                        key={i}
                        className="flex-1 rounded-t-sm"
                        style={{ height: `${30 + ((i * 17) % 60)}%` }}
                    />
                ))}
            </div>
            <Skeleton className="h-3 w-1/3" />
        </div>
    );
}
