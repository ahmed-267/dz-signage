import type { CSSProperties } from 'react';
import type {
    PlaylistTransition,
    PlaylistTransitionSpeed,
} from '@/types/playlist';

/** Mirrors `App\Enums\PlaylistTransitionSpeed::milliseconds()`. */
const SPEED_MS: Record<PlaylistTransitionSpeed, number> = {
    fast: 250,
    normal: 500,
    slow: 900,
};

/**
 * Transition styling for playlist playback. Two stacked layers cross-fade or
 * slide: the incoming item animates from `enter` → `settled`, the outgoing
 * item from `settled` → `leave`.
 */
export type PlaylistTransitionPhase = 'enter' | 'settled' | 'leave';

export function transitionSpeedMs(speed: PlaylistTransitionSpeed): number {
    return SPEED_MS[speed] ?? SPEED_MS.normal;
}

export function playlistTransitionStyle(
    transition: PlaylistTransition,
    speed: PlaylistTransitionSpeed,
    phase: PlaylistTransitionPhase,
): CSSProperties {
    if (transition === 'none') {
        return {
            opacity: phase === 'leave' ? 0 : 1,
        };
    }

    const durationMs = transitionSpeedMs(speed);
    const base: CSSProperties = {
        transition: `opacity ${durationMs}ms ease, transform ${durationMs}ms ease`,
    };

    if (phase === 'settled' || transition === 'fade') {
        return {
            ...base,
            opacity:
                phase === 'leave' ||
                (transition === 'fade' && phase !== 'settled')
                    ? 0
                    : 1,
        };
    }

    // Slide direction is the direction of travel: `slide_left` moves content
    // leftwards, so the incoming item waits on the right.
    const offscreen =
        transition === 'slide_left'
            ? phase === 'enter'
                ? '100%'
                : '-100%'
            : phase === 'enter'
              ? '-100%'
              : '100%';

    return { ...base, opacity: 1, transform: `translateX(${offscreen})` };
}
