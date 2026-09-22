import type { LayoutElementProps } from '@/types/layout-schema';
import type {
    WidgetCategory,
    WidgetDefinition,
    WidgetType,
} from '@/lib/widgets/types';

const CATEGORY_LABELS: Record<WidgetCategory, string> = {
    time: 'Time',
    information: 'Information',
    external: 'External',
};

const LEGACY_WIDGET_NAME_MAP: Record<string, WidgetType> = {
    clock: 'clock',
    countdown: 'countdown',
    'countdown timer': 'countdown',
    weather: 'weather',
    news: 'news',
    'news ticker': 'news',
    calendar: 'calendar',
    alert: 'alert',
    announcement: 'alert',
    info_card: 'info_card',
    'info card': 'info_card',
    'configurable cards': 'info_card',
    embed: 'embed',
    'live stream': 'embed',
    livestream: 'embed',
};

function daysAheadIso(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString();
}

export const WIDGET_DEFINITIONS: WidgetDefinition[] = [
    {
        type: 'clock',
        label: 'Clock',
        category: 'time',
        icon: 'Clock',
        defaultWidth: 420,
        defaultHeight: 220,
        offline: 'local',
        defaultConfig: () => ({
            timezone: 'UTC',
            hourFormat: '24',
            showSeconds: true,
            showDate: true,
            showWeekday: true,
            dateFormat: 'long',
        }),
    },
    {
        type: 'countdown',
        label: 'Countdown',
        category: 'time',
        icon: 'Timer',
        defaultWidth: 520,
        defaultHeight: 240,
        offline: 'local',
        defaultConfig: () => ({
            title: 'Event Begins In',
            targetAt: daysAheadIso(7),
            timezone: 'UTC',
            showDays: true,
            showHours: true,
            showMinutes: true,
            showSeconds: true,
            completionMessage: 'Event Started',
        }),
    },
    {
        type: 'weather',
        label: 'Weather',
        category: 'information',
        icon: 'Cloud',
        defaultWidth: 360,
        defaultHeight: 280,
        offline: 'cached',
        defaultConfig: () => ({
            location: 'Nottingham, United Kingdom',
            units: 'c',
            showTemp: true,
            showCondition: true,
            showHighLow: true,
            layout: 'stack',
        }),
    },
    {
        type: 'news',
        label: 'News',
        category: 'information',
        icon: 'Rss',
        defaultWidth: 720,
        defaultHeight: 200,
        offline: 'cached',
        defaultConfig: () => ({
            feedUrl: '',
            heading: 'News',
            maxItems: 5,
            rotateSeconds: 8,
            showSource: true,
            showTimestamp: false,
            layout: 'rotate',
        }),
    },
    {
        type: 'calendar',
        label: 'Calendar',
        category: 'information',
        icon: 'Calendar',
        defaultWidth: 420,
        defaultHeight: 360,
        offline: 'cached',
        defaultConfig: () => ({
            feedUrl: '',
            title: 'Upcoming Events',
            maxEvents: 5,
            showDate: true,
            showTime: true,
            showLocation: true,
            layout: 'list',
            timezone: 'UTC',
        }),
    },
    {
        type: 'alert',
        label: 'Alert',
        category: 'information',
        icon: 'AlertTriangle',
        defaultWidth: 560,
        defaultHeight: 160,
        offline: 'local',
        defaultConfig: () => ({
            title: 'Announcement',
            message: 'Add your message',
            icon: 'info',
            severity: 'info',
            alignment: 'left',
        }),
    },
    {
        type: 'info_card',
        label: 'Info Card',
        category: 'information',
        icon: 'LayoutTemplate',
        defaultWidth: 420,
        defaultHeight: 320,
        offline: 'local',
        defaultConfig: () => ({
            heading: 'Information',
            subheading: '',
            body: '',
            value: '',
            footer: '',
            layout: 'stack',
            mediaAssetId: null,
        }),
    },
    {
        type: 'embed',
        label: 'Embed',
        category: 'external',
        icon: 'Radio',
        defaultWidth: 640,
        defaultHeight: 360,
        offline: 'none',
        defaultConfig: () => ({
            url: '',
            provider: 'auto',
            autoplay: true,
            muted: true,
            loop: true,
            controls: true,
            volume: 70,
        }),
    },
];

const BY_TYPE = Object.fromEntries(
    WIDGET_DEFINITIONS.map((def) => [def.type, def]),
) as Record<WidgetType, WidgetDefinition>;

export function getWidgetDefinition(
    type: string | null | undefined,
): WidgetDefinition | null {
    if (!type || typeof type !== 'string') {
        return null;
    }
    return BY_TYPE[type as WidgetType] ?? null;
}

export function resolveWidgetType(
    props: Record<string, unknown> | null | undefined,
): WidgetType | null {
    if (!props) {
        return null;
    }

    const direct = props.widgetType;
    if (typeof direct === 'string') {
        const normalized = direct.trim().toLowerCase().replace(/-/g, '_');
        if (BY_TYPE[normalized as WidgetType]) {
            return normalized as WidgetType;
        }
    }

    const name = props.widgetName;
    if (typeof name === 'string') {
        const key = name.trim().toLowerCase();
        if (LEGACY_WIDGET_NAME_MAP[key]) {
            return LEGACY_WIDGET_NAME_MAP[key];
        }
        const underscored = key.replace(/\s+/g, '_');
        if (BY_TYPE[underscored as WidgetType]) {
            return underscored as WidgetType;
        }
    }

    return null;
}

export function widgetCatalogGrouped(): {
    category: WidgetCategory;
    label: string;
    widgets: WidgetDefinition[];
}[] {
    const order: WidgetCategory[] = ['time', 'information', 'external'];
    return order.map((category) => ({
        category,
        label: CATEGORY_LABELS[category],
        widgets: WIDGET_DEFINITIONS.filter((w) => w.category === category),
    }));
}

export function createWidgetElementProps(type: WidgetType): LayoutElementProps {
    const def = getWidgetDefinition(type);
    if (!def) {
        return {
            widgetType: type,
            config: {},
        };
    }

    return {
        widgetType: def.type,
        widgetName: def.label,
        config: def.defaultConfig() as unknown as Record<string, unknown>,
        color: '#FFFFFF',
        fontFamily: 'Outfit, system-ui, sans-serif',
        fontSize: 28,
        fontWeight: 600,
        textAlign: 'center',
        opacity: 1,
        borderRadius: 12,
        fill: 'rgba(15, 23, 42, 0.72)',
    };
}
