/* RMSignage customer PWA service worker.
 * Registered with scope `/app/` so it never controls `/player` or `/admin`.
 * Network-only: do not cache customer app documents (auth/session pages).
 * The fetch listener exists so Chromium can offer “Install app”.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(fetch(event.request));
});
