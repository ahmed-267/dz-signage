import type {
    OfflineManifestPayload,
    OfflinePackage,
    OfflineScheduleEntry,
} from '@/lib/player/offline-db';

/**
 * Test hook — E2E / unit tests may set `window.__DZ_PLAYER_NOW__` to an ISO
 * string or epoch ms so offline schedule changeovers are deterministic.
 */
export function playerNow(override?: Date | number | string | null): Date {
    if (override != null) {
        return new Date(override);
    }

    if (typeof window !== 'undefined') {
        const injected = (
            window as Window & { __DZ_PLAYER_NOW__?: string | number | Date }
        ).__DZ_PLAYER_NOW__;
        if (injected != null) {
            return new Date(injected);
        }
    }

    return new Date();
}

function entryMatches(entry: OfflineScheduleEntry, at: Date): boolean {
    const start = Date.parse(entry.window.startsAt);
    const end = Date.parse(entry.window.endsAt);
    if (Number.isNaN(start) || Number.isNaN(end)) {
        return false;
    }

    const t = at.getTime();

    // Inclusive start, exclusive end — mirrors ScheduleEvaluator.
    return t >= start && t < end;
}

function outranks(a: OfflineScheduleEntry, b: OfflineScheduleEntry): boolean {
    if (a.priority !== b.priority) {
        return a.priority > b.priority;
    }

    const aAct = a.activatedAt ?? '';
    const bAct = b.activatedAt ?? '';
    if (aAct !== bAct) {
        return aAct > bAct;
    }

    return a.scheduleId > b.scheduleId;
}

/**
 * Resolve what to play from a synchronised offline package.
 * Precedence: matching schedule window → fallback deployment → none.
 */
export function resolveOfflineContent(
    pkg: OfflinePackage,
    at?: Date | number | string | null,
): OfflineManifestPayload {
    const now = playerNow(at ?? null);

    if (pkg.screen.operationalStatus === 'inactive') {
        return {
            status: 'inactive',
            contentSource: 'none',
            manifestVersion: 1,
        } as OfflineManifestPayload;
    }

    const matching = pkg.scheduleEntries.filter((entry) =>
        entryMatches(entry, now),
    );

    if (matching.length > 0) {
        matching.sort((a, b) => (outranks(a, b) ? -1 : 1));
        return matching[0].content;
    }

    if (
        pkg.fallbackDeployment &&
        (pkg.fallbackDeployment.status === 'ready' ||
            pkg.fallbackDeployment.contentType)
    ) {
        return pkg.fallbackDeployment;
    }

    if (pkg.current.status === 'ready') {
        // Only keep a non-schedule current snapshot when no schedule matched —
        // schedule-sourced current is time-bound and must not stick forever.
        if (pkg.current.contentSource !== 'schedule') {
            return pkg.current;
        }
    }

    return {
        status: 'no_content',
        contentSource: 'none',
        screenId: pkg.screen.id,
        manifestVersion: 1,
    } as OfflineManifestPayload;
}

/** Next boundary (ms) when offline content may change, or null. */
export function nextOfflineBoundaryMs(
    pkg: OfflinePackage,
    at?: Date | number | string | null,
): number | null {
    const now = playerNow(at ?? null).getTime();
    let soonest: number | null = null;

    for (const entry of pkg.scheduleEntries) {
        const start = Date.parse(entry.window.startsAt);
        const end = Date.parse(entry.window.endsAt);
        for (const ts of [start, end]) {
            if (Number.isNaN(ts) || ts <= now) {
                continue;
            }
            if (soonest === null || ts < soonest) {
                soonest = ts;
            }
        }
    }

    return soonest;
}
