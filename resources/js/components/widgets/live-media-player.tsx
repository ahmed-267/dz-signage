import { Pause, Play, RotateCcw, Volume2, VolumeX } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import { csrfHeaders } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import {
    liveMediaKindLabel,
    liveMediaStatusLabel,
    resolveLiveMedia,
    type LiveMediaKind,
    type ResolvedLiveMedia,
} from '@/lib/widgets/embed-resolve';
import {
    extractYoutubeVideoId,
    loadYoutubeIframeApi,
    type YoutubePlayer,
} from '@/lib/widgets/youtube-iframe-api';
import type { EmbedWidgetConfig, WidgetRuntimeMode } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';

export type LiveMediaPlayerHandle = {
    play: () => void;
    pause: () => void;
    mute: () => void;
    unmute: () => void;
    setVolume: (volume: number) => void;
    restart: () => void;
};

type EmbedWidgetProps = {
    config: EmbedWidgetConfig;
    elementProps?: LayoutElementProps;
    mode?: WidgetRuntimeMode;
    isOnline?: boolean;
    /** When true, iframe/video receive pointer events (Editor interact mode). */
    interact?: boolean;
    /** Show RMSignage overlay transport controls. */
    showChrome?: boolean;
};

type ExtendedEmbedConfig = EmbedWidgetConfig & {
    kind?: LiveMediaKind;
    source_url?: string;
    autoplay?: boolean;
    muted?: boolean;
    loop?: boolean;
    controls?: boolean;
    volume?: number;
};

function clampVolume(value: unknown, fallback = 70): number {
    const n = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(n)) {
        return fallback;
    }
    return Math.min(100, Math.max(0, Math.round(n)));
}

function Placeholder({
    title,
    detail,
    elementProps,
}: {
    title: string;
    detail?: string | null;
    elementProps?: LayoutElementProps;
}) {
    return (
        <WidgetShell
            props={elementProps}
            className="items-center justify-center gap-1 bg-black/40 text-center text-white"
        >
            <div style={{ fontSize: '0.5em', fontWeight: 600 }}>{title}</div>
            {detail ? (
                <div
                    className="line-clamp-4 opacity-80"
                    style={{ fontSize: '0.32em', fontWeight: 400 }}
                >
                    {detail}
                </div>
            ) : null}
        </WidgetShell>
    );
}

type TransportState = {
    playing: boolean;
    muted: boolean;
    volume: number;
    live: boolean;
    error: string | null;
    audioBlocked: boolean;
};

type MediaController = {
    play: () => void;
    pause: () => void;
    mute: () => void;
    unmute: () => void;
    setVolume: (volume: number) => void;
    restart: () => void;
};

