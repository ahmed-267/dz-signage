import type { LayoutMediaMap } from '@/types/layout-schema';
import { AlertWidget } from '@/components/widgets/alert-widget';
import { CalendarWidget } from '@/components/widgets/calendar-widget';
import { ClockWidget } from '@/components/widgets/clock-widget';
import { CountdownWidget } from '@/components/widgets/countdown-widget';
import { EmbedWidget } from '@/components/widgets/embed-widget';
import { InfoCardWidget } from '@/components/widgets/info-card-widget';
import { NewsWidget } from '@/components/widgets/news-widget';
import { WeatherWidget } from '@/components/widgets/weather-widget';
import { WidgetErrorBoundary } from '@/components/widgets/widget-error-boundary';
import { WidgetShell } from '@/components/widgets/widget-shell';
import { getWidgetDefinition, resolveWidgetType } from '@/lib/widgets/registry';
import { lookupWidgetData } from '@/lib/widgets/widget-data-keys';
import type {
    AlertWidgetConfig,
    CalendarEventDemo,
    CalendarWidgetConfig,
    ClockWidgetConfig,
    CountdownWidgetConfig,
    EmbedWidgetConfig,
    InfoCardWidgetConfig,
    NewsItemDemo,
    NewsWidgetConfig,
    WeatherDemoData,
    WeatherWidgetConfig,
    WidgetRuntimeMode,
} from '@/lib/widgets/types';
import type { LayoutElement } from '@/types/layout-schema';

export type WidgetRendererProps = {
    element: LayoutElement;
    mode: WidgetRuntimeMode;
    mediaMap?: LayoutMediaMap;
    widgetData?: Record<string, unknown>;
    isOnline?: boolean;
    interact?: boolean;
};

function mergeConfig<T extends Record<string, unknown>>(
    type: string,
    raw: unknown,
): T {
    const def = getWidgetDefinition(type);
    const defaults = (def?.defaultConfig() ?? {}) as Record<string, unknown>;
    const overlay =
        raw && typeof raw === 'object' && !Array.isArray(raw)
            ? (raw as Record<string, unknown>)
            : {};
    return { ...defaults, ...overlay } as T;
}

export function WidgetRenderer({
    element,
    mode,
    mediaMap,
    widgetData,
    isOnline = true,
    interact = false,
}: WidgetRendererProps) {
    const props = element.props ?? {};
    const type = resolveWidgetType(props);
    const useDemo = mode === 'editor' || mode === 'preview';

    if (!type) {
        return (
            <WidgetShell props={props} className="items-center justify-center">
                <span style={{ fontSize: '0.45em' }}>Unknown widget</span>
            </WidgetShell>
        );
    }

    const config = props.config;
    const liveData = lookupWidgetData(
        widgetData,
        type,
        (config && typeof config === 'object' ? config : {}) as Record<
            string,
            unknown
        >,
    );

    return (
        <WidgetErrorBoundary>
            <div
                className="h-full w-full"
                data-widget-type={type}
                data-test={`widget-${type}`}
            >
                {type === 'clock' ? (
                    <ClockWidget
                        config={mergeConfig<ClockWidgetConfig>('clock', config)}
                        elementProps={props}
                    />
                ) : null}
                {type === 'countdown' ? (
                    <CountdownWidget
                        config={mergeConfig<CountdownWidgetConfig>(
                            'countdown',
                            config,
                        )}
                        elementProps={props}
                    />
                ) : null}
                {type === 'weather' ? (
                    <WeatherWidget
                        config={mergeConfig<WeatherWidgetConfig>(
                            'weather',
                            config,
                        )}
                        data={(liveData as WeatherDemoData | null) ?? null}
                        useDemo={useDemo && liveData == null}
                        elementProps={props}
                    />
                ) : null}
                {type === 'news' ? (
                    <NewsWidget
                        config={mergeConfig<NewsWidgetConfig>('news', config)}
                        data={
                            (liveData as
                                | { items?: NewsItemDemo[] }
                                | NewsItemDemo[]
                                | null) ?? null
                        }
                        useDemo={useDemo && liveData == null}
                        elementProps={props}
                    />
                ) : null}
                {type === 'calendar' ? (
                    <CalendarWidget
                        config={mergeConfig<CalendarWidgetConfig>(
                            'calendar',
                            config,
                        )}
                        data={
                            (liveData as
                                | { events?: CalendarEventDemo[] }
                                | CalendarEventDemo[]
                                | null) ?? null
                        }
                        useDemo={useDemo && liveData == null}
                        elementProps={props}
                    />
                ) : null}
                {type === 'alert' ? (
                    <AlertWidget
                        config={mergeConfig<AlertWidgetConfig>('alert', config)}
                        elementProps={props}
                    />
                ) : null}
                {type === 'info_card' ? (
                    <InfoCardWidget
                        config={mergeConfig<InfoCardWidgetConfig>(
                            'info_card',
                            config,
                        )}
                        elementProps={props}
                        mediaMap={mediaMap}
                    />
                ) : null}
                {type === 'embed' ? (
                    <EmbedWidget
                        config={mergeConfig<EmbedWidgetConfig>('embed', config)}
                        elementProps={props}
                        mode={mode}
                        isOnline={isOnline}
                        interact={interact}
                    />
                ) : null}
            </div>
        </WidgetErrorBoundary>
    );
}
