import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { PairingQr } from '@/components/player/pairing-qr';
import {
    PlaylistPreviewPlayer,
    type PlaylistPlayerItem,
} from '@/components/playlists/playlist-preview-player';
import {
    LayoutRenderer,
    type LayoutMediaMap,
} from '@/components/rendering/layout-renderer';
import { Spinner } from '@/components/ui/spinner';
import {
    clearAllOfflineData,
    getActivePackage,
    getRuntime,
    patchRuntime,
    type OfflineManifestPayload,
    type OfflinePackage,
} from '@/lib/player/offline-db';
import { registerPlayerServiceWorker } from '@/lib/player/register-sw';
import {
    nextOfflineBoundaryMs,
    resolveOfflineContent,
} from '@/lib/player/resolve-offline-content';
import { syncOfflinePackage } from '@/lib/player/sync-offline-package';
import { startWidgetDataSync } from '@/lib/player/widget-data-sync';
import {
    resetPlaybackTracking,
    trackContentApplied,
} from '@/lib/player/playback-telemetry';
import { isLayoutSchema, type LayoutSchema } from '@/types/layout-schema';
import type {
    PlaylistTransition,
    PlaylistTransitionSpeed,
} from '@/types/playlist';

const DEVICE_TOKEN_KEY = 'dz_player_device_token';
const PAIR_POLL_MS = 2500;
const CHECK_POLL_MS = 4000;
const DEFAULT_HEARTBEAT_MS = 45_000;
const MIN_HEARTBEAT_MS = 15_000;
const PLAYER_VERSION = import.meta.env.VITE_DZ_PLAYER_VERSION || '1.0.0';

type PlayerView =
    | 'loading'
    | 'pairing'
    | 'ready'
    | 'no_content'
    | 'no_offline_content'
    | 'inactive'
    | 'error';

type PlayerErrorCode =
    | 'manifest_invalid'
    | 'media_unavailable'
    | 'renderer_failure'
    | 'unknown';

type PairingSession = {
    public_id: string;
    code: string;
    pair_url: string;
    expires_in_seconds: number;
};

type DeploymentContentType = 'screen_design' | 'playlist';

type ManifestMedia = Record<
    string,
    { id?: number; type?: string; url?: string; mime?: string }
>;

/** Playlist manifests carry media per item, not at the top level. */
type ManifestPlaylistItem = {
    position?: number;
    name?: string | null;
    durationSeconds?: number;
    loopCount?: number;
    transition?: string;
    transitionSpeed?: string;
    screenDesignId?: number;
    screenDesignVersionId?: number | null;
    schema?: unknown;
    media?: ManifestMedia;
};

type ManifestPayload = OfflineManifestPayload & {
    items?: ManifestPlaylistItem[];
    media?: ManifestMedia;
};

function readStoredToken(): string | null {
    try {
        const value = window.localStorage.getItem(DEVICE_TOKEN_KEY);
        return value && value.length > 0 ? value : null;
    } catch {
        return null;
    }
}

function persistDeviceToken(token: string): void {
    try {
        window.localStorage.setItem(DEVICE_TOKEN_KEY, token);
    } catch {
        // Cookie still enables authenticated media requests.
    }
    document.cookie = `${DEVICE_TOKEN_KEY}=${encodeURIComponent(token)}; path=/; SameSite=Lax`;
}

function clearDeviceToken(): void {
    try {
        window.localStorage.removeItem(DEVICE_TOKEN_KEY);
    } catch {
        // ignore
    }
    document.cookie = `${DEVICE_TOKEN_KEY}=; path=/; Max-Age=0; SameSite=Lax`;
}

async function playerFetch(
    url: string,
    init: RequestInit = {},
    token?: string | null,
): Promise<Response> {
    const headers = new Headers(init.headers);
    if (token) {
        headers.set('X-Device-Token', token);
    }
    headers.set('Accept', 'application/json');
    if (init.method && init.method !== 'GET' && init.method !== 'HEAD') {
        headers.set('Content-Type', 'application/json');
    }

    return fetch(url, {
        ...init,
        headers,
        credentials: 'same-origin',
    });
}

