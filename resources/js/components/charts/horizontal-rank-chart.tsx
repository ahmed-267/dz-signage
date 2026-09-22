import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartEmpty } from './chart-empty';
import { axisTickStyle, chartMargin, getChartTheme } from './chart-theme';
import { ChartTooltip } from './chart-tooltip';

export type RankDatum = {
    id: string | number;
    label: string;
    value: number;
};

type HorizontalRankChartProps = {
    data: RankDatum[];
    valueLabel?: string;
    formatValue?: (value: number) => string;
    height?: number;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
    color?: string;
};

function truncateLabel(label: string, max = 22): string {
    if (label.length <= max) {
        return label;
    }

    return `${label.slice(0, max - 1)}…`;
}

/**
 * Horizontal bars for top content / screen comparison.
 */
export function HorizontalRankChart({
    data,
    valueLabel = 'Value',
    formatValue = (v) => v.toLocaleString(),
    height,
    emptyTitle = 'Nothing ranked yet',
    emptyDescription = 'Rankings appear when there is activity in this range.',
    className,
    color,
}: HorizontalRankChartProps) {
    const theme = getChartTheme();
    const rows = data.filter((row) => row.value > 0);

    if (rows.length === 0) {
        return (
            <ChartEmpty
                title={emptyTitle}
                description={emptyDescription}
                height={height ?? 200}
                className={className}
            />
        );
    }

    const chartHeight = height ?? Math.max(160, rows.length * 36 + 24);

    return (
        <div
            className={className}
            style={{ width: '100%', height: chartHeight }}
            data-slot="horizontal-rank-chart"
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={rows}
                    layout="vertical"
                    margin={{ ...chartMargin, left: 8, right: 16 }}
                >
                    <CartesianGrid
                        stroke={theme.border}
                        strokeDasharray="3 3"
                        horizontal={false}
                    />
                    <XAxis
                        type="number"
                        tick={axisTickStyle(theme)}
                        axisLine={false}
                        tickLine={false}
                        allowDecimals={false}
                    />
                    <YAxis
                        type="category"
                        dataKey="label"
                        width={120}
                        tickFormatter={(v: string) => truncateLabel(v)}
                        tick={axisTickStyle(theme)}
                        axisLine={false}
                        tickLine={false}
                    />
                    <Tooltip
                        content={
                            <ChartTooltip
                                formatLabel={(label) => String(label)}
                                formatValue={formatValue}
                            />
                        }
                    />
                    <Bar
                        dataKey="value"
                        name={valueLabel}
                        fill={color ?? theme.primary}
                        radius={[0, 3, 3, 0]}
                        isAnimationActive={false}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