function TransportChrome({
    state,
    onPlay,
    onPause,
    onMute,
    onUnmute,
    onVolume,
    onRestart,
    label,
}: {
    state: TransportState;
    onPlay: () => void;
    onPause: () => void;
    onMute: () => void;
    onUnmute: () => void;
    onVolume: (volume: number) => void;
    onRestart: () => void;
    label: string;
}) {
    return (
        <div
            className="pointer-events-auto absolute inset-x-0 bottom-0 z-20 flex flex-col gap-1 bg-gradient-to-t from-black/80 via-black/50 to-transparent px-2 pt-6 pb-2"
            data-test="live-media-controls"
            onPointerDown={(event) => event.stopPropagation()}
            onClick={(event) => event.stopPropagation()}
        >
            <div className="flex items-center gap-1.5">
                <span className="rounded bg-black/60 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-white uppercase">
                    {label}
                </span>
                {state.live ? (
                    <span className="rounded bg-red-600 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-white">
                        LIVE
                    </span>
                ) : null}
                <div className="flex-1" />
                <button
                    type="button"
                    className="rounded bg-white/15 p-1.5 text-white hover:bg-white/25"
                    aria-label={state.playing ? 'Pause' : 'Play'}
                    data-test="live-media-play-pause"
                    onClick={() => (state.playing ? onPause() : onPlay())}
                >
                    {state.playing ? (
                        <Pause className="size-3.5" />
                    ) : (
                        <Play className="size-3.5" />
                    )}
                </button>
                <button
                    type="button"
                    className="rounded bg-white/15 p-1.5 text-white hover:bg-white/25"
                    aria-label={state.muted ? 'Unmute' : 'Mute'}
                    data-test="live-media-mute"
                    onClick={() => (state.muted ? onUnmute() : onMute())}
                >
                    {state.muted || state.volume === 0 ? (
                        <VolumeX className="size-3.5" />
                    ) : (
                        <Volume2 className="size-3.5" />
                    )}
                </button>
                <input
                    type="range"
                    min={0}
                    max={100}
                    value={state.muted ? 0 : state.volume}
                    aria-label="Volume"
                    data-test="live-media-volume"
                    className="h-1 w-16 accent-cyan-400"
                    onChange={(event) => onVolume(Number(event.target.value))}
                />
                <button
                    type="button"
                    className="rounded bg-white/15 p-1.5 text-white hover:bg-white/25"
                    aria-label="Restart"
                    data-test="live-media-restart"
                    onClick={onRestart}
                >
                    <RotateCcw className="size-3.5" />
                </button>
            </div>
            {state.audioBlocked ? (
                <button
                    type="button"
                    className="rounded bg-cyan-500/90 px-2 py-1 text-left text-[10px] font-semibold text-black"
                    data-test="live-media-enable-sound"
                    onClick={onUnmute}
                >
                    Click to enable sound
                </button>
            ) : null}
            {state.error ? (
                <p className="text-[10px] text-amber-200">{state.error}</p>
            ) : null}
        </div>
    );
}

function HlsSurface({
    src,
    autoplay,
    muted,
    volume,
    loop,
    isLive,
    onReady,
}: {
    src: string;
    autoplay: boolean;
    muted: boolean;
    volume: number;
    loop: boolean;
    isLive: boolean;
    onReady: (controller: MediaController, video: HTMLVideoElement) => void;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState<string | null>(null);
    const retries = useRef(0);

    useEffect(() => {
        const video = ref.current;
        if (!video) return;

        let destroyed = false;
        let hls: {
            destroy: () => void;
            startLoad: () => void;
            recoverMediaError: () => void;
            on: (event: string, cb: (...args: unknown[]) => void) => void;
        } | null = null;

        const applyVolume = () => {
            video.volume = Math.min(1, Math.max(0, volume / 100));
            video.muted = muted;
        };

        const setup = async () => {
            try {
                applyVolume();
                if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = src;
                } else {
                    const { default: Hls } = await import('hls.js');
                    if (destroyed) return;
                    if (!Hls.isSupported()) {
                        setError('HLS is not supported in this browser.');
                        return;
                    }
                    const instance = new Hls({
                        enableWorker: true,
                        lowLatencyMode: true,
                    });
                    instance.loadSource(src);
                    instance.attachMedia(video);
                    hls = instance as unknown as NonNullable<typeof hls>;
                    instance.on(Hls.Events.ERROR, (_event, data) => {
                        if (!data?.fatal) {
                            return;
                        }
                        if (retries.current < 2) {
                            retries.current += 1;
                            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                                instance.startLoad();
                                return;
                            }
                            if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                                instance.recoverMediaError();
                                return;
                            }
                        }
                        setError('Live stream temporarily unavailable');
                    });
                }

                onReady(
                    {
                        play: () => {
                            void video.play().catch(() => undefined);
                        },
                        pause: () => video.pause(),
                        mute: () => {
                            video.muted = true;
                        },
                        unmute: () => {
                            video.muted = false;
                            video.volume = Math.min(
                                1,
                                Math.max(0, volume / 100),
                            );
                        },
                        setVolume: (next) => {
                            video.volume = Math.min(1, Math.max(0, next / 100));
                            video.muted = next === 0;
                        },
                        restart: () => {
                            retries.current = 0;
                            setError(null);
                            if (hls) {
                                hls.startLoad();
                            }
                            video.currentTime = isLive
                                ? Math.max(0, video.duration - 1)
                                : 0;
                            void video.play().catch(() => undefined);
                        },
                    },
                    video,
                );

                if (autoplay) {
                    void video.play().catch(() => undefined);
                }
            } catch {
                if (!destroyed) {
                    setError('Live stream temporarily unavailable');
                }
            }
        };

        void setup();

        return () => {
            destroyed = true;
            hls?.destroy();
        };
    }, [src, autoplay, muted, volume, isLive, onReady]);

    if (error) {
        return (
            <div className="flex h-full w-full items-center justify-center bg-black/60 text-center text-white">
                <span className="px-3 text-[0.45em] font-medium">{error}</span>
            </div>
        );
    }

    return (
        <video
            ref={ref}
            className="h-full w-full bg-black object-contain"
            autoPlay={autoplay}
            muted={muted}
            loop={loop}
            playsInline
            controls={false}
        />
    );
}

