import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatDuration } from '@/lib/format-duration';
import { ChartEmpty } from './chart-empty';
import { axisTickStyle, chartMargin, getChartTheme } from './chart-theme';
import { ChartTooltip, formatChartDateLabel } from './chart-tooltip';

export type PlaybackPoint = {
    date: string;
    plays: number;
    playback_seconds: number;
};

type PlaybackBarChartProps = {
    data: PlaybackPoint[];
    /** Which metric to plot. */
    metric?: 'plays' | 'playback_seconds';
    /** Override series name in tooltip / legend. */
    seriesName?: string;
    variant?: 'bar' | 'line';
    height?: number;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
};

function shortDateTick(value: string): string {
    return value.length >= 10 ? value.slice(5) : value;
}

/**
 * Daily content starts or playback duration.
 */
export function PlaybackBarChart({
    data,
    metric = 'plays',
    seriesName,
    variant = 'bar',
    height = 240,
    emptyTitle = 'No playback yet',
    emptyDescription = 'Playback appears when Players report content starts.',
    className,
}: PlaybackBarChartProps) {
    const theme = getChartTheme();
    const hasSignal = data.some((row) =>
        metric === 'plays' ? row.plays > 0 : row.playback_seconds > 0,
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

    const name =
        seriesName ?? (metric === 'plays' ? 'Content starts' : 'Playback time');
    const formatValue = (v: number) =>
        metric === 'plays' ? v.toLocaleString() : formatDuration(v);

    const common = (
        <>
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
                tick={axisTickStyle(theme)}
                axisLine={false}
                tickLine={false}
                width={44}
                tickFormatter={(v: number) =>
                    metric === 'plays' ? String(v) : formatDuration(v)
                }
                allowDecimals={false}
            />
            <Tooltip
                content={
                    <ChartTooltip
                        formatLabel={formatChartDateLabel}
                        formatValue={formatValue}
                    />
                }
            />
        </>
    );

    return (
        <div
            className={className}
            style={{ width: '100%', height }}
            data-slot="playback-bar-chart"
        >
            <ResponsiveContainer width="100%" height="100%">
                {variant === 'line' ? (
                    <LineChart data={data} margin={chartMargin}>
                        {common}
                        <Line
                            type="monotone"
                            dataKey={metric}
                            name={name}
                            stroke={theme.series[1]}
                            strokeWidth={2}
                            dot={false}
                            isAnimationActive={false}
                        />
                    </LineChart>
                ) : (
                    <BarChart data={data} margin={chartMargin}>
                        {common}
                        <Bar
                            dataKey={metric}
                            name={name}
                            fill={theme.series[1]}
                            radius={[3, 3, 0, 0]}
                            isAnimationActive={false}
                        />
                    </BarChart>
                )}
            </ResponsiveContainer>
        </div>
    );
}
