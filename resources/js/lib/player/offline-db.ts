import Dexie, { type Table } from 'dexie';

export type OfflineAssetMeta = {
    id: number;
    url: string;
    mime: string | null;
    type: string | null;
    updatedAt: string | null;
    sizeBytes: number | null;
    cacheKey: string;
};

export type OfflineManifestPayload = {
    status?: string;
    deploymentId?: number | null;
    deploymentVersion?: string | null;
    contentType?: 'screen_design' | 'playlist';
    contentSource?: string;
    scheduleId?: number;
    screenDesignId?: number | null;
    screenDesignVersionId?: number | null;
    schema?: unknown;
    playlistId?: number;
    playlistVersionId?: number | null;
    items?: unknown[];
    media?: Record<
        string,
        { id?: number; type?: string; url?: string; mime?: string }
    >;
    validity?: {
        timezone?: string;
        startsAt?: string;
        endsAt?: string;
    };
};

export type OfflineScheduleEntry = {
    scheduleId: number;
    scheduleName: string;
    priority: number;
    activatedAt: string | null;
    timezone: string;
    window: {
        startsAt: string;
        endsAt: string;
        key: string;
    };
    content: OfflineManifestPayload;
};

export type OfflinePackage = {
    packageVersion: string;
    manifestVersion: number;
    horizonHours: number;
    generatedAt: string;
    screen: {
        id: number;
        name: string;
        orientation: string | null;
        operationalStatus: string;
    };
    current: OfflineManifestPayload;
    scheduleEntries: OfflineScheduleEntry[];
    fallbackDeployment: OfflineManifestPayload | null;
    assets: OfflineAssetMeta[];
    /** Precomputed widget payloads keyed by PHP WidgetDataCollector keys. */
    widgetData?: Record<string, unknown>;
};

export type OfflineRuntimeState = {
    id: 'runtime';
    screenId: number | null;
    activePackageVersion: string | null;
    lastSuccessfulSyncAt: string | null;
    lastOfflineAt: string | null;
    cacheReady: boolean;
    syncError: string | null;
    online: boolean;
};

class PlayerOfflineDb extends Dexie {
    packages!: Table<OfflinePackage & { slot: 'active' | 'pending' }, string>;

    runtime!: Table<OfflineRuntimeState, string>;

    credentials!: Table<{ id: 'device'; token: string }, string>;

    constructor() {
        super('dz-player-offline');
        this.version(1).stores({
            packages: 'slot, packageVersion',
            runtime: 'id',
        });
        this.version(2).stores({
            packages: 'slot, packageVersion',
            runtime: 'id',
            credentials: 'id',
        });
    }
}

export const playerOfflineDb = new PlayerOfflineDb();

export const MEDIA_CACHE = 'dz-player-media-v1';
export const SHELL_CACHE = 'dz-player-shell-v1';

export async function getActivePackage(): Promise<OfflinePackage | null> {
    return (await playerOfflineDb.packages.get('active')) ?? null;
}

export async function getPendingPackage(): Promise<OfflinePackage | null> {
    return (await playerOfflineDb.packages.get('pending')) ?? null;
}

export async function putPendingPackage(pkg: OfflinePackage): Promise<void> {
    await playerOfflineDb.packages.put({ ...pkg, slot: 'pending' });
}

export async function activatePendingPackage(): Promise<OfflinePackage | null> {
    const pending = await getPendingPackage();
    if (!pending) {
        return null;
    }

    await playerOfflineDb.transaction(
        'rw',
        playerOfflineDb.packages,
        async () => {
            await playerOfflineDb.packages.put({ ...pending, slot: 'active' });
            await playerOfflineDb.packages.delete('pending');
        },
    );

    return pending;
}

export async function discardPendingPackage(): Promise<void> {
    await playerOfflineDb.packages.delete('pending');
}

export async function clearAllOfflineData(): Promise<void> {
    await playerOfflineDb.packages.clear();
    await playerOfflineDb.runtime.clear();
    await playerOfflineDb.credentials.clear();
    try {
        await caches.delete(MEDIA_CACHE);
    } catch {
        // ignore
    }
}

export async function getRuntime(): Promise<OfflineRuntimeState> {
    const existing = await playerOfflineDb.runtime.get('runtime');
    if (existing) {
        return existing;
    }

    const initial: OfflineRuntimeState = {
        id: 'runtime',
        screenId: null,
        activePackageVersion: null,
        lastSuccessfulSyncAt: null,
        lastOfflineAt: null,
        cacheReady: false,
        syncError: null,
        online: true,
    };
    await playerOfflineDb.runtime.put(initial);

    return initial;
}

export async function patchRuntime(
    patch: Partial<Omit<OfflineRuntimeState, 'id'>>,
): Promise<OfflineRuntimeState> {
    const current = await getRuntime();
    const next = { ...current, ...patch, id: 'runtime' as const };
    await playerOfflineDb.runtime.put(next);

    return next;
}
