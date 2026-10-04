const DISMISS_KEY = 'rmsignage.pwa-install.dismissed-at';
const SNOOZE_MS = 14 * 24 * 60 * 60 * 1000;

export type PwaInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

let deferredPrompt: PwaInstallPromptEvent | null = null;
let listening = false;
const subscribers = new Set<() => void>();

function notify(): void {
    subscribers.forEach((fn) => {
        fn();
    });
}

export function subscribePwaInstall(listener: () => void): () => void {
    subscribers.add(listener);

    return () => {
        subscribers.delete(listener);
    };
}

export function getDeferredPwaPrompt(): PwaInstallPromptEvent | null {
    return deferredPrompt;
}

export function clearDeferredPwaPrompt(): void {
    deferredPrompt = null;
    notify();
}

export function isStandaloneDisplay(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const nav = window.navigator as Navigator & { standalone?: boolean };

    return (
        nav.standalone === true ||
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        window.matchMedia('(display-mode: minimal-ui)').matches
    );
}

export function isIosDevice(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const ua = window.navigator.userAgent;
    const iPhoneLike = /iPad|iPhone|iPod/i.test(ua);
    const iPadOs =
        window.navigator.platform === 'MacIntel' &&
        window.navigator.maxTouchPoints > 1;

    return iPhoneLike || iPadOs;
}

export function isIosChrome(): boolean {
    return isIosDevice() && /CriOS/i.test(window.navigator.userAgent);
}

export function isPwaInstallDismissed(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const raw = window.localStorage.getItem(DISMISS_KEY);
    if (!raw) {
        return false;
    }

    const at = Number.parseInt(raw, 10);
    if (!Number.isFinite(at)) {
        return false;
    }

    return Date.now() - at < SNOOZE_MS;
}

export function dismissPwaInstallPrompt(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
}

export function capturePwaInstallEvents(): void {
    if (typeof window === 'undefined' || listening) {
        return;
    }

    listening = true;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event as PwaInstallPromptEvent;
        notify();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        dismissPwaInstallPrompt();
        notify();
    });
}

export async function registerAppServiceWorker(): Promise<void> {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    if (window.location.pathname.startsWith('/player')) {
        return;
    }

    try {
        await navigator.serviceWorker.register('/sw.js', { scope: '/app/' });
    } catch {
        // Private mode / insecure context — the web app still works without install.
    }
}
