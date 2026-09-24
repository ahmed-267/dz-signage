import type { CSSProperties } from 'react';
import type {
    PlaylistTransition,
    PlaylistTransitionSpeed,
} from '@/types/playlist';

/**
 * Mirrors `App\Enums\PlaylistTransitionSpeed::milliseconds()`.
 * Keep in sync — PHP is canonical; this is the client runtime copy.
 */
const SPEED_MS: Record<PlaylistTransitionSpeed, number> = {
    fast: 400,
    normal: 700,
    slow: 1000,
};

/** Smooth ease-in-out — not linear. */
export const PLAYLIST_TRANSITION_EASING =
    'cubic-bezier(0.4, 0, 0.2, 1)' as const;

/**
 * Explicit transition lifecycle used by PlaylistPreviewPlayer / Player.
 *
 * IDLE → PREPARING (mount both layers at start poses)
 *      → ANIMATING (Web Animations API drives both layers)
 *      → COMMIT (swap current, unmount outgoing) → IDLE
 */
export type TransitionLifecycle = 'idle' | 'preparing' | 'animating' | 'commit';

export type PlaylistTransitionLayer = 'outgoing' | 'incoming';

export function transitionSpeedMs(speed: PlaylistTransitionSpeed): number {
    return SPEED_MS[speed] ?? SPEED_MS.normal;
}

export function resolvePlaylistTransition(
    transition: string | null | undefined,
    options?: { preferReducedMotion?: boolean; surface?: 'preview' | 'player' },
): PlaylistTransition {
    const value =
        transition === 'none' ||
        transition === 'fade' ||
        transition === 'slide_left' ||
        transition === 'slide_right'
            ? transition
            : 'fade';

    if (
        options?.preferReducedMotion &&
        options.surface !== 'player' &&
        (value === 'slide_left' || value === 'slide_right')
    ) {
        return 'fade';
    }

    if (options?.preferReducedMotion && options.surface !== 'player') {
        return value === 'none' ? 'none' : 'fade';
    }

    return value;
}

/**
 * Static layout for a transition layer (absolute fill). Animation is driven by
 * the Web Animations API — not CSS transition — so React re-renders cannot
 * cancel mid-flight interpolation.
 */
export function playlistLayerBoxStyle(
    layer: PlaylistTransitionLayer,
): CSSProperties {
    return {
        position: 'absolute',
        inset: 0,
        width: '100%',
        height: '100%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        pointerEvents: 'none',
        backfaceVisibility: 'hidden',
        willChange: 'opacity, transform',
        // Incoming sits above during fade so the blend is visible.
        zIndex: layer === 'incoming' ? 2 : 1,
    };
}

export function playlistIdleLayerStyle(): CSSProperties {
    return {
        ...playlistLayerBoxStyle('outgoing'),
        opacity: 1,
        transform: 'translate3d(0, 0, 0)',
        zIndex: 1,
        willChange: 'auto',
    };
}

/** Start pose applied before WAAPI runs (must match first keyframe). */
export function playlistLayerStartStyle(
    transition: PlaylistTransition,
    layer: PlaylistTransitionLayer,
): CSSProperties {
    const resolved = resolvePlaylistTransition(transition);
    const box = playlistLayerBoxStyle(layer);

    if (resolved === 'none') {
        return {
            ...box,
            opacity: 1,
            transform: 'translate3d(0, 0, 0)',
        };
    }

    if (resolved === 'fade') {
        return {
            ...box,
            opacity: layer === 'outgoing' ? 1 : 0,
            transform: 'translate3d(0, 0, 0)',
        };
    }

    const isLeft = resolved === 'slide_left';
    let translateX = '0%';
    if (layer === 'incoming') {
        translateX = isLeft ? '100%' : '-100%';
    }

    return {
        ...box,
        opacity: 1,
        transform: `translate3d(${translateX}, 0, 0)`,
        // Equal stacking — horizontal offset alone creates the push look.
        zIndex: 1,
    };
}

/**
 * WAAPI keyframes for a layer. Fade crossfades opacities; slides push both.
 */