function DashSurface({
    src,
    autoplay,
    muted,
    volume,
    onReady,
}: {
    src: string;
    autoplay: boolean;
    muted: boolean;
    volume: number;
    onReady: (controller: MediaController, video: HTMLVideoElement) => void;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const video = ref.current;
        if (!video) return;

        let destroyed = false;
        let player: { destroy: () => void; reset?: () => void } | null = null;

        const applyVolume = () => {
            video.volume = Math.min(1, Math.max(0, volume / 100));
            video.muted = muted;
        };

        const setup = async () => {
            try {
                applyVolume();
                const dashjs = await import('dashjs');
                if (destroyed) return;
                const MediaPlayer = dashjs.MediaPlayer;
                const instance = MediaPlayer().create();
                instance.initialize(video, src, autoplay);
                instance.updateSettings({
                    streaming: {
                        retryAttempts: {
                            MPD: 2,
                            MediaSegment: 2,
                        },
                    },
                });
                player = instance as unknown as NonNullable<typeof player>;
                instance.on(dashjs.MediaPlayer.events.ERROR, () => {
                    if (!destroyed) {
                        setError('Live stream temporarily unavailable');
                    }
                });

                onReady(
                    {
                        play: () => {
                            void video.play().catch(() => undefined);
                        },
                        pause: () => video.pause(),
                        mute: () => {
                            video.muted = true;
                        },
                        unmute: () => {
                            video.muted = false;
                            video.volume = Math.min(
                                1,
                                Math.max(0, volume / 100),
                            );
                        },
                        setVolume: (next) => {
                            video.volume = Math.min(1, Math.max(0, next / 100));
                            video.muted = next === 0;
                        },
                        restart: () => {
                            setError(null);
                            player?.reset?.();
                            instance.initialize(video, src, true);
                        },
                    },
                    video,
                );
            } catch {
                if (!destroyed) {
                    setError('Live stream temporarily unavailable');
                }
            }
        };

        void setup();

        return () => {
            destroyed = true;
            try {
                player?.destroy();
            } catch {
                // ignore
            }
        };
    }, [src, autoplay, muted, volume, onReady]);

    if (error) {
        return (
            <div className="flex h-full w-full items-center justify-center bg-black/60 text-center text-white">
                <span className="px-3 text-[0.45em] font-medium">{error}</span>
            </div>
        );
    }

    return (
        <video
            ref={ref}
            className="h-full w-full bg-black object-contain"
            autoPlay={autoplay}
            muted={muted}
            playsInline
            controls={false}
        />
    );
}

