import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { ChartEmpty } from './chart-empty';
import { getChartTheme } from './chart-theme';
import { ChartTooltip } from './chart-tooltip';

export type StatusSlice = {
    key: string;
    label: string;
    value: number;
    color?: string;
};

type StatusDonutChartProps = {
    data: StatusSlice[];
    height?: number;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
    showLegend?: boolean;
};

/**
 * Donut breakdown for screen health or publishing status counts.
 */
export function StatusDonutChart({
    data,
    height = 220,
    emptyTitle = 'No status data',
    emptyDescription = 'Status breakdown appears when TVs or Deployments exist.',
    className,
    showLegend = true,
}: StatusDonutChartProps) {
    const theme = getChartTheme();
    const slices = data.filter((row) => row.value > 0);
    const total = data.reduce((sum, row) => sum + row.value, 0);

    const palette = [
        theme.success,
        theme.warning,
        theme.mutedForeground,
        theme.destructive,
        theme.info,
        theme.primary,
    ];

    if (total === 0 || slices.length === 0) {
        return (
            <ChartEmpty
                title={emptyTitle}
                description={emptyDescription}
                height={height}
                className={className}
            />
        );
    }

    const coloured = slices.map((slice, i) => ({
        ...slice,
        color: slice.color ?? palette[i % palette.length],
    }));

    return (
        <div className={className} data-slot="status-donut-chart">
            <div style={{ width: '100%', height }} className="relative">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={coloured}
                            dataKey="value"
                            nameKey="label"
                            cx="50%"
                            cy="50%"
                            innerRadius="58%"
                            outerRadius="82%"
                            paddingAngle={2}
                            stroke="none"
                            isAnimationActive={false}
                        >
                            {coloured.map((slice) => (
                                <Cell key={slice.key} fill={slice.color} />
                            ))}
                        </Pie>
                        <Tooltip
                            content={
                                <ChartTooltip
                                    formatLabel={(label) => String(label)}
                                    formatValue={(v) => v.toLocaleString()}
                                />
                            }
                        />
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                        Total
                    </p>
                    <p className="font-display text-2xl font-semibold tabular-nums">
                        {total}
                    </p>
                </div>
            </div>
            {showLegend ? (
                <ul className="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                    {coloured.map((slice) => (
                        <li
                            key={slice.key}
                            className="flex items-center gap-1.5 text-xs"
                        >
                            <span
                                className="size-2 rounded-full"
                                style={{ background: slice.color }}
                                aria-hidden
                            />
                            <span className="text-muted-foreground">
                                {slice.label}
                            </span>
                            <span className="font-medium tabular-nums">
                                {slice.value}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
