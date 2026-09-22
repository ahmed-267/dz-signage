/**
 * Chart colours / axis styles resolved from CSS variables so light and dark
 * themes stay in sync with the RMSignage design tokens (cyan primary).
 */

export function readCssVar(
    name: string,
    fallback: string,
    el: HTMLElement | null = typeof document !== 'undefined'
        ? document.documentElement
        : null,
): string {
    if (!el) {
        return fallback;
    }

    const value = getComputedStyle(el).getPropertyValue(name).trim();

    return value || fallback;
}

export type ChartTheme = {
    primary: string;
    success: string;
    warning: string;
    destructive: string;
    info: string;
    muted: string;
    mutedForeground: string;
    border: string;
    foreground: string;
    card: string;
    /** Prefer cyan / green / amber / rose — skip purple chart-4 for product charts. */
    series: [string, string, string, string, string];
};

export function getChartTheme(
    el: HTMLElement | null = typeof document !== 'undefined'
        ? document.documentElement
        : null,
): ChartTheme {
    const primary = readCssVar('--primary', '#0891b2', el);
    const success = readCssVar('--success', '#059669', el);
    const warning = readCssVar('--warning', '#d97706', el);
    const destructive = readCssVar('--destructive', '#e11d48', el);
    const info = readCssVar('--info', '#2563eb', el);
    const muted = readCssVar('--muted', '#f1f5f9', el);
    const mutedForeground = readCssVar('--muted-foreground', '#64748b', el);
    const border = readCssVar('--border', '#e2e8f0', el);
    const foreground = readCssVar('--foreground', '#0d1117', el);
    const card = readCssVar('--card', '#ffffff', el);

    return {
        primary,
        success,
        warning,
        destructive,
        info,
        muted,
        mutedForeground,
        border,
        foreground,
        card,
        series: [
            readCssVar('--chart-1', primary, el),
            readCssVar('--chart-2', success, el),
            readCssVar('--chart-3', warning, el),
            readCssVar('--chart-5', destructive, el),
            info,
        ],
    };
}

export const chartMargin = { top: 8, right: 8, left: 0, bottom: 0 } as const;

export function axisTickStyle(theme: ChartTheme): {
    fill: string;
    fontSize: number;
} {
    return { fill: theme.mutedForeground, fontSize: 11 };
}