function DirectVideoSurface({
    src,
    autoplay,
    muted,
    volume,
    loop,
    onReady,
}: {
    src: string;
    autoplay: boolean;
    muted: boolean;
    volume: number;
    loop: boolean;
    onReady: (controller: MediaController, video: HTMLVideoElement) => void;
}) {
    const ref = useRef<HTMLVideoElement>(null);

    useEffect(() => {
        const video = ref.current;
        if (!video) return;
        video.volume = Math.min(1, Math.max(0, volume / 100));
        video.muted = muted;
        onReady(
            {
                play: () => {
                    void video.play().catch(() => undefined);
                },
                pause: () => video.pause(),
                mute: () => {
                    video.muted = true;
                },
                unmute: () => {
                    video.muted = false;
                },
                setVolume: (next) => {
                    video.volume = Math.min(1, Math.max(0, next / 100));
                    video.muted = next === 0;
                },
                restart: () => {
                    video.currentTime = 0;
                    void video.play().catch(() => undefined);
                },
            },
            video,
        );
        if (autoplay) {
            void video.play().catch(() => undefined);
        }
    }, [src, autoplay, muted, volume, onReady]);

    return (
        <video
            ref={ref}
            className="h-full w-full bg-black object-contain"
            src={src}
            autoPlay={autoplay}
            muted={muted}
            loop={loop}
            playsInline
            controls={false}
        />
    );
}

function YoutubeSurface({
    playUrl,
    autoplay,
    muted,
    volume,
    isLive,
    onBlocked,
    onReady,
}: {
    playUrl: string;
    autoplay: boolean;
    muted: boolean;
    volume: number;
    isLive: boolean;
    onBlocked?: (message: string) => void;
    onReady: (controller: MediaController) => void;
}) {
    const hostRef = useRef<HTMLDivElement>(null);
    const playerRef = useRef<YoutubePlayer | null>(null);

    useEffect(() => {
        const host = hostRef.current;
        const videoId = extractYoutubeVideoId(playUrl);
        if (!host || !videoId) {
            return;
        }

        let cancelled = false;
        let player: YoutubePlayer | null = null;

        void loadYoutubeIframeApi()
            .then((YT) => {
                if (cancelled || !hostRef.current) {
                    return;
                }
                hostRef.current.innerHTML = '';
                const mount = document.createElement('div');
                hostRef.current.appendChild(mount);
                player = new YT.Player(mount, {
                    videoId,
                    width: '100%',
                    height: '100%',
                    playerVars: {
                        autoplay: autoplay ? 1 : 0,
                        mute: muted ? 1 : 0,
                        controls: 0,
                        rel: 0,
                        modestbranding: 1,
                        playsinline: 1,
                        origin: window.location.origin,
                        ...(isLive ? {} : {}),
                    },
                    events: {
                        onReady: (event) => {
                            const api = event.target;
                            playerRef.current = api;
                            api.setVolume(volume);
                            if (muted) {
                                api.mute();
                            } else {
                                api.unMute();
                                api.setVolume(volume);
                            }
                            if (autoplay) {
                                api.playVideo();
                            }
                            onReady({
                                play: () => api.playVideo(),
                                pause: () => api.pauseVideo(),
                                mute: () => api.mute(),
                                unmute: () => {
                                    api.unMute();
                                    api.setVolume(volume);
                                },
                                setVolume: (next) => {
                                    api.setVolume(next);
                                    if (next === 0) {
                                        api.mute();
                                    } else {
                                        api.unMute();
                                    }
                                },
                                restart: () => {
                                    if (isLive) {
                                        api.seekTo(
                                            Number.MAX_SAFE_INTEGER,
                                            true,
                                        );
                                    } else {
                                        api.seekTo(0, true);
                                    }
                                    api.playVideo();
                                },
                            });
                        },
                        onError: (event) => {
                            const code = Number(event.data);
                            if (code === 101 || code === 150) {
                                onBlocked?.(
                                    'This YouTube video does not allow embedding.',
                                );
                            } else if (code === 100) {
                                onBlocked?.(
                                    'This YouTube video is unavailable.',
                                );
                            } else if (code === 2) {
                                onBlocked?.('Invalid YouTube video id.');
                            }
                        },
                    },
                });
                playerRef.current = player;
            })
            .catch(() => {
                onBlocked?.('YouTube player failed to load.');
            });

        return () => {
            cancelled = true;
            try {
                player?.destroy();
            } catch {
                // ignore
            }
            playerRef.current = null;
        };
    }, [playUrl, autoplay, muted, volume, isLive, onBlocked, onReady]);

    return (
        <div
            ref={hostRef}
            className="h-full w-full bg-black [&_iframe]:h-full [&_iframe]:w-full"
            data-test="youtube-player-host"
        />
    );
}

