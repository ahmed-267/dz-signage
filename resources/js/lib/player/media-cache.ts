import {
    MEDIA_CACHE,
    type OfflineAssetMeta,
    type OfflinePackage,
} from '@/lib/player/offline-db';

export async function cacheAssets(
    assets: OfflineAssetMeta[],
    token: string,
    onProgress?: (done: number, total: number) => void,
): Promise<{ ok: boolean; error?: string; cachedKeys: string[] }> {
    let cache: Cache;
    try {
        cache = await caches.open(MEDIA_CACHE);
    } catch {
        return {
            ok: false,
            error: 'Cache Storage unavailable',
            cachedKeys: [],
        };
    }

    const cachedKeys: string[] = [];
    let done = 0;

    for (const asset of assets) {
        try {
            const existing = await cache.match(asset.url);
            if (existing) {
                const existingKey = existing.headers.get('X-DZ-Cache-Key');
                if (existingKey === asset.cacheKey) {
                    cachedKeys.push(asset.cacheKey);
                    done++;
                    onProgress?.(done, assets.length);
                    continue;
                }
            }

            const response = await fetch(asset.url, {
                headers: {
                    Accept: '*/*',
                    'X-Device-Token': token,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return {
                    ok: false,
                    error: `Media ${asset.id} failed (${response.status})`,
                    cachedKeys,
                };
            }

            const headers = new Headers(response.headers);
            headers.set('X-DZ-Cache-Key', asset.cacheKey);
            const body = await response.blob();
            await cache.put(
                asset.url,
                new Response(body, {
                    status: 200,
                    statusText: 'OK',
                    headers,
                }),
            );
            cachedKeys.push(asset.cacheKey);
        } catch (error) {
            const message =
                error instanceof DOMException &&
                error.name === 'QuotaExceededError'
                    ? 'Storage quota exceeded'
                    : error instanceof Error
                      ? error.message
                      : 'Media cache failed';

            return { ok: false, error: message, cachedKeys };
        }

        done++;
        onProgress?.(done, assets.length);
    }

    return { ok: true, cachedKeys };
}

/** Delete media cache entries not referenced by the active package. */
export async function pruneUnreferencedMedia(
    pkg: OfflinePackage,
): Promise<number> {
    const keepKeys = new Set(pkg.assets.map((a) => a.cacheKey));
    const keepPaths = new Set(
        pkg.assets.map((a) => new URL(a.url, self.location.origin).pathname),
    );

    let cache: Cache;
    try {
        cache = await caches.open(MEDIA_CACHE);
    } catch {
        return 0;
    }

    const requests = await cache.keys();
    let removed = 0;

    for (const request of requests) {
        const path = new URL(request.url).pathname;
        const response = await cache.match(request);
        const key = response?.headers.get('X-DZ-Cache-Key') ?? null;
        const stillNeeded =
            keepPaths.has(path) && (key === null || keepKeys.has(key));

        if (!stillNeeded) {
            await cache.delete(request);
            removed++;
        }
    }

    return removed;
}
