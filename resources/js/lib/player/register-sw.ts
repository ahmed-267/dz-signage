import { SHELL_CACHE } from '@/lib/player/offline-db';

/**
 * Register the Player service worker. Safe to call repeatedly.
 * New versions wait for a safe reload (skipWaiting is not automatic).
 */
export async function registerPlayerServiceWorker(): Promise<void> {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register(
            '/player-sw.js',
            { scope: '/' },
        );

        await warmPlayerShellCache();

        registration.update().catch(() => {
            // ignore
        });
    } catch {
        // SW unavailable (private mode / insecure context) — online Player still works.
    }
}

/**
 * Cache the Player document and the script/style URLs it actually loaded so an
 * offline reload can boot without hitting the network.
 */
export async function warmPlayerShellCache(): Promise<void> {
    if (typeof window === 'undefined' || !('caches' in window)) {
        return;
    }

    try {
        const cache = await caches.open(SHELL_CACHE);
        const urls = new Set<string>(['/player', '/player.webmanifest']);

        document
            .querySelectorAll('script[src], link[rel="stylesheet"]')
            .forEach((node) => {
                const el = node as HTMLScriptElement | HTMLLinkElement;
                const href =
                    'src' in el && el.src
                        ? el.src
                        : 'href' in el
                          ? el.href
                          : '';
                if (
                    href &&
                    (href.includes('/build/') ||
                        href.includes('/fonts/') ||
                        href.endsWith('.css') ||
                        href.endsWith('.js'))
                ) {
                    urls.add(href);
                }
            });

        await Promise.all(
            [...urls].map(async (url) => {
                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        return;
                    }
                    await cache.put(url, response.clone());
                    const path = new URL(url, window.location.origin).pathname;
                    if (path !== url && !url.startsWith(path)) {
                        await cache.put(path, response.clone());
                    }
                } catch {
                    // Best-effort warm.
                }
            }),
        );
    } catch {
        // ignore quota / private mode
    }
}
