import { Pause, Play, RotateCcw, SkipBack, SkipForward } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    playlistIdleLayerStyle,
    playlistLayerStartStyle,
    resolvePlaylistTransition,
    runPlaylistLayerAnimations,
    transitionSpeedMs,
    type TransitionLifecycle,
} from '@/components/playlists/transitions';
import {
    LayoutRenderer,
    type LayoutMediaMap,
} from '@/components/rendering/layout-renderer';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/lib/format-duration';
import { cn } from '@/lib/utils';
import type { LayoutSchema } from '@/types/layout-schema';
import type { WidgetRuntimeMode } from '@/lib/widgets/types';
import type {
    PlaylistTransition,
    PlaylistTransitionSpeed,
} from '@/types/playlist';

export type PlaylistPlayerItem = {
    /** Stable key — playlist item id, or a client key for unsaved items. */
    key: string;
    name: string;
    schema: LayoutSchema;
    /** Seconds for a single play of this item. */
    duration_seconds: number;
    /** How many times to show this item before advancing (default 1). */
    loop_count: number;
    transition: PlaylistTransition;
    transition_speed: PlaylistTransitionSpeed;
    is_active: boolean;
};

type PlaylistPreviewPlayerProps = {
    items: PlaylistPlayerItem[];
    mediaMap?: LayoutMediaMap;
    autoPlay?: boolean;
    loop?: boolean;
    showControls?: boolean;
    /** Must set the height — the stage flexes to fill it. */
    className?: string;
    stageClassName?: string;
    emptyMessage?: string;
    runtime?: WidgetRuntimeMode;
    widgetData?: Record<string, unknown>;
    isOnline?: boolean;
    /**
     * `player` keeps configured transitions even under prefers-reduced-motion.
     * `preview` (default) softens slides to fade when reduced motion is set.
     */
    surface?: 'preview' | 'player';
};

const MIN_ITEM_SECONDS = 1;

function itemDurationMs(seconds: number): number {
    const safe = Number.isFinite(seconds)
        ? Math.max(MIN_ITEM_SECONDS, Math.round(seconds))
        : MIN_ITEM_SECONDS;
    return safe * 1000;
}

function normalizeLoopCount(value: number | undefined): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return 1;
    }
    return Math.max(1, Math.round(value));
}

function usePrefersReducedMotion(): boolean {
    const [reduced, setReduced] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return;
        }
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const sync = () => setReduced(mq.matches);
        sync();
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    }, []);

    return reduced;
}

type TransitionState = {
    lifecycle: TransitionLifecycle;
    /** Index that remains committed until animation finishes. */
    currentIndex: number;
    /** Index of the Screen entering during PREPARING/ANIMATING. */
    incomingIndex: number | null;
};

/**
 * Sequential Screen Design playback shared by Playlist Preview and `/player`.
 *
 * Transition lifecycle (double-buffer — outgoing is NEVER unmounted early):
 *   IDLE → PREPARING (mount A+B at start poses)
 *        → ANIMATING (Web Animations API drives both layers simultaneously)
 *        → COMMIT (B becomes current, unmount A) → IDLE
 *
 * Item `duration_seconds` starts when a Screen is committed (IDLE).
 * Transition duration is visual only.
 */
