import { useEffect, useState } from 'react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import type { ClockWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';

type ClockWidgetProps = {
    config: ClockWidgetConfig;
    elementProps?: LayoutElementProps;
};

export function ClockWidget({ config, elementProps }: ClockWidgetProps) {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(id);
    }, []);

    const time = new Intl.DateTimeFormat('en-GB', {
        timeZone: config.timezone || 'UTC',
        hour: '2-digit',
        minute: '2-digit',
        second: config.showSeconds ? '2-digit' : undefined,
        hour12: config.hourFormat === '12',
    }).format(now);

    const weekday = config.showWeekday
        ? new Intl.DateTimeFormat('en-GB', {
              timeZone: config.timezone || 'UTC',
              weekday: config.dateFormat === 'long' ? 'long' : 'short',
          }).format(now)
        : null;

    const date = config.showDate
        ? new Intl.DateTimeFormat('en-GB', {
              timeZone: config.timezone || 'UTC',
              day: 'numeric',
              month: config.dateFormat === 'long' ? 'long' : 'short',
              year: 'numeric',
          }).format(now)
        : null;

    return (
        <WidgetShell
            props={elementProps}
            className="items-center justify-center gap-1"
        >
            <div className="leading-none tracking-tight tabular-nums">
                {time}
            </div>
            {weekday || date ? (
                <div
                    className="opacity-80"
                    style={{ fontSize: '0.45em', fontWeight: 500 }}
                >
                    {[weekday, date].filter(Boolean).join(' · ')}
                </div>
            ) : null}
        </WidgetShell>
    );
}
