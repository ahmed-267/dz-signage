import { useEffect, useMemo, useState } from 'react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import type { CountdownWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';

type CountdownWidgetProps = {
    config: CountdownWidgetConfig;
    elementProps?: LayoutElementProps;
};

function pad(n: number): string {
    return String(Math.max(0, n)).padStart(2, '0');
}

export function CountdownWidget({
    config,
    elementProps,
}: CountdownWidgetProps) {
    const targetMs = useMemo(() => {
        const parsed = Date.parse(config.targetAt);
        return Number.isFinite(parsed) ? parsed : Date.now();
    }, [config.targetAt]);

    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, []);

    const remaining = Math.max(0, targetMs - now);
    const done = remaining <= 0;

    const totalSeconds = Math.floor(remaining / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const parts: string[] = [];
    if (config.showDays) {
        parts.push(pad(days));
    }
    if (config.showHours) {
        parts.push(pad(hours));
    }
    if (config.showMinutes) {
        parts.push(pad(minutes));
    }
    if (config.showSeconds) {
        parts.push(pad(seconds));
    }

    return (
        <WidgetShell
            props={elementProps}
            className="items-center justify-center gap-2"
        >
            {config.title ? (
                <div
                    className="opacity-85"
                    style={{ fontSize: '0.4em', fontWeight: 500 }}
                >
                    {config.title}
                </div>
            ) : null}
            {done ? (
                <div className="leading-tight">{config.completionMessage}</div>
            ) : (
                <div className="leading-none tracking-tight tabular-nums">
                    {parts.join(':')}
                </div>
            )}
        </WidgetShell>
    );
}
