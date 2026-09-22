import type { LayoutElement, LayoutSchema } from '@/types/layout-schema';
import { resolveWidgetType } from '@/lib/widgets/registry';
import type { WidgetType } from '@/lib/widgets/types';

export type WidgetDataMap = Record<string, unknown>;

export type WidgetDataRequestItem = {
    type: WidgetType;
    config: Record<string, unknown>;
};

const REFRESH_MS = 5 * 60 * 1000;

const EXTERNAL_TYPES = new Set<WidgetType>(['weather', 'news', 'calendar']);

export function collectExternalWidgetRequests(
    schema: LayoutSchema | null | undefined,
): WidgetDataRequestItem[] {
    if (!schema?.elements?.length) {
        return [];
    }

    const seen = new Set<string>();
    const items: WidgetDataRequestItem[] = [];

    for (const element of schema.elements) {
        const request = widgetRequestFromElement(element);
        if (!request) {
            continue;
        }
        const key = `${request.type}:${JSON.stringify(request.config)}`;
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        items.push(request);
    }

    return items;
}

function widgetRequestFromElement(
    element: LayoutElement,
): WidgetDataRequestItem | null {
    if (element.type !== 'widget') {
        return null;
    }

    const type = resolveWidgetType(element.props);
    if (!type || !EXTERNAL_TYPES.has(type)) {
        return null;
    }

    const config =
        element.props?.config &&
        typeof element.props.config === 'object' &&
        !Array.isArray(element.props.config)
            ? (element.props.config as Record<string, unknown>)
            : {};

    return { type, config };
}

/**
 * POST widget configs to the player API and merge returned data.
 * Failures are swallowed so the player keeps last-known data.
 */
export async function fetchWidgetData(
    items: WidgetDataRequestItem[],
    options: {
        token?: string | null;
        signal?: AbortSignal;
    } = {},
): Promise<WidgetDataMap | null> {
    if (items.length === 0) {
        return {};
    }

    try {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };
        if (options.token) {
            headers['X-Device-Token'] = options.token;
            headers.Authorization = `Bearer ${options.token}`;
        }

        const response = await fetch('/player/api/widgets/data', {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ requests: items }),
            signal: options.signal,
        });

        if (!response.ok) {
            return null;
        }

        const payload = (await response.json()) as {
            widgetData?: WidgetDataMap;
            data?: WidgetDataMap;
        };

        return payload.widgetData ?? payload.data ?? null;
    } catch {
        return null;
    }
}

export function startWidgetDataSync(options: {
    getSchema: () => LayoutSchema | null | undefined;
    getToken: () => string | null | undefined;
    isOnline: () => boolean;
    onData: (data: WidgetDataMap) => void;
    intervalMs?: number;
}): () => void {
    const intervalMs = options.intervalMs ?? REFRESH_MS;
    let aborted = false;
    let timer: number | null = null;
    const abortController = new AbortController();

    const tick = async () => {
        if (aborted || !options.isOnline()) {
            return;
        }

        const items = collectExternalWidgetRequests(options.getSchema());
        if (items.length === 0) {
            return;
        }

        const data = await fetchWidgetData(items, {
            token: options.getToken(),
            signal: abortController.signal,
        });

        if (!aborted && data) {
            options.onData(data);
        }
    };

    void tick();
    timer = window.setInterval(() => {
        void tick();
    }, intervalMs);

    return () => {
        aborted = true;
        abortController.abort();
        if (timer !== null) {
            window.clearInterval(timer);
        }
    };
}