function mediaMapFromManifest(
    media: ManifestMedia | undefined,
): LayoutMediaMap {
    if (!media) {
        return {};
    }

    const map: LayoutMediaMap = {};
    for (const [id, entry] of Object.entries(media)) {
        const ref = { url: entry.url ?? null, type: entry.type };
        map[id] = ref;
        const numeric = Number(id);
        if (!Number.isNaN(numeric)) {
            map[numeric] = ref;
        }
    }
    return map;
}

/** Playlist media is per item — merge into one map for the renderer. */
function mediaMapFromItems(
    items: ManifestPlaylistItem[] | undefined,
): LayoutMediaMap {
    const map: LayoutMediaMap = {};
    for (const item of items ?? []) {
        Object.assign(map, mediaMapFromManifest(item.media));
    }
    return map;
}

function normalizeTransition(value: unknown): PlaylistTransition {
    return value === 'none' ||
        value === 'fade' ||
        value === 'slide_left' ||
        value === 'slide_right'
        ? value
        : 'fade';
}

function normalizeTransitionSpeed(value: unknown): PlaylistTransitionSpeed {
    return value === 'fast' || value === 'slow' || value === 'normal'
        ? value
        : 'normal';
}

/**
 * Manifest playlist items → player items. Items without a renderable schema
 * are dropped so a single bad item cannot blank the screen.
 */
function playlistItemsFromManifest(
    items: ManifestPlaylistItem[] | undefined,
): PlaylistPlayerItem[] {
    if (!items) {
        return [];
    }

    const playerItems: PlaylistPlayerItem[] = [];

    items.forEach((item, index) => {
        if (!isLayoutSchema(item.schema)) {
            return;
        }

        playerItems.push({
            key: String(item.position ?? index),
            name: item.name ?? `Item ${index + 1}`,
            schema: item.schema,
            duration_seconds:
                typeof item.durationSeconds === 'number' &&
                Number.isFinite(item.durationSeconds)
                    ? item.durationSeconds
                    : 10,
            loop_count:
                typeof item.loopCount === 'number' &&
                Number.isFinite(item.loopCount)
                    ? Math.max(1, Math.round(item.loopCount))
                    : 1,
            transition: normalizeTransition(item.transition),
            transition_speed: normalizeTransitionSpeed(item.transitionSpeed),
            // The manifest only carries active items.
            is_active: true,
        });
    });

    return playerItems;
}

function playbackStateForView(view: PlayerView): string {
    switch (view) {
        case 'ready':
            return 'ready';
        case 'no_content':
        case 'no_offline_content':
            return 'no_content';
        case 'inactive':
            return 'inactive';
        case 'error':
            return 'error';
        case 'pairing':
            return 'pairing';
        default:
            return 'rendering';
    }
}

