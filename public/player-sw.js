/* RMSignage Player service worker.
 * Scope is `/` so `/build` assets can be cached, but only Player-related
 * requests are intercepted — `/app` and `/admin` are never cached here.
 * New versions wait for a safe reload (skipWaiting is NOT automatic).
 */

const SHELL_CACHE = 'dz-player-shell-v1';
const MEDIA_CACHE = 'dz-player-media-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) =>
                cache
                    .addAll(['/player', '/player.webmanifest'])
                    .catch(() => undefined),
            ),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never touch customer / admin surfaces.
    if (
        url.pathname.startsWith('/app') ||
        url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/login') ||
        url.pathname.startsWith('/register')
    ) {
        return;
    }

    if (url.pathname.startsWith('/player/api/media/')) {
        event.respondWith(mediaResponse(request));
        return;
    }

    // Other player API calls: network-only (manifest/heartbeat/package).
    if (url.pathname.startsWith('/player/api/')) {
        return;
    }

    if (url.pathname === '/player' || url.pathname === '/player/') {
        event.respondWith(networkFirst(request, SHELL_CACHE));
        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/fonts/') ||
        url.pathname === '/favicon.svg' ||
        url.pathname === '/player.webmanifest' ||
        url.pathname.startsWith('/icons/')
    ) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));
    }
});

async function mediaResponse(request) {
    const cache = await caches.open(MEDIA_CACHE);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    try {
        return await fetch(request);
    } catch {
        return new Response('Media unavailable offline', {
            status: 503,
            statusText: 'Service Unavailable',
        });
    }
}

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached =
            (await cache.match(request)) ||
            (await cache.match(new URL(request.url).pathname));
        if (cached) {
            return cached;
        }
        return new Response('Player unavailable offline', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' },
        });
    }
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached =
        (await cache.match(request)) ||
        (await cache.match(new URL(request.url).pathname));
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('', { status: 504 });
    }
}
