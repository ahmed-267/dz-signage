import { BarChart3 } from 'lucide-react';
import { cn } from '@/lib/utils';

type ChartEmptyProps = {
    title?: string;
    description?: string;
    className?: string;
    height?: number;
};

/**
 * Honest empty state for charts — no placeholder series.
 */
export function ChartEmpty({
    title = 'No data yet',
    description = 'This chart will populate when TVs report telemetry in the selected range.',
    className,
    height = 220,
}: ChartEmptyProps) {
    return (
        <div
            className={cn(
                'text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-lg px-4 text-center',
                className,
            )}
            style={{ minHeight: height }}
            data-slot="chart-empty"
            role="status"
        >
            <BarChart3 className="size-5 opacity-60" aria-hidden />
            <p className="text-foreground text-sm font-medium">{title}</p>
            <p className="max-w-xs text-xs text-pretty">{description}</p>
        </div>
    );
}