function VimeoSurface({
    playUrl,
    autoplay,
    muted,
    volume,
    onReady,
}: {
    playUrl: string;
    autoplay: boolean;
    muted: boolean;
    volume: number;
    onReady: (controller: MediaController) => void;
}) {
    const frameRef = useRef<HTMLIFrameElement>(null);

    useEffect(() => {
        const frame = frameRef.current;
        if (!frame) return;

        const post = (method: string, value?: unknown) => {
            frame.contentWindow?.postMessage(
                JSON.stringify(
                    value === undefined ? { method } : { method, value },
                ),
                'https://player.vimeo.com',
            );
        };

        const onLoad = () => {
            post('addEventListener', 'play');
            post('addEventListener', 'pause');
            post('setVolume', muted ? 0 : volume / 100);
            if (muted) {
                post('setVolume', 0);
            }
            if (autoplay) {
                post('play');
            }
            onReady({
                play: () => post('play'),
                pause: () => post('pause'),
                mute: () => post('setVolume', 0),
                unmute: () => post('setVolume', volume / 100),
                setVolume: (next) => post('setVolume', next / 100),
                restart: () => {
                    post('setCurrentTime', 0);
                    post('play');
                },
            });
        };

        frame.addEventListener('load', onLoad);
        return () => frame.removeEventListener('load', onLoad);
    }, [playUrl, autoplay, muted, volume, onReady]);

    return (
        <iframe
            ref={frameRef}
            title="Vimeo"
            src={playUrl}
            className="h-full w-full border-0 bg-black"
            allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
            referrerPolicy="strict-origin-when-cross-origin"
            allowFullScreen
        />
    );
}

type RemoteEmbed = {
    kind?: string;
    play_url?: string | null;
    url?: string | null;
    message?: string | null;
};

function useRemoteEmbed(
    sourceUrl: string,
    enabled: boolean,
): RemoteEmbed | null {
    const [remote, setRemote] = useState<RemoteEmbed | null>(null);

    useEffect(() => {
        if (!enabled || sourceUrl.trim() === '') {
            setRemote(null);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void fetch('/app/widgets/data', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ embed: { url: sourceUrl } }),
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const body = (await response
                            .json()
                            .catch(() => null)) as {
                            message?: string;
                            errors?: { url?: string[] };
                        } | null;
                        setRemote({
                            kind: 'unsupported',
                            play_url: null,
                            message:
                                body?.errors?.url?.[0] ??
                                body?.message ??
                                'This URL could not be embedded.',
                        });
                        return;
                    }
                    const payload = (await response.json()) as {
                        data?: { embed?: RemoteEmbed };
                    };
                    setRemote(payload.data?.embed ?? null);
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setRemote(null);
                    }
                });
        }, 350);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [enabled, sourceUrl]);

    return remote;
}

function applyRemoteEmbed(
    local: ResolvedLiveMedia,
    remote: RemoteEmbed | null,
): ResolvedLiveMedia {
    if (!remote) {
        return local;
    }

    if (
        remote.kind === 'blocked' ||
        remote.kind === 'unsupported' ||
        remote.kind === 'drm'
    ) {
        return {
            ...local,
            kind:
                remote.kind === 'blocked'
                    ? 'blocked'
                    : remote.kind === 'drm'
                      ? 'drm'
                      : 'unsupported',
            playUrl: null,
            message:
                remote.message ??
                'This YouTube video does not allow embedding.',
            needsLookup: false,
            status:
                remote.kind === 'unsupported' ? 'unsupported' : 'restricted',
        };
    }

    const playUrl = remote.play_url ?? remote.url ?? null;
    if (local.needsLookup && playUrl) {
        return resolveLiveMedia(playUrl, {
            autoplay: local.autoplay,
            muted: local.muted,
            loop: local.loop,
            controls: local.controls,
            volume: local.volume,
            kind: remote.kind ?? local.kind,
        });
    }

    return local;
}

