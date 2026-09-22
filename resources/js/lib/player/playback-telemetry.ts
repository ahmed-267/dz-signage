/**
 * Batched, idempotent playback telemetry for Analytics (Phase 15).
 * Failures are silent — signage must keep playing.
 */

export type PlaybackTelemetryEvent = {
    type:
        | 'content_started'
        | 'content_ended'
        | 'playlist_item_started'
        | 'deployment_applied'
        | 'player_error';
    occurred_at?: string;
    deployment_id?: number | null;
    screen_design_version_id?: number | null;
    playlist_version_id?: number | null;
    schedule_id?: number | null;
    duration_seconds?: number | null;
    error_code?: string | null;
    idempotency_key: string;
};

const queue: PlaybackTelemetryEvent[] = [];
let flushTimer: number | null = null;
let lastContentKey: string | null = null;

function deviceAuthHeaders(token: string): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        'X-Device-Token': token,
    };
}

export function enqueuePlaybackEvent(
    token: string | null,
    event: PlaybackTelemetryEvent,
): void {
    if (!token) {
        return;
    }

    queue.push(event);

    if (flushTimer !== null) {
        return;
    }

    flushTimer = window.setTimeout(() => {
        flushTimer = null;
        void flushPlaybackEvents(token);
    }, 1500);
}

export async function flushPlaybackEvents(token: string): Promise<void> {
    if (queue.length === 0) {
        return;
    }

    const batch = queue.splice(0, 20);

    try {
        await fetch('/player/api/playback-events', {
            method: 'POST',
            headers: deviceAuthHeaders(token),
            body: JSON.stringify({ events: batch }),
            credentials: 'same-origin',
            keepalive: true,
        });
    } catch {
        // Drop on failure — avoid retry storms on offline Screens.
    }
}

/**
 * Emit content_started / deployment_applied once per content version change.
 */
export function trackContentApplied(
    token: string | null,
    payload: {
        deploymentId?: number | null;
        screenDesignVersionId?: number | null;
        playlistVersionId?: number | null;
        scheduleId?: number | null;
        contentKey: string;
    },
): void {
    if (!token) {
        return;
    }

    if (lastContentKey === payload.contentKey) {
        return;
    }

    lastContentKey = payload.contentKey;
    const stamp = new Date().toISOString();
    const baseKey = `${payload.contentKey}:${stamp.slice(0, 16)}`;

    enqueuePlaybackEvent(token, {
        type: 'deployment_applied',
        occurred_at: stamp,
        deployment_id: payload.deploymentId ?? null,
        screen_design_version_id: payload.screenDesignVersionId ?? null,
        playlist_version_id: payload.playlistVersionId ?? null,
        schedule_id: payload.scheduleId ?? null,
        idempotency_key: `dep:${baseKey}`,
    });

    enqueuePlaybackEvent(token, {
        type: 'content_started',
        occurred_at: stamp,
        deployment_id: payload.deploymentId ?? null,
        screen_design_version_id: payload.screenDesignVersionId ?? null,
        playlist_version_id: payload.playlistVersionId ?? null,
        schedule_id: payload.scheduleId ?? null,
        idempotency_key: `start:${baseKey}`,
    });
}

export function resetPlaybackTracking(): void {
    lastContentKey = null;
}
