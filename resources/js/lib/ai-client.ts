import { csrfHeaders } from '@/lib/csrf';

export type AiStatus = {
    available: boolean;
    feature_enabled: boolean;
    configured: boolean;
    provider: string;
    video_enabled: boolean;
    message: string | null;
};

type AiJsonError = {
    message?: string;
    code?: string;
};

function idempotencyKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `ai-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function aiRequest<T>(
    url: string,
    method: 'GET' | 'POST' = 'POST',
    body?: Record<string, unknown>,
): Promise<T> {
    const payload =
        method === 'POST'
            ? {
                  ...body,
                  idempotency_key:
                      (body?.idempotency_key as string | undefined) ??
                      idempotencyKey(),
              }
            : undefined;

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    const data = (await response.json().catch(() => ({}))) as T & AiJsonError;

    if (!response.ok) {
        throw new Error(
            data.message ?? 'AI generation failed. Please try again.',
        );
    }

    return data;
}
