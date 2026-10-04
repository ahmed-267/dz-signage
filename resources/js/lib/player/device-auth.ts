export const DEVICE_TOKEN_KEY = 'dz_player_device_token';

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 400;

export type DeviceCredentials = {
    id: 'device';
    token: string;
};

function readCookieToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const parts = document.cookie.split(';');
    for (const part of parts) {
        const trimmed = part.trim();
        if (trimmed.startsWith(`${DEVICE_TOKEN_KEY}=`)) {
            const value = decodeURIComponent(
                trimmed.slice(DEVICE_TOKEN_KEY.length + 1),
            );

            return value.length > 0 ? value : null;
        }
    }

    return null;
}

function writeCookieToken(token: string | null): void {
    if (typeof document === 'undefined') {
        return;
    }

    const secure =
        typeof window !== 'undefined' && window.location.protocol === 'https:'
            ? '; Secure'
            : '';

    if (token === null) {
        document.cookie = `${DEVICE_TOKEN_KEY}=; path=/; Max-Age=0; SameSite=Lax${secure}`;
        return;
    }

    document.cookie = `${DEVICE_TOKEN_KEY}=${encodeURIComponent(token)}; path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

function readLocalStorageToken(): string | null {
    try {
        const value = window.localStorage.getItem(DEVICE_TOKEN_KEY);
        return value && value.length > 0 ? value : null;
    } catch {
        return null;
    }
}

function writeLocalStorageToken(token: string | null): void {
    try {
        if (token === null) {
            window.localStorage.removeItem(DEVICE_TOKEN_KEY);
            return;
        }
        window.localStorage.setItem(DEVICE_TOKEN_KEY, token);
    } catch {
        // Private mode / quota — cookie + IndexedDB still apply.
    }
}

async function readIndexedDbToken(): Promise<string | null> {
    try {
        const { playerOfflineDb } = await import('@/lib/player/offline-db');
        const row = await playerOfflineDb.credentials.get('device');
        return row?.token && row.token.length > 0 ? row.token : null;
    } catch {
        return null;
    }
}

async function writeIndexedDbToken(token: string | null): Promise<void> {
    try {
        const { playerOfflineDb } = await import('@/lib/player/offline-db');
        if (token === null) {
            await playerOfflineDb.credentials.delete('device');
            return;
        }
        await playerOfflineDb.credentials.put({ id: 'device', token });
    } catch {
        // ignore
    }
}

/**
 * Restore a pairing token after a TV / APK / browser restart.
 * IndexedDB survives many WebView restarts; localStorage and a long-lived
 * cookie cover the rest. Never treat a missing cookie as unpaired if IDB has a token.
 */
export async function readStoredToken(): Promise<string | null> {
    const indexed = await readIndexedDbToken();
    if (indexed) {
        writeLocalStorageToken(indexed);
        writeCookieToken(indexed);
        return indexed;
    }

    const local = readLocalStorageToken();
    if (local) {
        writeCookieToken(local);
        void writeIndexedDbToken(local);
        return local;
    }

    const cookie = readCookieToken();
    if (cookie) {
        writeLocalStorageToken(cookie);
        void writeIndexedDbToken(cookie);
        return cookie;
    }

    return null;
}

export function persistDeviceToken(token: string): void {
    writeLocalStorageToken(token);
    writeCookieToken(token);
    void writeIndexedDbToken(token);
}

export function clearDeviceToken(): void {
    writeLocalStorageToken(null);
    writeCookieToken(null);
    void writeIndexedDbToken(null);
}

export function collectDeviceMeta(playerVersion: string): Record<string, unknown> {
    if (typeof window === 'undefined') {
        return { player_version: playerVersion };
    }

    const ua = window.navigator.userAgent;
    const isTv = /AFT|Silk\/|Android TV|BRAVIA|CrKey|SmartTV|HbbTV|TV Safari/i.test(
        ua,
    );

    return {
        user_agent: ua.slice(0, 1024),
        platform: window.navigator.platform || null,
        language: window.navigator.language || null,
        form_factor: isTv ? 'tv' : 'unknown',
        player_version: playerVersion,
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight,
        },
    };
}
