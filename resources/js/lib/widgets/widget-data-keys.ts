import { md5 } from '@/lib/widgets/md5';
import type {
    CalendarWidgetConfig,
    NewsWidgetConfig,
    WeatherWidgetConfig,
    WidgetType,
} from '@/lib/widgets/types';

/**
 * Build cache keys matching PHP WidgetDataCollector:
 * - weather: md5(strtolower(trim(location)).'|'.$units)
 * - news: md5(trim(feedUrl))
 * - calendar: md5(trim(feedUrl))
 */
export function weatherDataKey(location: string, units: string): string {
    const canonical = `${location.trim().toLowerCase()}|${units}`;
    return `weather:${md5(canonical)}`;
}

export function newsDataKey(feedUrl: string): string {
    return `news:${md5(feedUrl.trim())}`;
}

export function calendarDataKey(feedUrl: string): string {
    return `calendar:${md5(feedUrl.trim())}`;
}

export function widgetDataKeyForConfig(
    type: WidgetType,
    config: Record<string, unknown>,
): string | null {
    if (type === 'weather') {
        const location =
            typeof config.location === 'string' ? config.location : '';
        const units = typeof config.units === 'string' ? config.units : 'c';
        return weatherDataKey(location, units);
    }

    if (type === 'news') {
        const feedUrl =
            typeof config.feedUrl === 'string' ? config.feedUrl : '';
        return newsDataKey(feedUrl);
    }

    if (type === 'calendar') {
        const feedUrl =
            typeof config.feedUrl === 'string' ? config.feedUrl : '';
        return calendarDataKey(feedUrl);
    }

    return null;
}

export function lookupWidgetData(
    map: Record<string, unknown> | undefined,
    type: WidgetType,
    config:
        | WeatherWidgetConfig
        | NewsWidgetConfig
        | CalendarWidgetConfig
        | Record<string, unknown>,
): unknown {
    if (!map) {
        return undefined;
    }

    const key = widgetDataKeyForConfig(type, config as Record<string, unknown>);
    if (!key) {
        return undefined;
    }

    return map[key];
}
