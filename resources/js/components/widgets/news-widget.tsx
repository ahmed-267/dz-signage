import { useEffect, useMemo, useState } from 'react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import { DEMO_NEWS_ITEMS } from '@/lib/widgets/demo-data';
import type { NewsItemDemo, NewsWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';

type NewsWidgetProps = {
    config: NewsWidgetConfig;
    data?: { items?: NewsItemDemo[] } | NewsItemDemo[] | null;
    elementProps?: LayoutElementProps;
    useDemo?: boolean;
};

function normalizeItems(
    data: NewsWidgetProps['data'],
    useDemo: boolean,
): NewsItemDemo[] {
    if (Array.isArray(data)) {
        return data;
    }
    if (data && Array.isArray(data.items)) {
        return data.items;
    }
    return useDemo ? DEMO_NEWS_ITEMS : [];
}

export function NewsWidget({
    config,
    data,
    elementProps,
    useDemo = true,
}: NewsWidgetProps) {
    const items = useMemo(() => {
        return normalizeItems(data, useDemo).slice(
            0,
            Math.max(1, config.maxItems || 5),
        );
    }, [config.maxItems, data, useDemo]);

    const [index, setIndex] = useState(0);

    useEffect(() => {
        setIndex(0);
    }, [items.length, config.layout]);

    useEffect(() => {
        if (config.layout !== 'rotate' || items.length <= 1) {
            return;
        }
        const ms = Math.max(2, config.rotateSeconds || 8) * 1000;
        const id = window.setInterval(() => {
            setIndex((prev) => (prev + 1) % items.length);
        }, ms);
        return () => window.clearInterval(id);
    }, [config.layout, config.rotateSeconds, items.length]);

    const meta = (item: NewsItemDemo) => {
        const parts: string[] = [];
        if (config.showSource && item.source) {
            parts.push(item.source);
        }
        if (config.showTimestamp && item.publishedAt) {
            parts.push(
                new Intl.DateTimeFormat('en-GB', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date(item.publishedAt)),
            );
        }
        return parts.join(' · ');
    };

    return (
        <WidgetShell props={elementProps} className="justify-center gap-2">
            {config.heading ? (
                <div
                    className="opacity-75"
                    style={{ fontSize: '0.4em', fontWeight: 600 }}
                >
                    {config.heading}
                </div>
            ) : null}

            {items.length === 0 ? (
                <div style={{ fontSize: '0.45em', fontWeight: 500 }}>
                    No news items
                </div>
            ) : config.layout === 'ticker' ? (
                <div
                    className="w-full overflow-hidden whitespace-nowrap"
                    style={{ fontSize: '0.55em', fontWeight: 500 }}
                >
                    <div className="animate-pulse">
                        {items.map((item) => item.title).join('   •   ')}
                    </div>
                </div>
            ) : config.layout === 'list' ? (
                <ul className="w-full space-y-1.5 overflow-hidden">
                    {items.map((item, i) => (
                        <li key={`${item.title}-${i}`} className="min-w-0">
                            <div
                                className="truncate"
                                style={{ fontSize: '0.48em', fontWeight: 600 }}
                            >
                                {item.title}
                            </div>
                            {meta(item) ? (
                                <div
                                    className="truncate opacity-70"
                                    style={{
                                        fontSize: '0.32em',
                                        fontWeight: 400,
                                    }}
                                >
                                    {meta(item)}
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="min-w-0">
                    <div
                        className="line-clamp-3"
                        style={{ fontSize: '0.55em', fontWeight: 600 }}
                    >
                        {items[index]?.title}
                    </div>
                    {meta(items[index]!) ? (
                        <div
                            className="mt-1 opacity-70"
                            style={{ fontSize: '0.34em', fontWeight: 400 }}
                        >
                            {meta(items[index]!)}
                        </div>
                    ) : null}
                </div>
            )}
        </WidgetShell>
    );
}
