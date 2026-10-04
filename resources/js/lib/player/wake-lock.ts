/**
 * Keep the TV from dimming while content is on screen. Silent no-op when
 * Wake Lock is unavailable (Fire TV WebView / older Silk).
 */
export function startPlayerWakeLock(): () => void {
    if (typeof navigator === 'undefined' || !('wakeLock' in navigator)) {
        return () => {
            // no-op
        };
    }

    let released = false;
    let sentinel: WakeLockSentinel | null = null;

    const request = async () => {
        if (released || document.visibilityState !== 'visible') {
            return;
        }

        try {
            sentinel = await navigator.wakeLock.request('screen');
            sentinel.addEventListener('release', () => {
                if (!released) {
                    void request();
                }
            });
        } catch {
            // Permission / unsupported — Player still runs.
        }
    };

    void request();
    const onVisibility = () => {
        void request();
    };
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
        released = true;
        document.removeEventListener('visibilitychange', onVisibility);
        void sentinel?.release();
        sentinel = null;
    };
}
