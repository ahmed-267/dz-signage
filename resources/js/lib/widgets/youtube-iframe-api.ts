/**
 * Lazily loads the official YouTube IFrame Player API once per page.
 * Shared by Editor, Preview, and Player — never load the script twice.
 */

export type YoutubePlayer = {
    playVideo: () => void;
    pauseVideo: () => void;
    stopVideo: () => void;
    seekTo: (seconds: number, allowSeekAhead: boolean) => void;
    mute: () => void;
    unMute: () => void;
    isMuted: () => boolean;
    setVolume: (volume: number) => void;
    getVolume: () => number;
    getPlayerState: () => number;
    getCurrentTime: () => number;
    getDuration: () => number;
    destroy: () => void;
};

export type YoutubePlayerEvent = {
    target: YoutubePlayer;
    data: number;
};

type YoutubeNamespace = {
    Player: new (
        element: HTMLElement | string,
        options: {
            videoId?: string;
            width?: string | number;
            height?: string | number;
            playerVars?: Record<string, string | number>;
            events?: {
                onReady?: (event: YoutubePlayerEvent) => void;
                onStateChange?: (event: YoutubePlayerEvent) => void;
                onError?: (event: YoutubePlayerEvent) => void;
            };
        },
    ) => YoutubePlayer;
    PlayerState: {
        UNSTARTED: number;
        ENDED: number;
        PLAYING: number;
        PAUSED: number;
        BUFFERING: number;
        CUED: number;
    };
};

declare global {
    interface Window {
        YT?: YoutubeNamespace;
        onYouTubeIframeAPIReady?: () => void;
    }
}

let apiPromise: Promise<YoutubeNamespace> | null = null;

export function loadYoutubeIframeApi(): Promise<YoutubeNamespace> {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('YouTube API requires a browser.'));
    }

    if (window.YT?.Player) {
        return Promise.resolve(window.YT);
    }

    if (apiPromise) {
        return apiPromise;
    }

    apiPromise = new Promise<YoutubeNamespace>((resolve, reject) => {
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            try {
                previous?.();
            } catch {
                // ignore prior handlers that throw
            }
            if (window.YT?.Player) {
                resolve(window.YT);
            } else {
                reject(new Error('YouTube IFrame API failed to initialise.'));
            }
        };

        if (!document.querySelector('script[data-rm-youtube-api]')) {
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.async = true;
            script.dataset.rmYoutubeApi = '1';
            script.onerror = () =>
                reject(new Error('Failed to load the YouTube IFrame API.'));
            document.head.appendChild(script);
        }
    });

    return apiPromise;
}

/** YouTube player state constants (mirror of YT.PlayerState). */
export const YT_STATE = {
    UNSTARTED: -1,
    ENDED: 0,
    PLAYING: 1,
    PAUSED: 2,
    BUFFERING: 3,
    CUED: 5,
} as const;

export function extractYoutubeVideoId(playUrl: string): string | null {
    try {
        const parsed = new URL(playUrl);
        const match = parsed.pathname.match(/\/embed\/([A-Za-z0-9_-]{6,})/);
        return match?.[1] ?? null;
    } catch {
        return null;
    }
}
