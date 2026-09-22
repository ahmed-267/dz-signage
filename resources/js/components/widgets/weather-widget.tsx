import { WidgetShell } from '@/components/widgets/widget-shell';
import { DEMO_WEATHER } from '@/lib/widgets/demo-data';
import type { WeatherDemoData, WeatherWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';
import { cn } from '@/lib/utils';

type WeatherWidgetProps = {
    config: WeatherWidgetConfig;
    data?: WeatherDemoData | Record<string, unknown> | null;
    elementProps?: LayoutElementProps;
    useDemo?: boolean;
};

function asWeather(
    data: WeatherDemoData | Record<string, unknown> | null | undefined,
    fallback: WeatherDemoData,
): WeatherDemoData {
    if (!data || typeof data !== 'object') {
        return fallback;
    }
    const raw = data as Record<string, unknown>;
    return {
        location:
            typeof raw.location === 'string' ? raw.location : fallback.location,
        temp: typeof raw.temp === 'number' ? raw.temp : fallback.temp,
        units: raw.units === 'f' ? 'f' : 'c',
        condition:
            typeof raw.condition === 'string'
                ? raw.condition
                : fallback.condition,
        high: typeof raw.high === 'number' ? raw.high : fallback.high,
        low: typeof raw.low === 'number' ? raw.low : fallback.low,
        icon: typeof raw.icon === 'string' ? raw.icon : fallback.icon,
    };
}

export function WeatherWidget({
    config,
    data,
    elementProps,
    useDemo = true,
}: WeatherWidgetProps) {
    const weather = asWeather(data ?? (useDemo ? DEMO_WEATHER : null), {
        ...DEMO_WEATHER,
        location: config.location,
        units: config.units,
    });
    const unitLabel = (config.units === 'f' ? 'F' : 'C') as string;
    const inline = config.layout === 'inline';

    return (
        <WidgetShell
            props={elementProps}
            className={cn(
                'gap-1',
                inline ? 'items-center justify-center' : 'justify-center',
            )}
        >
            <div
                className="opacity-75"
                style={{ fontSize: '0.38em', fontWeight: 500 }}
            >
                {weather.location || config.location}
            </div>
            <div
                className={cn(
                    'flex',
                    inline ? 'flex-row items-baseline gap-3' : 'flex-col gap-1',
                )}
            >
                {config.showTemp ? (
                    <div className="leading-none tabular-nums">
                        {Math.round(weather.temp)}°{unitLabel}
                    </div>
                ) : null}
                {config.showCondition ? (
                    <div
                        className="opacity-85"
                        style={{ fontSize: '0.42em', fontWeight: 500 }}
                    >
                        {weather.condition}
                    </div>
                ) : null}
            </div>
            {config.showHighLow ? (
                <div
                    className="opacity-70"
                    style={{ fontSize: '0.36em', fontWeight: 500 }}
                >
                    H {Math.round(weather.high)}° · L {Math.round(weather.low)}°
                </div>
            ) : null}
        </WidgetShell>
    );
}