/**
 * LiveMediaPlayer — shared renderer for Editor, Preview, and Player.
 * Exported as EmbedWidget for the existing widget registry.
 */
export function EmbedWidget({
    config,
    elementProps,
    mode = 'preview',
    isOnline = true,
    interact = false,
    showChrome,
}: EmbedWidgetProps) {
    const cfg = config as ExtendedEmbedConfig;
    const rawUrl =
        typeof cfg.source_url === 'string' && cfg.source_url.trim() !== ''
            ? cfg.source_url
            : typeof cfg.url === 'string'
              ? cfg.url
              : '';

    const volume = clampVolume(cfg.volume, 70);
    const resolvedLocal = resolveLiveMedia(rawUrl, {
        kind: cfg.kind ?? cfg.provider,
        autoplay: cfg.autoplay ?? true,
        muted: cfg.muted ?? true,
        loop: cfg.loop ?? true,
        controls: cfg.controls ?? true,
        volume,
    });
    const askServer =
        mode !== 'player' &&
        (resolvedLocal.needsLookup ||
            resolvedLocal.kind === 'youtube' ||
            resolvedLocal.kind === 'website');
    const remote = useRemoteEmbed(rawUrl, askServer);
    const resolved = applyRemoteEmbed(resolvedLocal, remote);
    const [playerBlocked, setPlayerBlocked] = useState<string | null>(null);
    const controllerRef = useRef<MediaController | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const [transport, setTransport] = useState<TransportState>({
        playing: Boolean(resolved.autoplay),
        muted: resolved.muted,
        volume,
        live: resolved.isLive,
        error: null,
        audioBlocked: Boolean(resolved.autoplay && resolved.muted === false),
    });

    const chromeVisible =
        showChrome ??
        (mode !== 'player'
            ? true
            : Boolean(resolved.controls) && resolved.kind !== 'website');

    useEffect(() => {
        setPlayerBlocked(null);
        setTransport({
            playing: Boolean(resolved.autoplay),
            muted: resolved.muted,
            volume,
            live: resolved.isLive,
            error: null,
            audioBlocked: Boolean(resolved.autoplay && !resolved.muted),
        });
    }, [
        resolved.playUrl,
        resolved.autoplay,
        resolved.muted,
        resolved.isLive,
        volume,
    ]);

    const bindController = (
        controller: MediaController,
        video?: HTMLVideoElement,
    ) => {
        controllerRef.current = controller;
        videoRef.current = video ?? null;
        if (video) {
            const sync = () => {
                setTransport((prev) => ({
                    ...prev,
                    playing: !video.paused,
                    muted: video.muted,
                    volume: Math.round(video.volume * 100),
                }));
            };
            video.addEventListener('play', sync);
            video.addEventListener('pause', sync);
            video.addEventListener('volumechange', sync);
        }
    };

    if (!isOnline) {
        return (
            <Placeholder
                title="Live content unavailable"
                detail="This embed needs an internet connection. Local Screen content continues."
                elementProps={elementProps}
            />
        );
    }

    if (
        playerBlocked ||
        resolved.kind === 'empty' ||
        resolved.kind === 'unsupported' ||
        resolved.kind === 'blocked' ||
        resolved.kind === 'drm' ||
        !resolved.playUrl
    ) {
        return (
            <Placeholder
                title={
                    playerBlocked
                        ? playerBlocked
                        : (resolved.message ?? 'Unsupported live media URL')
                }
                detail={
                    resolved.kind === 'blocked' || resolved.kind === 'drm'
                        ? null
                        : resolved.sourceUrl || null
                }
                elementProps={elementProps}
            />
        );
    }

    const label =
        resolved.isLive && resolved.kind === 'youtube'
            ? 'YouTube Live'
            : liveMediaKindLabel(resolved.kind);

    const surface =
        resolved.kind === 'hls' ? (
            <HlsSurface
                src={resolved.playUrl}
                autoplay={resolved.autoplay}
                muted={resolved.muted}
                volume={volume}
                loop={false}
                isLive
                onReady={bindController}
            />
        ) : resolved.kind === 'dash' ? (
            <DashSurface
                src={resolved.playUrl}
                autoplay={resolved.autoplay}
                muted={resolved.muted}
                volume={volume}
                onReady={bindController}
            />
        ) : resolved.kind === 'video' ? (
            <DirectVideoSurface
                src={resolved.playUrl}
                autoplay={resolved.autoplay}
                muted={resolved.muted}
                volume={volume}
                loop={resolved.loop}
                onReady={bindController}
            />
        ) : resolved.kind === 'youtube' ? (
            <YoutubeSurface
                playUrl={resolved.playUrl}
                autoplay={resolved.autoplay}
                muted={resolved.muted}
                volume={volume}
                isLive={resolved.isLive}
                onBlocked={setPlayerBlocked}
                onReady={(controller) => {
                    controllerRef.current = controller;
                }}
            />
        ) : resolved.kind === 'vimeo' ? (
            <VimeoSurface
                playUrl={resolved.playUrl}
                autoplay={resolved.autoplay}
                muted={resolved.muted}
                volume={volume}
                onReady={(controller) => {
                    controllerRef.current = controller;
                }}
            />
        ) : (
            <iframe
                title="Embedded content"
                src={resolved.playUrl}
                className="h-full w-full border-0 bg-black"
                sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
                allow="fullscreen; autoplay"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
            />
        );

    return (
        <WidgetShell
            props={elementProps}
            className={cn('relative p-0', interact && 'pointer-events-auto')}
        >
            <div
                className={cn(
                    'absolute top-1 left-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[9px] font-medium tracking-wide text-white uppercase',
                    !interact && 'pointer-events-none',
                )}
            >
                {label}
                {liveMediaStatusLabel(resolved) === 'supported' ? ' · ✓' : ''}
            </div>
            <div
                className={cn(
                    'h-full w-full',
                    interact ? 'pointer-events-auto' : 'pointer-events-none',
                )}
            >
                {surface}
            </div>
            {chromeVisible &&
            (resolved.kind === 'youtube' ||
                resolved.kind === 'vimeo' ||
                resolved.kind === 'hls' ||
                resolved.kind === 'dash' ||
                resolved.kind === 'video') ? (
                <TransportChrome
                    state={transport}
                    label={label}
                    onPlay={() => {
                        controllerRef.current?.play();
                        setTransport((prev) => ({ ...prev, playing: true }));
                    }}
                    onPause={() => {
                        controllerRef.current?.pause();
                        setTransport((prev) => ({ ...prev, playing: false }));
                    }}
                    onMute={() => {
                        controllerRef.current?.mute();
                        setTransport((prev) => ({
                            ...prev,
                            muted: true,
                            audioBlocked: false,
                        }));
                    }}
                    onUnmute={() => {
                        controllerRef.current?.unmute();
                        controllerRef.current?.setVolume(volume || 70);
                        setTransport((prev) => ({
                            ...prev,
                            muted: false,
                            volume: volume || 70,
                            audioBlocked: false,
                        }));
                    }}
                    onVolume={(next) => {
                        controllerRef.current?.setVolume(next);
                        setTransport((prev) => ({
                            ...prev,
                            volume: next,
                            muted: next === 0,
                            audioBlocked: false,
                        }));
                    }}
                    onRestart={() => {
                        controllerRef.current?.restart();
                        setTransport((prev) => ({ ...prev, playing: true }));
                    }}
                />
            ) : null}
        </WidgetShell>
    );
}

/** Alias for the canonical shared player. */
export const LiveMediaPlayer = EmbedWidget;
