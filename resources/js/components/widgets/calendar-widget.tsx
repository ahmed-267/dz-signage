import { useMemo } from 'react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import { DEMO_CALENDAR_EVENTS } from '@/lib/widgets/demo-data';
import type {
    CalendarEventDemo,
    CalendarWidgetConfig,
} from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';

type CalendarWidgetProps = {
    config: CalendarWidgetConfig;
    data?: { events?: CalendarEventDemo[] } | CalendarEventDemo[] | null;
    elementProps?: LayoutElementProps;
    useDemo?: boolean;
};

function normalizeEvents(
    data: CalendarWidgetProps['data'],
    useDemo: boolean,
): CalendarEventDemo[] {
    if (Array.isArray(data)) {
        return data;
    }
    if (data && Array.isArray(data.events)) {
        return data.events;
    }
    return useDemo ? DEMO_CALENDAR_EVENTS : [];
}

export function CalendarWidget({
    config,
    data,
    elementProps,
    useDemo = true,
}: CalendarWidgetProps) {
    const events = useMemo(() => {
        return normalizeEvents(data, useDemo).slice(
            0,
            Math.max(1, config.maxEvents || 5),
        );
    }, [config.maxEvents, data, useDemo]);

    const formatWhen = (event: CalendarEventDemo) => {
        const parts: string[] = [];
        const start = new Date(event.startsAt);
        if (config.showDate) {
            parts.push(
                new Intl.DateTimeFormat('en-GB', {
                    timeZone: config.timezone || 'UTC',
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                }).format(start),
            );
        }
        if (config.showTime && !event.allDay) {
            parts.push(
                new Intl.DateTimeFormat('en-GB', {
                    timeZone: config.timezone || 'UTC',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(start),
            );
        }
        return parts.join(' · ');
    };

    return (
        <WidgetShell props={elementProps} className="justify-start gap-2 py-3">
            {config.title ? (
                <div
                    className="opacity-80"
                    style={{ fontSize: '0.42em', fontWeight: 600 }}
                >
                    {config.title}
                </div>
            ) : null}
            {events.length === 0 ? (
                <div style={{ fontSize: '0.42em', fontWeight: 500 }}>
                    No upcoming events
                </div>
            ) : (
                <ul className="w-full space-y-2 overflow-hidden">
                    {events.map((event, i) => (
                        <li key={`${event.title}-${i}`} className="min-w-0">
                            <div
                                className="truncate"
                                style={{
                                    fontSize:
                                        config.layout === 'compact'
                                            ? '0.42em'
                                            : '0.48em',
                                    fontWeight: 600,
                                }}
                            >
                                {event.title}
                            </div>
                            <div
                                className="truncate opacity-75"
                                style={{ fontSize: '0.32em', fontWeight: 400 }}
                            >
                                {[
                                    formatWhen(event),
                                    config.showLocation ? event.location : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </WidgetShell>
    );
}
