import { Head } from '@inertiajs/react';

/**
 * Standalone screen player surface.
 * Fixed dark canvas so TV/kiosk display stays stable regardless of OS theme.
 */
export default function Player() {
    return (
        <>
            <Head title="Player" />
            <div className="flex min-h-dvh w-full flex-col items-center justify-center bg-[#0b0f14] px-6 text-center text-[#f4f7fa]">
                <p className="text-sm font-medium tracking-[0.25em] text-[#5eead4] uppercase">
                    DZ Signage Player
                </p>
                <h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Player Ready
                </h1>
                <p className="mt-3 max-w-sm text-sm text-[#94a3b8]">
                    Pairing, playback, and offline caching arrive in later
                    phases.
                </p>
            </div>
        </>
    );
}