export function playlistLayerKeyframes(
    transition: PlaylistTransition,
    layer: PlaylistTransitionLayer,
): Keyframe[] {
    const resolved = resolvePlaylistTransition(transition);

    if (resolved === 'none') {
        return layer === 'outgoing'
            ? [{ opacity: 1 }, { opacity: 0 }]
            : [{ opacity: 1 }, { opacity: 1 }];
    }

    if (resolved === 'fade') {
        return layer === 'outgoing'
            ? [
                  { opacity: 1, transform: 'translate3d(0, 0, 0)' },
                  { opacity: 0, transform: 'translate3d(0, 0, 0)' },
              ]
            : [
                  { opacity: 0, transform: 'translate3d(0, 0, 0)' },
                  { opacity: 1, transform: 'translate3d(0, 0, 0)' },
              ];
    }

    const isLeft = resolved === 'slide_left';
    if (layer === 'outgoing') {
        return [
            { opacity: 1, transform: 'translate3d(0%, 0, 0)' },
            {
                opacity: 1,
                transform: `translate3d(${isLeft ? '-100%' : '100%'}, 0, 0)`,
            },
        ];
    }

    return [
        {
            opacity: 1,
            transform: `translate3d(${isLeft ? '100%' : '-100%'}, 0, 0)`,
        },
        { opacity: 1, transform: 'translate3d(0%, 0, 0)' },
    ];
}

/**
 * Run simultaneous WAAPI animations on outgoing + incoming layers.
 * Returns a promise that resolves when both finish (or immediately for none/0ms).
 */
export function runPlaylistLayerAnimations(args: {
    outgoing: HTMLElement | null;
    incoming: HTMLElement | null;
    transition: PlaylistTransition;
    speed: PlaylistTransitionSpeed;
}): Promise<void> {
    const { outgoing, incoming, transition, speed } = args;
    const resolved = resolvePlaylistTransition(transition);
    const duration = resolved === 'none' ? 0 : transitionSpeedMs(speed);

    if (!outgoing || !incoming || duration <= 0) {
        return Promise.resolve();
    }

    const timing: KeyframeAnimationOptions = {
        duration,
        easing: PLAYLIST_TRANSITION_EASING,
        fill: 'forwards',
    };

    const outAnim = outgoing.animate(
        playlistLayerKeyframes(resolved, 'outgoing'),
        timing,
    );
    const inAnim = incoming.animate(
        playlistLayerKeyframes(resolved, 'incoming'),
        timing,
    );

    const settle = (anim: Animation) =>
        anim.finished.then(
            () => undefined,
            () => undefined,
        );

    return Promise.all([settle(outAnim), settle(inAnim)]).then(() => undefined);
}

/** @deprecated Prefer WAAPI helpers above. */
export type PlaylistTransitionPhase = 'enter' | 'settled' | 'leave';

/** @deprecated Prefer playlistLayerStartStyle + runPlaylistLayerAnimations. */
export function playlistLayerStyle(args: {
    transition: PlaylistTransition;
    speed: PlaylistTransitionSpeed;
    layer: PlaylistTransitionLayer;
    lifecycle: Extract<TransitionLifecycle, 'preparing' | 'animating'>;
}): CSSProperties {
    void args.speed;
    void args.lifecycle;
    return playlistLayerStartStyle(args.transition, args.layer);
}

/** @deprecated */
export function playlistTransitionStyle(
    transition: PlaylistTransition,
    speed: PlaylistTransitionSpeed,
    phase: PlaylistTransitionPhase,
): CSSProperties {
    void speed;
    if (phase === 'enter') {
        return playlistLayerStartStyle(transition, 'incoming');
    }
    if (phase === 'leave') {
        return {
            ...playlistLayerStartStyle(transition, 'outgoing'),
            opacity: transition === 'fade' ? 0 : 1,
            transform:
                transition === 'slide_left'
                    ? 'translate3d(-100%, 0, 0)'
                    : transition === 'slide_right'
                      ? 'translate3d(100%, 0, 0)'
                      : 'translate3d(0, 0, 0)',
        };
    }
    return playlistIdleLayerStyle();
}
