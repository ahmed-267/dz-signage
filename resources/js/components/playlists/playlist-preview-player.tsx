import { Pause, Play, RotateCcw, SkipBack, SkipForward } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    playlistTransitionStyle,
    transitionSpeedMs,
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

/**
 * Sequential Screen Design playback shared by the playlist editor, the
 * playlist preview page, and the Player. Inactive items are skipped. Each
 * item plays for `duration_seconds`, repeated `loop_count` times, then the
 * player advances. Transitions are visual only and do not affect runtime.
 * The canvas stays dark and theme independent — app chrome must never
 * recolor rendered signage.
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
}: PlaylistPreviewPlayerProps) {
    const activeItems = useMemo(
        () => items.filter((item) => item.is_active),
        [items],
    );

    const [index, setIndex] = useState(0);
    /** 1-based play count within the current item (1..loop_count). */
    const [playPass, setPlayPass] = useState(1);
    const [playing, setPlaying] = useState(autoPlay);
    const [outgoing, setOutgoing] = useState<PlaylistPlayerItem | null>(null);
    const [entered, setEntered] = useState(true);
    const [fit, setFit] = useState({ width: 640, height: 360 });

    const stageRef = useRef<HTMLDivElement>(null);
    const advanceTimerRef = useRef<number | null>(null);
    const transitionTimerRef = useRef<number | null>(null);
    const currentItemRef = useRef<PlaylistPlayerItem | null>(null);

    const total = activeItems.length;
    const safeIndex = total > 0 ? Math.min(index, total - 1) : 0;
    const current = total > 0 ? activeItems[safeIndex] : null;
    const currentLoops = normalizeLoopCount(current?.loop_count);
    const safePlayPass =
        current === null ? 1 : Math.min(Math.max(1, playPass), currentLoops);

    useEffect(() => {
        currentItemRef.current = current;
    }, [current]);

    // A shorter playlist (item removed / deactivated) must not leave a
    // dangling index.
    useEffect(() => {
        setIndex((prev) => (total === 0 ? 0 : Math.min(prev, total - 1)));
    }, [total]);

    // Reset the per-item play pass when the current item changes.
    useEffect(() => {
        setPlayPass(1);
    }, [current?.key, safeIndex]);

    useEffect(() => {
        const stage = stageRef.current;
        if (!stage) {
            return;
        }

        const measure = () => {
            const rect = stage.getBoundingClientRect();
            setFit({
                width: Math.max(80, rect.width),
                height: Math.max(80, rect.height),
            });
        };

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(stage);
        return () => observer.disconnect();
    }, []);

    const goTo = useCallback((next: number) => {
        setOutgoing(currentItemRef.current);
        setEntered(false);
        setPlayPass(1);
        setIndex(next);
    }, []);

    const goNext = useCallback(() => {
        if (total === 0) {
            return;
        }
        const next = safeIndex + 1;
        if (next >= total) {
            if (loop) {
                goTo(0);
            }
            return;
        }
        goTo(next);
    }, [goTo, loop, safeIndex, total]);

    const goPrevious = useCallback(() => {
        if (total === 0) {
            return;
        }
        const previous = safeIndex - 1;
        if (previous < 0) {
            if (loop) {
                goTo(total - 1);
            }
            return;
        }
        goTo(previous);
    }, [goTo, loop, safeIndex, total]);

    const restart = useCallback(() => {
        goTo(0);
        setPlaying(true);
    }, [goTo]);

    // Advance timer — one timer at a time. After each duration_seconds pass,
    // either repeat the same item (loop_count) or advance to the next item.
    useEffect(() => {
        if (advanceTimerRef.current !== null) {
            window.clearTimeout(advanceTimerRef.current);
            advanceTimerRef.current = null;
        }

        if (!playing || current === null || total === 0) {
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
    }, [current, currentLoops, goNext, loop, playing, safePlayPass, total]);

    // Run the incoming animation, then drop the outgoing layer.
    useEffect(() => {
        if (transitionTimerRef.current !== null) {
            window.clearTimeout(transitionTimerRef.current);
            transitionTimerRef.current = null;
        }

        if (current === null) {
            return;
        }

        const frame = window.requestAnimationFrame(() => setEntered(true));
        transitionTimerRef.current = window.setTimeout(
            () => setOutgoing(null),
            transitionSpeedMs(current.transition_speed) + 60,
        );

        return () => {
            window.cancelAnimationFrame(frame);
            if (transitionTimerRef.current !== null) {
                window.clearTimeout(transitionTimerRef.current);
                transitionTimerRef.current = null;
            }
        };
    }, [current, safeIndex]);

    const transition = current?.transition ?? 'none';
    const speed = current?.transition_speed ?? 'normal';

    return (
        <div
            className={cn('flex min-h-0 w-full flex-col gap-3', className)}
            data-test="playlist-player"
            data-playlist-count={total}
        >
            <div
                ref={stageRef}
                data-test="playlist-player-stage"
                data-current-index={total === 0 ? '' : safeIndex + 1}
                data-current-name={current?.name ?? ''}
                className={cn(
                    'relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg bg-[#060810]',
                    stageClassName,
                )}
            >
                {current === null ? (
                    <p className="px-6 text-center text-sm text-[#94a3b8]">
                        {emptyMessage}
                    </p>
                ) : (
                    <>
                        {outgoing && outgoing.key !== current.key ? (
                            <div
                                className="pointer-events-none absolute inset-0 flex items-center justify-center"
                                style={playlistTransitionStyle(
                                    transition,
                                    speed,
                                    entered ? 'leave' : 'settled',
                                )}
                                aria-hidden
                            >
                                <LayoutRenderer
                                    schema={outgoing.schema}
                                    mode="preview"
                                    runtime={runtime}
                                    fitWidth={fit.width}
                                    fitHeight={fit.height}
                                    mediaMap={mediaMap}
                                    widgetData={widgetData}
                                    isOnline={isOnline}
                                />
                            </div>
                        ) : null}
                        <div
                            className="pointer-events-none absolute inset-0 flex items-center justify-center"
                            style={playlistTransitionStyle(
                                transition,
                                speed,
                                entered ? 'settled' : 'enter',
                            )}
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
                            />
                        </div>
                    </>
                )}
            </div>

            {showControls ? (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p
                            className="truncate text-sm font-medium"
                            data-test="playlist-player-name"
                        >
                            {current?.name ?? 'No active items'}
                        </p>
                        <p className="text-muted-foreground font-mono text-xs">
                            <span data-test="playlist-player-position">
                                {total === 0
                                    ? '0/0'
                                    : `${safeIndex + 1}/${total}`}
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
