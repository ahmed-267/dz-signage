export type WidgetType =
    | 'clock'
    | 'countdown'
    | 'weather'
    | 'news'
    | 'calendar'
    | 'alert'
    | 'info_card'
    | 'embed';

export type WidgetCategory = 'time' | 'information' | 'external';

export type WidgetOfflineMode = 'local' | 'cached' | 'none';

export type WidgetRuntimeMode = 'editor' | 'preview' | 'player';

export type ClockWidgetConfig = {
    timezone: string;
    hourFormat: '12' | '24';
    showSeconds: boolean;
    showDate: boolean;
    showWeekday: boolean;
    dateFormat: 'short' | 'long';
};

export type CountdownWidgetConfig = {
    title: string;
    targetAt: string;
    timezone: string;
    showDays: boolean;
    showHours: boolean;
    showMinutes: boolean;
    showSeconds: boolean;
    completionMessage: string;
};

export type WeatherWidgetConfig = {
    location: string;
    units: 'c' | 'f';
    showTemp: boolean;
    showCondition: boolean;
    showHighLow: boolean;
    layout: 'stack' | 'inline';
};

export type NewsWidgetConfig = {
    feedUrl: string;
    heading: string;
    maxItems: number;
    rotateSeconds: number;
    showSource: boolean;
    showTimestamp: boolean;
    layout: 'rotate' | 'ticker' | 'list';
};

export type CalendarWidgetConfig = {
    feedUrl: string;
    title: string;
    maxEvents: number;
    showDate: boolean;
    showTime: boolean;
    showLocation: boolean;
    layout: 'list' | 'compact';
    timezone: string;
};

export type AlertWidgetConfig = {
    title: string;
    message: string;
    icon: 'info' | 'warning' | 'error' | 'success';
    severity: 'info' | 'warning' | 'error' | 'success';
    alignment: 'left' | 'center' | 'right';
};

export type InfoCardWidgetConfig = {
    heading: string;
    subheading: string;
    body: string;
    value: string;
    footer: string;
    layout: 'stack' | 'split';
    mediaAssetId: string | number | null;
};

export type EmbedWidgetConfig = {
    url: string;
    provider: 'auto' | 'youtube' | 'vimeo' | 'generic';
    kind?:
        | 'youtube'
        | 'vimeo'
        | 'hls'
        | 'dash'
        | 'video'
        | 'website'
        | 'teams'
        | 'zoom'
        | 'webex'
        | 'blocked'
        | 'drm'
        | 'unsupported'
        | 'empty';
    source_url?: string;
    autoplay?: boolean;
    muted?: boolean;
    loop?: boolean;
    controls?: boolean;
    /** 0–100. Applied when unmuted. */
    volume?: number;
};

export type WidgetConfigByType = {
    clock: ClockWidgetConfig;
    countdown: CountdownWidgetConfig;
    weather: WeatherWidgetConfig;
    news: NewsWidgetConfig;
    calendar: CalendarWidgetConfig;
    alert: AlertWidgetConfig;
    info_card: InfoCardWidgetConfig;
    embed: EmbedWidgetConfig;
};

export type WidgetConfig = WidgetConfigByType[WidgetType];

export type WeatherDemoData = {
    location: string;
    temp: number;
    units: 'c' | 'f';
    condition: string;
    high: number;
    low: number;
    icon?: string;
};

export type NewsItemDemo = {
    title: string;
    source?: string;
    publishedAt?: string;
    url?: string;
};

export type CalendarEventDemo = {
    title: string;
    startsAt: string;
    endsAt?: string;
    location?: string;
    allDay?: boolean;
};

export type WidgetDefinition<T extends WidgetType = WidgetType> = {
    type: T;
    label: string;
    category: WidgetCategory;
    icon: string;
    defaultWidth: number;
    defaultHeight: number;
    defaultConfig: () => WidgetConfigByType[T];
    offline: WidgetOfflineMode;
};