function formatCountdown(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

/**
 * Standalone screen player — dark canvas, no app chrome / theme.
 */
export default function Player() {
    const [view, setView] = useState<PlayerView>('loading');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [pairing, setPairing] = useState<PairingSession | null>(null);
    const [expiresIn, setExpiresIn] = useState(0);
    const [schema, setSchema] = useState<LayoutSchema | null>(null);
    const [contentType, setContentType] =
        useState<DeploymentContentType>('screen_design');
    const [playlistItems, setPlaylistItems] = useState<PlaylistPlayerItem[]>(
        [],
    );
    /** Remount key — a new deployment must restart playback from item one. */
    const [contentVersion, setContentVersion] = useState('');
    const [mediaMap, setMediaMap] = useState<LayoutMediaMap>({});
    const [widgetData, setWidgetData] = useState<Record<string, unknown>>({});
    const [isOnline, setIsOnline] = useState(
        typeof navigator !== 'undefined' ? navigator.onLine : true,
    );
    const [fit, setFit] = useState({
        width: typeof window !== 'undefined' ? window.innerWidth : 1920,
        height: typeof window !== 'undefined' ? window.innerHeight : 1080,
    });

    const deploymentVersionRef = useRef<string | null>(null);
    const deploymentIdRef = useRef<number | null>(null);
    const tokenRef = useRef<string | null>(null);
    const pairIntervalRef = useRef<number | null>(null);
    const checkIntervalRef = useRef<number | null>(null);
    const heartbeatIntervalRef = useRef<number | null>(null);
    const scheduleTimerRef = useRef<number | null>(null);
    const packageRef = useRef<OfflinePackage | null>(null);
    const schemaRef = useRef<LayoutSchema | null>(null);
    const heartbeatMsRef = useRef<number>(DEFAULT_HEARTBEAT_MS);
    const viewRef = useRef<PlayerView>('loading');
    const errorCodeRef = useRef<PlayerErrorCode | null>(null);
    const mountedRef = useRef(true);

    function clearPairInterval() {
        if (pairIntervalRef.current !== null) {
            window.clearInterval(pairIntervalRef.current);
            pairIntervalRef.current = null;
        }
    }

    function clearCheckInterval() {
        if (checkIntervalRef.current !== null) {
            window.clearInterval(checkIntervalRef.current);
            checkIntervalRef.current = null;
        }
    }

    function clearHeartbeatInterval() {
        if (heartbeatIntervalRef.current !== null) {
            window.clearInterval(heartbeatIntervalRef.current);
            heartbeatIntervalRef.current = null;
        }
    }

    function clearScheduleTimer() {
        if (scheduleTimerRef.current !== null) {
            window.clearTimeout(scheduleTimerRef.current);
            scheduleTimerRef.current = null;
        }
    }

    useEffect(() => {
        viewRef.current = view;
    }, [view]);

    useEffect(() => {
        schemaRef.current = schema;
    }, [schema]);

    useEffect(() => {
        const onOnline = () => setIsOnline(true);
        const onOffline = () => setIsOnline(false);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, []);

    useEffect(() => {
        if (view !== 'ready' || !isOnline) {
            return;
        }

        return startWidgetDataSync({
            getSchema: () => schemaRef.current,
            getToken: () => tokenRef.current,
            isOnline: () =>
                typeof navigator !== 'undefined' ? navigator.onLine : true,
            onData: (data) => {
                if (!mountedRef.current) {
                    return;
                }
                setWidgetData((prev) => ({ ...prev, ...data }));
            },
        });
    }, [view, isOnline, contentVersion]);

    useEffect(() => {
        mountedRef.current = true;
        void (async () => {
            await registerPlayerServiceWorker();
            await bootstrap();
        })();

        return () => {
            mountedRef.current = false;
            clearPairInterval();
            clearCheckInterval();
            clearHeartbeatInterval();
            clearScheduleTimer();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- mount once
    }, []);

    useEffect(() => {
        if (view !== 'pairing') {
            return;
        }
        const timer = window.setInterval(() => {
            setExpiresIn((prev) => Math.max(0, prev - 1));
        }, 1000);
        return () => window.clearInterval(timer);
    }, [view]);

    useEffect(() => {
        if (view !== 'ready' && view !== 'no_content' && view !== 'inactive') {
            return;
        }

        const measure = () => {
            setFit({
                width: window.innerWidth,
                height: window.innerHeight,
            });
        };
        measure();
        window.addEventListener('resize', measure);
        return () => window.removeEventListener('resize', measure);
    }, [view]);

    async function applyPackage(pkg: OfflinePackage) {
        packageRef.current = pkg;
        if (pkg.widgetData && typeof pkg.widgetData === 'object') {
            setWidgetData(pkg.widgetData);
        }
        const resolved = resolveOfflineContent(pkg) as ManifestPayload;
        applyManifest(resolved);
        scheduleOfflineChangeover(pkg);
    }

    function scheduleOfflineChangeover(pkg: OfflinePackage) {
        clearScheduleTimer();
        const boundary = nextOfflineBoundaryMs(pkg);
        if (boundary === null) {
            return;
        }

        const delay = Math.max(250, boundary - Date.now());
        scheduleTimerRef.current = window.setTimeout(
            () => {
                if (!mountedRef.current || packageRef.current !== pkg) {
                    return;
                }
                void applyPackage(pkg);
            },
            Math.min(delay, 2_147_000_000),
        );
    }

    async function bootstrap() {
        clearPairInterval();
        clearCheckInterval();
        clearHeartbeatInterval();
        clearScheduleTimer();
        setView('loading');
        setErrorMessage(null);
        errorCodeRef.current = null;

        const existing = readStoredToken();
        if (existing) {
            tokenRef.current = existing;
            persistDeviceToken(existing);

            // Boot from cache first so offline reload never blanks the screen.
            const cached = await getActivePackage();
            if (cached && mountedRef.current) {
                await applyPackage(cached);
            }

            // When already offline with a cached package, skip the network sync —
            // a hanging fetch must never leave the Player stuck on Loading.
            if (!navigator.onLine && cached) {
                startAuthenticatedPolling(existing);
                return;
            }

            const ok = await loadManifest(existing);
            if (!mountedRef.current) {
                return;
            }
            if (ok) {
                startAuthenticatedPolling(existing);
                return;
            }

            if (cached) {
                startAuthenticatedPolling(existing);
                return;
            }
        }

        await startPairing();
    }

    async function startPairing() {
        clearPairInterval();
        clearCheckInterval();
        clearHeartbeatInterval();
        deploymentIdRef.current = null;
        if (!mountedRef.current) {
            return;
        }
        setView('loading');

        try {
            const response = await playerFetch('/player/api/pairing-sessions', {
                method: 'POST',
                body: '{}',
            });

            if (!response.ok) {
                throw new Error('Unable to start pairing.');
            }

            const data = (await response.json()) as PairingSession;

            if (!mountedRef.current) {
                return;
            }

            setPairing({
                public_id: data.public_id,
                code: data.code,
                pair_url: data.pair_url,
                expires_in_seconds: data.expires_in_seconds,
            });
            setExpiresIn(data.expires_in_seconds);
            setView('pairing');
            startPairPolling(data.public_id);
        } catch {
            if (mountedRef.current) {
                setErrorMessage(
                    'Could not start pairing. Check your connection and reload.',
                );
                setView('error');
            }
        }
    }

    function startPairPolling(publicId: string) {
        clearPairInterval();

        const poll = async () => {
            if (!mountedRef.current) {
                return;
            }

            try {
                const response = await playerFetch(
                    `/player/api/pairing-sessions/${encodeURIComponent(publicId)}`,
                );
                if (!response.ok) {
                    return;
                }

                const data = (await response.json()) as {
                    status: string;
                    device_token?: string;
                    expires_in_seconds?: number;
                };

                if (data.status === 'pending') {
                    if (typeof data.expires_in_seconds === 'number') {
                        setExpiresIn(data.expires_in_seconds);
                    }
                    return;
                }

                if (data.status === 'expired') {
                    clearPairInterval();
                    await startPairing();
                    return;
                }

                if (data.status === 'claimed' && data.device_token) {
                    clearPairInterval();
                    persistDeviceToken(data.device_token);
                    tokenRef.current = data.device_token;
                    setView('loading');
                    const ok = await loadManifest(data.device_token);
                    if (!mountedRef.current) {
                        return;
                    }
                    if (ok) {
                        startAuthenticatedPolling(data.device_token);
                    } else {
                        await startPairing();
                    }
                    return;
                }

                if (data.status === 'claimed') {
                    // Token already consumed (e.g. another tab) — start a fresh session.
                    clearPairInterval();
                    await startPairing();
                }
            } catch {
                // Keep polling on transient errors.
            }
        };

        pairIntervalRef.current = window.setInterval(() => {
            void poll();
        }, PAIR_POLL_MS);
        void poll();
    }

    async function loadManifest(token: string): Promise<boolean> {
        try {
            const result = await syncOfflinePackage(token);

            if (result.status === 'failed' && result.error === 'revoked') {
                clearDeviceToken();
                tokenRef.current = null;
                deploymentVersionRef.current = null;
                packageRef.current = null;
                await clearAllOfflineData();
                return false;
            }

            if (
                (result.status === 'activated' ||
                    result.status === 'unchanged') &&
                result.package
            ) {
                if (!mountedRef.current) {
                    return true;
                }
                await applyPackage(result.package);
                return true;
            }

            // Sync failed — keep playing from cache when available.
            const cached = result.package ?? (await getActivePackage());
            if (cached) {
                await patchRuntime({
                    online: false,
                    lastOfflineAt: new Date().toISOString(),
                    syncError: result.status === 'failed' ? result.error : null,
                });
                if (mountedRef.current) {
                    await applyPackage(cached);
                }
                return true;
            }

            if (mountedRef.current) {
                setView('no_offline_content');
            }
            return true;
        } catch {
            const cached = await getActivePackage();
            if (cached && mountedRef.current) {
                await applyPackage(cached);
                return true;
            }

            if (mountedRef.current) {
                errorCodeRef.current = 'unknown';
                setErrorMessage('Unable to load screen content.');
                setView('error');
            }
            return true;
        }
    }

    function clearContent() {
        setSchema(null);
        setPlaylistItems([]);
        setContentType('screen_design');
        setContentVersion('');
        setMediaMap({});
        setWidgetData({});
        deploymentVersionRef.current = null;
        deploymentIdRef.current = null;
        errorCodeRef.current = null;
        resetPlaybackTracking();
    }

    function applyManifest(manifest: ManifestPayload) {
        const status = manifest.status ?? 'error';

        if (status === 'inactive') {
            clearContent();
            setView('inactive');
            return;
        }

        if (status === 'no_content') {
            clearContent();
            setView('no_content');
            return;
        }

        // Manifests written before Phase 7 carry no content type.
        const manifestContentType = manifest.contentType ?? 'screen_design';

        if (status === 'ready' && manifestContentType === 'playlist') {
            const items = playlistItemsFromManifest(manifest.items);

            if (items.length > 0) {
                deploymentVersionRef.current =
                    manifest.deploymentVersion ?? null;
                deploymentIdRef.current = manifest.deploymentId ?? null;
                errorCodeRef.current = null;
                setSchema(null);
                setContentType('playlist');
                setContentVersion(
                    manifest.deploymentVersion ??
                        String(manifest.deploymentId ?? 'playlist'),
                );
                setPlaylistItems(items);
                setMediaMap(mediaMapFromItems(manifest.items));
                setView('ready');
                trackContentApplied(tokenRef.current, {
                    deploymentId: manifest.deploymentId ?? null,
                    screenDesignVersionId:
                        manifest.items?.[0]?.screenDesignVersionId ?? null,
                    playlistVersionId: manifest.playlistVersionId ?? null,
                    scheduleId: manifest.scheduleId ?? null,
                    contentKey:
                        manifest.deploymentVersion ??
                        `playlist:${manifest.deploymentId ?? 'x'}:${manifest.playlistVersionId ?? 'x'}`,
                });
                return;
            }
        }

        if (
            status === 'ready' &&
            manifestContentType === 'screen_design' &&
            isLayoutSchema(manifest.schema)
        ) {
            deploymentVersionRef.current = manifest.deploymentVersion ?? null;
            deploymentIdRef.current = manifest.deploymentId ?? null;
            errorCodeRef.current = null;
            setContentType('screen_design');
            setPlaylistItems([]);
            setSchema(manifest.schema);
            setMediaMap(mediaMapFromManifest(manifest.media));
            setView('ready');
            trackContentApplied(tokenRef.current, {
                deploymentId: manifest.deploymentId ?? null,
                screenDesignVersionId: manifest.screenDesignVersionId ?? null,
                playlistVersionId: null,
                scheduleId: manifest.scheduleId ?? null,
                contentKey:
                    manifest.deploymentVersion ??
                    `design:${manifest.deploymentId ?? 'x'}:${manifest.screenDesignVersionId ?? 'x'}`,
            });
            return;
        }

        // Keep last good frame if a package resolve is briefly incomplete.
        if (viewRef.current === 'ready') {
            return;
        }

        errorCodeRef.current = 'manifest_invalid';
        setErrorMessage('This screen received an unexpected content response.');
        setView('error');
    }

    function startAuthenticatedPolling(token: string) {
        startCheckPolling(token);
        startHeartbeat(token);
    }

    /**
     * Presence heartbeat. Failures are intentionally silent: a screen that
     * cannot report in must keep rendering its last known content.
     */
    async function sendHeartbeat(token: string) {
        if (!mountedRef.current || tokenRef.current !== token) {
            return;
        }

        const currentView = viewRef.current;
        const width = window.innerWidth;
        const height = window.innerHeight;
        const runtime = await getRuntime();

        const payload: Record<string, unknown> = {
            player_version: PLAYER_VERSION,
            viewport_width: width,
            viewport_height: height,
            orientation: height > width ? 'portrait' : 'landscape',
            deployment_id: deploymentIdRef.current,
            playback_state: playbackStateForView(currentView),
            metadata: {
                offline: {
                    cache_ready: runtime.cacheReady,
                    package_version: runtime.activePackageVersion,
                    last_sync_at: runtime.lastSuccessfulSyncAt,
                    last_offline_at: runtime.lastOfflineAt,
                    sync_error: runtime.syncError,
                },
            },
        };

        if (currentView === 'error') {
            payload.error_code = errorCodeRef.current ?? 'unknown';
        }

        try {
            const response = await playerFetch(
                '/player/api/heartbeat',
                { method: 'POST', body: JSON.stringify(payload) },
                token,
            );

            if (response.status === 401) {
                clearDeviceToken();
                tokenRef.current = null;
                packageRef.current = null;
                await clearAllOfflineData();
                clearCheckInterval();
                clearHeartbeatInterval();
                await startPairing();
                return;
            }

            if (!response.ok) {
                await patchRuntime({
                    online: false,
                    lastOfflineAt: new Date().toISOString(),
                });
                return;
            }

            await patchRuntime({ online: true });

            const data = (await response.json()) as {
                heartbeat_interval_seconds?: number;
            };

            const seconds = data.heartbeat_interval_seconds;
            if (typeof seconds !== 'number' || !Number.isFinite(seconds)) {
                return;
            }

            const nextMs = Math.max(
                MIN_HEARTBEAT_MS,
                Math.round(seconds) * 1000,
            );
            if (
                nextMs !== heartbeatMsRef.current &&
                mountedRef.current &&
                tokenRef.current === token
            ) {
                heartbeatMsRef.current = nextMs;
                scheduleHeartbeat(token);
            }
        } catch {
            await patchRuntime({
                online: false,
                lastOfflineAt: new Date().toISOString(),
            });
        }
    }

    function scheduleHeartbeat(token: string) {
        clearHeartbeatInterval();
        heartbeatIntervalRef.current = window.setInterval(() => {
            void sendHeartbeat(token);
        }, heartbeatMsRef.current);
    }

    function startHeartbeat(token: string) {
        scheduleHeartbeat(token);
        void sendHeartbeat(token);
    }

    function startCheckPolling(token: string) {
        clearCheckInterval();

        const poll = async () => {
            if (!mountedRef.current || tokenRef.current !== token) {
                return;
            }

            try {
                const response = await playerFetch(
                    '/player/api/manifest/check',
                    { method: 'GET' },
                    token,
                );

                if (response.status === 401) {
                    clearDeviceToken();
                    tokenRef.current = null;
                    packageRef.current = null;
                    await clearAllOfflineData();
                    clearCheckInterval();
                    await startPairing();
                    return;
                }

                if (!response.ok) {
                    await patchRuntime({
                        online: false,
                        lastOfflineAt: new Date().toISOString(),
                    });
                    return;
                }

                await patchRuntime({ online: true });

                const data = (await response.json()) as {
                    version?: string | null;
                    screen_active?: boolean;
                };

                if (data.screen_active === false) {
                    setView('inactive');
                    return;
                }

                const nextVersion = data.version ?? null;
                if (nextVersion !== deploymentVersionRef.current) {
                    await loadManifest(token);
                }
            } catch {
                await patchRuntime({
                    online: false,
                    lastOfflineAt: new Date().toISOString(),
                });
                // Keep last known content; local schedule timer still runs.
                if (packageRef.current) {
                    scheduleOfflineChangeover(packageRef.current);
                }
            }
        };

        checkIntervalRef.current = window.setInterval(() => {
            void poll();
        }, CHECK_POLL_MS);
    }

    return (
        <>
            <Head title="Player">
                <link rel="manifest" href="/player.webmanifest" />
                <meta name="theme-color" content="#0a0a0a" />
            </Head>
            <div
                data-test="player-root"
                className="flex min-h-dvh w-full flex-col bg-[#0b0f14] text-[#f4f7fa]"
            >
                {view === 'loading' ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6">
                        <Spinner className="size-8 text-[#5eead4]" />
                        <p className="text-sm text-[#94a3b8]">Loading…</p>
                    </div>
                ) : null}

                {view === 'pairing' && pairing ? (
                    <div
                        data-test="player-pairing"
                        className="flex flex-1 flex-col items-center justify-center gap-8 px-6 py-10 text-center"
                    >
                        <p className="font-display text-sm font-semibold tracking-[0.28em] text-[#5eead4] uppercase">
                            RMSignage
                        </p>
                        <PairingQr url={pairing.pair_url} size={240} />
                        <div>
                            <p
                                data-test="player-pairing-code"
                                className="font-mono text-4xl font-semibold tracking-[0.35em] text-white sm:text-5xl"
                                aria-label={`Pairing code ${pairing.code}`}
                            >
                                {pairing.code}
                            </p>
                            <p className="mt-4 max-w-md text-sm text-[#94a3b8]">
                                Keep this screen open. In RMSignage go to Paired
                                TVs → Pair a TV.
                            </p>
                            <p className="mt-2 text-xs text-[#64748b]">
                                Expires in {formatCountdown(expiresIn)}
                            </p>
                        </div>
                    </div>
                ) : null}

                {view === 'no_content' ? (
                    <div
                        data-test="player-no-content"
                        className="flex flex-1 flex-col items-center justify-center px-6 text-center"
                    >
                        <p className="font-display text-sm font-semibold tracking-[0.28em] text-[#5eead4] uppercase">
                            RMSignage
                        </p>
                        <h1 className="mt-4 max-w-lg text-2xl font-semibold tracking-tight sm:text-3xl">
                            This screen is connected and ready. Publish a design
                            from RMSignage.
                        </h1>
                    </div>
                ) : null}

                {view === 'no_offline_content' ? (
                    <div
                        data-test="player-no-offline-content"
                        className="flex flex-1 flex-col items-center justify-center px-6 text-center"
                    >
                        <p className="font-display text-sm font-semibold tracking-[0.28em] text-[#5eead4] uppercase">
                            RMSignage
                        </p>
                        <h1 className="mt-4 max-w-lg text-2xl font-semibold tracking-tight sm:text-3xl">
                            No offline content is available yet.
                        </h1>
                        <p className="mt-3 max-w-md text-sm text-[#94a3b8]">
                            Connect to the network so this screen can
                            synchronise content.
                        </p>
                    </div>
                ) : null}

                {view === 'inactive' ? (
                    <div
                        data-test="player-inactive"
                        className="flex flex-1 flex-col items-center justify-center px-6 text-center"
                    >
                        <p className="text-lg text-[#94a3b8]">
                            This screen is currently inactive.
                        </p>
                    </div>
                ) : null}

                {view === 'error' ? (
                    <div
                        data-test="player-error"
                        className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center"
                    >
                        <p className="max-w-md text-lg text-[#f87171]">
                            {errorMessage ?? 'Something went wrong.'}
                        </p>
                        <button
                            type="button"
                            className="rounded-md bg-[#5eead4] px-4 py-2 text-sm font-medium text-[#0b0f14]"
                            onClick={() => void bootstrap()}
                        >
                            Try again
                        </button>
                    </div>
                ) : null}

                {view === 'ready' &&
                contentType === 'playlist' &&
                playlistItems.length > 0 ? (
                    <div
                        data-test="player-ready"
                        data-content-type="playlist"
                        className="flex min-h-dvh w-full items-center justify-center overflow-hidden"
                    >
                        <PlaylistPreviewPlayer
                            key={contentVersion}
                            items={playlistItems}
                            mediaMap={mediaMap}
                            showControls={false}
                            autoPlay
                            loop
                            runtime="player"
                            widgetData={widgetData}
                            isOnline={isOnline}
                            className="min-h-dvh w-full"
                            stageClassName="rounded-none"
                        />
                    </div>
                ) : null}

                {view === 'ready' &&
                contentType === 'screen_design' &&
                schema ? (
                    <div
                        data-test="player-ready"
                        data-content-type="screen_design"
                        className="flex min-h-dvh w-full items-center justify-center overflow-hidden"
                    >
                        <LayoutRenderer
                            schema={schema}
                            mode="preview"
                            runtime="player"
                            fitWidth={fit.width}
                            fitHeight={fit.height}
                            mediaMap={mediaMap}
                            widgetData={widgetData}
                            isOnline={isOnline}
                        />
                    </div>
                ) : null}
            </div>
        </>
    );
}
