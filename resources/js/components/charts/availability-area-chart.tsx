import { useId } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartEmpty } from './chart-empty';
import { axisTickStyle, chartMargin, getChartTheme } from './chart-theme';
import { ChartTooltip, formatChartDateLabel } from './chart-tooltip';

export type AvailabilityPoint = {
    date: string;
    online_seconds?: number;
    offline_seconds?: number;
    availability_percent: number | null;
};

type AvailabilityAreaChartProps = {
    data: AvailabilityPoint[];
    height?: number;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
};

function shortDateTick(value: string): string {
    if (value.length >= 10) {
        return value.slice(5);
    }

    return value;
}

/**
 * Availability % over time from heartbeat-derived daily stats.
 */
export function AvailabilityAreaChart({
    data,
    height = 240,
    emptyTitle = 'No availability data',
    emptyDescription = 'Daily availability appears once TVs report heartbeats.',
    className,
}: AvailabilityAreaChartProps) {
    const gradientId = useId().replace(/:/g, '');
    const theme = getChartTheme();

    const hasSignal = data.some(
        (row) =>
            row.availability_percent != null ||
            (row.online_seconds ?? 0) + (row.offline_seconds ?? 0) > 0,
    );

    if (!hasSignal) {
        return (
            <ChartEmpty
                title={emptyTitle}
                description={emptyDescription}
                height={height}
                className={className}
            />
        );
    }

    const chartData = data.map((row) => ({
        ...row,
        availability_percent: row.availability_percent ?? null,
    }));

    return (
        <div
            className={className}
            style={{ width: '100%', height }}
            data-slot="availability-area-chart"
        >
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={chartMargin}>
                    <defs>
                        <linearGradient
                            id={gradientId}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor={theme.primary}
                                stopOpacity={0.35}
                            />
                            <stop
                                offset="100%"
                                stopColor={theme.primary}
                                stopOpacity={0.02}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        stroke={theme.border}
                        strokeDasharray="3 3"
                        vertical={false}
                    />
                    <XAxis
                        dataKey="date"
                        tickFormatter={shortDateTick}
                        tick={axisTickStyle(theme)}
                        axisLine={false}
                        tickLine={false}
                        minTickGap={24}
                    />
                    <YAxis
                        domain={[0, 100]}
                        tickFormatter={(v: number) => `${v}%`}
                        tick={axisTickStyle(theme)}
                        axisLine={false}
                        tickLine={false}
                        width={40}
                    />
                    <Tooltip
                        content={
                            <ChartTooltip
                                formatLabel={formatChartDateLabel}
                                formatValue={(v) => `${v}%`}
                            />
                        }
                    />
                    <Area
                        type="monotone"
                        dataKey="availability_percent"
                        name="Availability"
                        stroke={theme.primary}
                        strokeWidth={2}
                        fill={`url(#${gradientId})`}
                        connectNulls={false}
                        isAnimationActive={false}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
