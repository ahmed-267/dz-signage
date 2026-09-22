import type { ReactNode } from 'react';
import { getChartTheme } from './chart-theme';

type TooltipPayloadItem = {
    name?: string;
    value?: number | string | null;
    color?: string;
    dataKey?: string | number;
    payload?: Record<string, unknown>;
};

type ChartTooltipProps = {
    active?: boolean;
    label?: string | number;
    payload?: TooltipPayloadItem[];
    /** Format the x-axis label (usually a date string). */
    formatLabel?: (label: string) => string;
    /** Format a series value; receives dataKey for multi-series tooltips. */
    formatValue?: (value: number, dataKey?: string) => string;
    unit?: string;
};

function defaultFormatLabel(label: string): string {
    if (/^\d{4}-\d{2}-\d{2}/.test(label)) {
        try {
            return new Intl.DateTimeFormat(undefined, {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
            }).format(new Date(`${label.slice(0, 10)}T12:00:00`));
        } catch {
            return label;
        }
    }

    return label;
}

function defaultFormatValue(value: number, unit?: string): string {
    const formatted = Number.isInteger(value)
        ? value.toLocaleString()
        : value.toLocaleString(undefined, { maximumFractionDigits: 1 });

    return unit ? `${formatted}${unit}` : formatted;
}

/**
 * Shared Recharts tooltip shell — date + value(+unit) with theme colours.
 */
export function ChartTooltip({
    active,
    label,
    payload,
    formatLabel = defaultFormatLabel,
    formatValue,
    unit,
}: ChartTooltipProps) {
    if (!active || !payload?.length) {
        return null;
    }

    const theme = getChartTheme();
    const title = formatLabel(String(label ?? ''));

    return (
        <div
            className="border-border/80 bg-card text-card-foreground rounded-lg border px-3 py-2 shadow-md"
            style={{ background: theme.card, borderColor: theme.border }}
        >
            {title ? (
                <p className="text-muted-foreground mb-1.5 font-mono text-[10px] tracking-wide uppercase">
                    {title}
                </p>
            ) : null}
            <ul className="space-y-1">
                {payload.map((item) => {
                    const raw = item.value;
                    const numeric =
                        typeof raw === 'number'
                            ? raw
                            : raw == null || raw === ''
                              ? null
                              : Number(raw);
                    const dataKey =
                        item.dataKey != null ? String(item.dataKey) : undefined;
                    const display =
                        numeric == null || Number.isNaN(numeric)
                            ? '—'
                            : formatValue
                              ? formatValue(numeric, dataKey)
                              : defaultFormatValue(numeric, unit);

                    return (
                        <li
                            key={`${item.name ?? dataKey}-${display}`}
                            className="flex items-center gap-2 text-sm"
                        >
                            <span
                                className="size-2 shrink-0 rounded-full"
                                style={{
                                    background: item.color ?? theme.primary,
                                }}
                                aria-hidden
                            />
                            <span className="text-muted-foreground truncate">
                                {item.name ?? dataKey}
                            </span>
                            <span className="ml-auto font-medium tabular-nums">
                                {display}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export function formatChartDateLabel(date: string): string {
    return defaultFormatLabel(date);
}

export type ChartTooltipRenderer = (props: ChartTooltipProps) => ReactNode;
