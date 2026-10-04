import {
    activatePendingPackage,
    discardPendingPackage,
    getActivePackage,
    patchRuntime,
    putPendingPackage,
    type OfflinePackage,
} from '@/lib/player/offline-db';
import { cacheAssets, pruneUnreferencedMedia } from '@/lib/player/media-cache';

function abortAfter(ms: number): AbortSignal {
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
        return AbortSignal.timeout(ms);
    }

    const controller = new AbortController();
    window.setTimeout(() => controller.abort(), ms);

    return controller.signal;
}

export type SyncResult =
    | { status: 'unchanged'; package: OfflinePackage }
    | { status: 'activated'; package: OfflinePackage }
    | { status: 'failed'; error: string; package: OfflinePackage | null }
    | { status: 'skipped'; package: OfflinePackage | null };

/**
 * Offline sync: fetch package → best-effort media cache → activate.
 * Incomplete Cache Storage on TVs must not block activating the new package.
 */
export async function syncOfflinePackage(
    token: string,
    options: { force?: boolean } = {},
): Promise<SyncResult> {
    const active = await getActivePackage();

    let remote: OfflinePackage;
    try {
        const response = await fetch('/player/api/offline-package', {
            headers: {
                Accept: 'application/json',
                'X-Device-Token': token,
            },
            credentials: 'same-origin',
            signal: abortAfter(30_000),
        });

        if (response.status === 401) {
            return { status: 'failed', error: 'revoked', package: active };
        }

        if (!response.ok) {
            throw new Error(`Offline package HTTP ${response.status}`);
        }

        remote = (await response.json()) as OfflinePackage;
    } catch (error) {
        const message =
            error instanceof Error
                ? error.message
                : 'Offline package fetch failed';
        await patchRuntime({
            online: false,
            lastOfflineAt: new Date().toISOString(),
            syncError: message,
        });

        return { status: 'failed', error: message, package: active };
    }

    await patchRuntime({ online: true, syncError: null });

    if (
        !options.force &&
        active &&
        active.packageVersion === remote.packageVersion
    ) {
        await patchRuntime({
            cacheReady: true,
            activePackageVersion: active.packageVersion,
            lastSuccessfulSyncAt: new Date().toISOString(),
            screenId: active.screen.id,
        });

        return { status: 'unchanged', package: active };
    }

    await putPendingPackage(remote);

    const cached = await cacheAssets(remote.assets, token);
    if (!cached.ok && cached.error === 'revoked') {
        await discardPendingPackage();
        await patchRuntime({
            syncError: cached.error,
        });

        return { status: 'failed', error: 'revoked', package: active };
    }

    if (!cached.ok) {
        await patchRuntime({
            syncError: cached.error ?? 'Asset cache incomplete',
        });
    }

    const activated = await activatePendingPackage();
    if (!activated) {
        return {
            status: 'failed',
            error: 'Could not activate package',
            package: active,
        };
    }

    try {
        await pruneUnreferencedMedia(activated);
    } catch {
        // Cleanup is best-effort — never fail activation for prune errors.
    }

    await patchRuntime({
        cacheReady: true,
        activePackageVersion: activated.packageVersion,
        lastSuccessfulSyncAt: new Date().toISOString(),
        screenId: activated.screen.id,
        syncError: null,
        online: true,
    });

    return { status: 'activated', package: activated };
}