export function PlaylistPreviewPlayer({
    items,
    mediaMap,
    autoPlay = true,
    loop = true,
    showControls = true,
    className,
    stageClassName,
    emptyMessage = 'Nothing to play — add an active item to preview this playlist.',
    runtime,
    widgetData,
    isOnline = true,
    surface = 'preview',
}: PlaylistPreviewPlayerProps) {
    const activeItems = useMemo(
        () => items.filter((item) => item.is_active),
        [items],
    );
    const prefersReducedMotion = usePrefersReducedMotion();

    const [playPass, setPlayPass] = useState(1);
    const [playing, setPlaying] = useState(autoPlay);
    const [fit, setFit] = useState({ width: 640, height: 360 });
    const [transition, setTransition] = useState<TransitionState>({
        lifecycle: 'idle',
        currentIndex: 0,
        incomingIndex: null,
    });

    const stageRef = useRef<HTMLDivElement>(null);
    const outgoingRef = useRef<HTMLDivElement>(null);
    const incomingRef = useRef<HTMLDivElement>(null);
    const advanceTimerRef = useRef<number | null>(null);
    const pendingIndexRef = useRef<number | null>(null);
    const lifecycleRef = useRef<TransitionLifecycle>('idle');
    const animTokenRef = useRef(0);

    const total = activeItems.length;
    const safeCurrent =
        total > 0 ? Math.min(transition.currentIndex, total - 1) : 0;
    const current = total > 0 ? activeItems[safeCurrent] : null;
    const incoming =
        transition.incomingIndex !== null && total > 0
            ? activeItems[Math.min(transition.incomingIndex, total - 1)]
            : null;
    const nextWarm = total > 1 ? activeItems[(safeCurrent + 1) % total] : null;
    const currentLoops = normalizeLoopCount(current?.loop_count);
    const safePlayPass =
        current === null ? 1 : Math.min(Math.max(1, playPass), currentLoops);

    useEffect(() => {
        lifecycleRef.current = transition.lifecycle;
    }, [transition.lifecycle]);

    useEffect(() => {
        setTransition((prev) => {
            if (total === 0) {
                return {
                    lifecycle: 'idle',
                    currentIndex: 0,
                    incomingIndex: null,
                };
            }
            const clamped = Math.min(prev.currentIndex, total - 1);
            if (clamped === prev.currentIndex) {
                return prev;
            }
            return {
                lifecycle: 'idle',
                currentIndex: clamped,
                incomingIndex: null,
            };
        });
    }, [total]);

    useEffect(() => {
        setPlayPass(1);
    }, [current?.key, safeCurrent]);

    useEffect(() => {
        const stage = stageRef.current;
        if (!stage) {
            return;
        }

        const measure = () => {
            const rect = stage.getBoundingClientRect();
            const width = Math.max(80, rect.width);
            const height = Math.max(80, rect.height);
            setFit((prev) =>
                prev.width === width && prev.height === height
                    ? prev
                    : { width, height },
            );
        };

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(stage);
        return () => observer.disconnect();
    }, []);

    const commitIncoming = useCallback(() => {
        setTransition((prev) => {
            if (prev.incomingIndex === null) {
                return { ...prev, lifecycle: 'idle', incomingIndex: null };
            }
            return {
                lifecycle: 'idle',
                currentIndex: prev.incomingIndex,
                incomingIndex: null,
            };
        });
        pendingIndexRef.current = null;
    }, []);

    const beginTransition = useCallback(
        (nextIndex: number) => {
            if (total === 0) {
                return;
            }
            const target = Math.max(0, Math.min(nextIndex, total - 1));

            if (lifecycleRef.current !== 'idle') {
                pendingIndexRef.current = target;
                return;
            }

            if (target === safeCurrent) {
                return;
            }

            if (!activeItems[target]) {
                return;
            }

            setTransition({
                lifecycle: 'preparing',
                currentIndex: safeCurrent,
                incomingIndex: target,
            });
        },
        [activeItems, safeCurrent, total],
    );

    const prefersReducedMotionRef = useRef(prefersReducedMotion);
    const surfaceRef = useRef(surface);
    const activeItemsRef = useRef(activeItems);
    prefersReducedMotionRef.current = prefersReducedMotion;
    surfaceRef.current = surface;
    activeItemsRef.current = activeItems;

    /**
     * Stable run key for the whole PREPARING→ANIMATING window.
     * Must NOT change when we flip preparing→animating, or the effect cleanup
     * would cancel WAAPI mid-flight (opacity stuck at start poses forever).
     */
    const transitionRunKey =
        transition.incomingIndex !== null &&
        (transition.lifecycle === 'preparing' ||
            transition.lifecycle === 'animating')
            ? `${transition.currentIndex}->${transition.incomingIndex}`
            : null;

    // PREPARING → run WAAPI → COMMIT. Outgoing stays mounted the whole time.
    useEffect(() => {
        if (transitionRunKey === null) {
            return;
        }

        const parts = transitionRunKey.split('->');
        const incomingIndex = Number(parts[1]);
        const items = activeItemsRef.current;
        const incomingItem = items[incomingIndex];
        if (!incomingItem) {
            commitIncoming();
            return;
        }

        const token = ++animTokenRef.current;
        let cancelled = false;

        const run = async () => {
            // Wait one frame so both layers are in the DOM at start poses.
            await new Promise<void>((resolve) => {
                window.requestAnimationFrame(() => resolve());
            });
            if (cancelled || token !== animTokenRef.current) {
                return;
            }

            const resolved = resolvePlaylistTransition(
                incomingItem.transition ?? 'none',
                {
                    preferReducedMotion: prefersReducedMotionRef.current,
                    surface: surfaceRef.current,
                },
            );
            const speed = incomingItem.transition_speed ?? 'normal';

            setTransition((prev) =>
                prev.lifecycle === 'preparing'
                    ? { ...prev, lifecycle: 'animating' }
                    : prev,
            );

            // Another frame after marking ANIMATING so refs stay mounted.
            await new Promise<void>((resolve) => {
                window.requestAnimationFrame(() => resolve());
            });
            if (cancelled || token !== animTokenRef.current) {
                return;
            }

            try {
                await runPlaylistLayerAnimations({
                    outgoing: outgoingRef.current,
                    incoming: incomingRef.current,
                    transition: resolved,
                    speed,
                });
            } catch {
                // Cancelled WAAPI rejects finished — treat as aborted.
                return;
            }

            if (cancelled || token !== animTokenRef.current) {
                return;
            }

            commitIncoming();

            const queued = pendingIndexRef.current;
            pendingIndexRef.current = null;
            if (queued !== null) {
                window.requestAnimationFrame(() => {
                    setTransition((prev) => {
                        if (prev.lifecycle !== 'idle') {
                            pendingIndexRef.current = queued;
                            return prev;
                        }
                        if (queued === prev.currentIndex) {
                            return prev;
                        }
                        return {
                            lifecycle: 'preparing',
                            currentIndex: prev.currentIndex,
                            incomingIndex: queued,
                        };
                    });
                });
            }
        };

        void run();

        return () => {
            cancelled = true;
            animTokenRef.current += 1;
            // Only abort in-flight animations. Cancelling a finished fill
            // snaps the layer back to its start pose and flashes the stage.
            const abortRunning = (el: HTMLElement | null) => {
                el?.getAnimations().forEach((animation) => {
                    if (animation.playState !== 'finished') {
                        animation.cancel();
                    }
                });
            };
            abortRunning(outgoingRef.current);
            abortRunning(incomingRef.current);
        };
    }, [transitionRunKey, commitIncoming]);

    // After commit, idle styles match the finished pose. Clear fills before
    // paint so the next transition does not composite with a stale animation.
    useLayoutEffect(() => {
        if (transition.lifecycle !== 'idle') {
            return;
        }
        outgoingRef.current?.getAnimations().forEach((animation) => {
            animation.cancel();
        });
    }, [transition.lifecycle, current?.key]);

    const goTo = useCallback(
        (next: number) => {
            setPlayPass(1);
            beginTransition(next);
        },
        [beginTransition],
    );

    const goNext = useCallback(() => {
        if (total === 0) {
            return;
        }
        const next = safeCurrent + 1;
        if (next >= total) {
            if (loop) {
                goTo(0);
            }
            return;
        }
        goTo(next);
    }, [goTo, loop, safeCurrent, total]);

    const goPrevious = useCallback(() => {
        if (total === 0) {
            return;
        }
        const previous = safeCurrent - 1;
        if (previous < 0) {
            if (loop) {
                goTo(total - 1);
            }
            return;
        }
        goTo(previous);
    }, [goTo, loop, safeCurrent, total]);

    const restart = useCallback(() => {
        goTo(0);
        setPlaying(true);
    }, [goTo]);

    // Advance timer runs only while IDLE on a committed Screen.
    useEffect(() => {
        if (advanceTimerRef.current !== null) {
            window.clearTimeout(advanceTimerRef.current);
            advanceTimerRef.current = null;
        }

        if (
            !playing ||
            current === null ||
            total === 0 ||
            transition.lifecycle !== 'idle'
        ) {
            return;
        }

        if (total === 1 && !loop && safePlayPass >= currentLoops) {
            return;
        }

        advanceTimerRef.current = window.setTimeout(() => {
            if (safePlayPass < currentLoops) {
                setPlayPass((prev) => prev + 1);
                return;
            }
            goNext();
        }, itemDurationMs(current.duration_seconds));

        return () => {
            if (advanceTimerRef.current !== null) {
                window.clearTimeout(advanceTimerRef.current);
                advanceTimerRef.current = null;
            }
        };
    }, [
        current,
        currentLoops,
        goNext,
        loop,
        playing,
        safePlayPass,
        total,
        transition.lifecycle,
    ]);

    const activeTransition = resolvePlaylistTransition(
        (incoming ?? current)?.transition ?? 'none',
        { preferReducedMotion: prefersReducedMotion, surface },
    );
    const isTransitioning =
        transition.lifecycle === 'preparing' ||
        transition.lifecycle === 'animating';

    return (
        <div
            className={cn('flex min-h-0 w-full flex-col gap-3', className)}
            data-test="playlist-player"
            data-playlist-count={total}
            data-transition={activeTransition}
            data-transition-lifecycle={transition.lifecycle}
            data-transition-ms={transitionSpeedMs(
                (incoming ?? current)?.transition_speed ?? 'normal',
            )}
        >
            <div
                ref={stageRef}
                data-test="playlist-player-stage"
                data-current-index={total === 0 ? '' : safeCurrent + 1}
                data-current-name={current?.name ?? ''}
                className={cn(
                    'relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg bg-[#060810]',
                    stageClassName,
                )}
                style={{ position: 'relative', overflow: 'hidden' }}
            >
                {current === null ? (
                    <p className="px-6 text-center text-sm text-[#94a3b8]">
                        {emptyMessage}
                    </p>
                ) : (
                    <>
                        {!isTransitioning &&
                        nextWarm &&
                        nextWarm.key !== current.key ? (
                            <div
                                key="playlist-preload"
                                className="pointer-events-none absolute inset-0 flex items-center justify-center"
                                style={{
                                    opacity: 0,
                                    visibility: 'hidden',
                                    zIndex: 0,
                                }}
                                aria-hidden
                                data-test="playlist-player-preload"
                            >
                                <LayoutRenderer
                                    schema={nextWarm.schema}
                                    mode="preview"
                                    runtime={runtime}
                                    fitWidth={fit.width}
                                    fitHeight={fit.height}
                                    mediaMap={mediaMap}
                                    widgetData={widgetData}
                                    isOnline={isOnline}
                                    mediaActive={false}
                                />
                            </div>
                        ) : null}

                        <div
                            key={current.key}
                            ref={outgoingRef}
                            style={
                                isTransitioning && incoming
                                    ? playlistLayerStartStyle(
                                          activeTransition,
                                          'outgoing',
                                      )
                                    : playlistIdleLayerStyle()
                            }
                            data-test="playlist-player-outgoing"
                            data-transition-layer="outgoing"
                        >
                            <LayoutRenderer
                                schema={current.schema}
                                mode="preview"
                                runtime={runtime}
                                fitWidth={fit.width}
                                fitHeight={fit.height}
                                mediaMap={mediaMap}
                                widgetData={widgetData}
                                isOnline={isOnline}
                                mediaActive={!isTransitioning}
                            />
                        </div>

                        {isTransitioning && incoming ? (
                            <div
                                key={incoming.key}
                                ref={incomingRef}
                                style={playlistLayerStartStyle(
                                    activeTransition,
                                    'incoming',
                                )}
                                data-test="playlist-player-incoming"
                                data-transition-layer="incoming"
                            >
                                <LayoutRenderer
                                    schema={incoming.schema}
                                    mode="preview"
                                    runtime={runtime}
                                    fitWidth={fit.width}
                                    fitHeight={fit.height}
                                    mediaMap={mediaMap}
                                    widgetData={widgetData}
                                    isOnline={isOnline}
                                    mediaActive={
                                        transition.lifecycle === 'animating'
                                    }
                                />
                            </div>
                        ) : null}
                    </>
                )}
            </div>

            {showControls ? (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-h-0 min-w-0">
                        <p
                            className="truncate text-sm font-medium"
                            data-test="playlist-player-name"
                        >
                            {(incoming ?? current)?.name ?? 'No active items'}
                        </p>
                        <p className="text-muted-foreground font-mono text-xs">
                            <span data-test="playlist-player-position">
                                {total === 0
                                    ? '0/0'
                                    : `${
                                          (incoming
                                              ? Math.min(
                                                    transition.incomingIndex ??
                                                        0,
                                                    total - 1,
                                                )
                                              : safeCurrent) + 1
                                      }/${total}`}
                            </span>
                            {current
                                ? ` · ${formatDuration(current.duration_seconds)}${
                                      currentLoops > 1
                                          ? ` ×${currentLoops} (${safePlayPass}/${currentLoops})`
                                          : ''
                                  }`
                                : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Previous item"
                            data-test="playlist-player-prev"
                            disabled={total === 0}
                            onClick={goPrevious}
                        >
                            <SkipBack className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon"
                            aria-label={playing ? 'Pause' : 'Play'}
                            data-test="playlist-player-toggle"
                            data-playing={playing ? 'true' : 'false'}
                            disabled={total === 0}
                            onClick={() => setPlaying((prev) => !prev)}
                        >
                            {playing ? (
                                <Pause className="size-4" />
                            ) : (
                                <Play className="size-4" />
                            )}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Next item"
                            data-test="playlist-player-next"
                            disabled={total === 0}
                            onClick={goNext}
                        >
                            <SkipForward className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Restart playlist"
                            data-test="playlist-player-restart"
                            disabled={total === 0}
                            onClick={restart}
                        >
                            <RotateCcw className="size-4" />
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
