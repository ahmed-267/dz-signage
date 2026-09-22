/**
 * LiveMediaResolver — client mirror of App\Support\Widgets\EmbedUrlValidator.
 * Editor / Preview / Player must share this so playback never loads raw watch pages.
 */

export type LiveMediaKind =
    | 'youtube'
    | 'vimeo'
    | 'hls'
    | 'dash'
    | 'video'
    | 'website'
    | 'teams'
    | 'zoom'
    | 'webex'
    | 'blocked'
    | 'drm'
    | 'unsupported'
    | 'empty';

/** @deprecated Prefer LiveMediaKind — kept for existing imports. */
export type EmbedKind = LiveMediaKind;

export type ResolvedLiveMedia = {
    kind: LiveMediaKind;
    sourceUrl: string;
    playUrl: string | null;
    message: string | null;
    autoplay: boolean;
    muted: boolean;
    loop: boolean;
    controls: boolean;
    /** 0–100. Applied when unmuted. */
    volume: number;
    /** True when the URL looks like a live broadcast (not VOD). */
    isLive: boolean;
    /** Channel/handle live URLs have no video id until the server asks YouTube. */
    needsLookup: boolean;
    /** Short status for the properties panel. */
    status: 'supported' | 'restricted' | 'unsupported' | 'resolving' | 'empty';
};

/** @deprecated Prefer ResolvedLiveMedia. */
export type ResolvedEmbed = ResolvedLiveMedia;

const KNOWN_BLOCKED = new Set([
    'www.bbc.co.uk',
    'bbc.co.uk',
    'www.bbc.com',
    'bbc.com',
    'www.cnn.com',
    'cnn.com',
    'www.nytimes.com',
    'nytimes.com',
    'www.theguardian.com',
    'theguardian.com',
]);

const DRM_HOSTS = new Set([
    'www.amazon.com',
    'amazon.com',
    'www.primevideo.com',
    'primevideo.com',
    'www.netflix.com',
    'netflix.com',
    'www.disneyplus.com',
    'disneyplus.com',
    'www.hulu.com',
    'hulu.com',
    'www.max.com',
    'max.com',
    'play.hbomax.com',
    'www.apple.com',
    'tv.apple.com',
    'www.paramountplus.com',
    'paramountplus.com',
    'www.peacocktv.com',
    'peacocktv.com',
    'www.spotify.com',
    'open.spotify.com',
]);

function blockedSiteMessage(host: string): string {
    if (
        host === 'bbc.co.uk' ||
        host === 'www.bbc.co.uk' ||
        host === 'bbc.com' ||
        host === 'www.bbc.com'
    ) {
        return 'BBC does not permit this page to be embedded directly. Use the News Widget, an official embeddable video, or a supported live-stream URL.';
    }

    return 'This website does not allow external embedding.';
}

function drmMessage(): string {
    return 'This provider does not support playback inside third-party signage applications.';
}

function resolveConferencing(
    host: string,
    path: string,
    sourceUrl: string,
    urlLower: string,
): Omit<
    ResolvedLiveMedia,
    'autoplay' | 'muted' | 'loop' | 'controls' | 'volume'
> | null {
    const pathLower = path.toLowerCase();
    const isTeams =
        host === 'teams.microsoft.com' ||
        host === 'teams.live.com' ||
        host.endsWith('.teams.microsoft.com') ||
        host === 'events.teams.microsoft.com' ||
        host === 'www.microsoftstream.com' ||
        host === 'web.microsoftstream.com' ||
        host === 'microsoftstream.com' ||
        host.endsWith('.stream.azure.net');
    const isZoom =
        host === 'zoom.us' ||
        host === 'www.zoom.us' ||
        host.endsWith('.zoom.us') ||
        host === 'zoom.com' ||
        host === 'www.zoom.com';
    const isWebex =
        host === 'webex.com' ||
        host === 'www.webex.com' ||
        host.endsWith('.webex.com');

    if (isTeams) {
        const embeddable =
            pathLower.includes('/embed/') ||
            urlLower.includes('/embed/video/') ||
            (host.includes('stream') && pathLower.includes('/video/')) ||
            (pathLower.includes('/embed') &&
                !pathLower.includes('/l/meetup-join/'));
        if (embeddable) {
            return {
                kind: 'teams',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: true,
                needsLookup: false,
                status: 'supported',
            };
        }
        return {
            kind: 'unsupported',
            sourceUrl,
            playUrl: null,
            message:
                'This Teams link requires the Microsoft Teams experience and cannot be embedded directly in RMSignage. Use a public Stream/embed URL, an HLS stream, or an official embeddable player link.',
            isLive: true,
            needsLookup: false,
            status: 'unsupported',
        };
    }

    if (isZoom) {
        const embeddable =
            pathLower.includes('/embed') ||
            urlLower.includes('embed=true') ||
            (host.includes('webcast') && pathLower.includes('/viewer'));
        if (embeddable) {
            return {
                kind: 'zoom',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: true,
                needsLookup: false,
                status: 'supported',
            };
        }
        return {
            kind: 'unsupported',
            sourceUrl,
            playUrl: null,
            message:
                'This Zoom link requires the Zoom client or an authenticated participant session and cannot be embedded directly in RMSignage. Use an official webcast/embed viewer URL, HLS, or another supported live stream.',
            isLive: true,
            needsLookup: false,
            status: 'unsupported',
        };
    }

    if (isWebex) {
        const embeddable =
            pathLower.includes('/embed') ||
            urlLower.includes('embed=true') ||
            pathLower.includes('/widget');
        if (embeddable) {
            return {
                kind: 'webex',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: true,
                needsLookup: false,
                status: 'supported',
            };
        }
        return {
            kind: 'unsupported',
            sourceUrl,
            playUrl: null,
            message:
                'This Webex link requires the Webex experience and cannot be embedded directly in RMSignage. Use an official embed/widget URL, HLS, or another supported live stream.',
            isLive: true,
            needsLookup: false,
            status: 'unsupported',
        };
    }

    return null;
}

/** @handle/live, /channel/ID/live, /c/name/live, /user/name/live — no video id in the URL. */
export function youtubeNeedsLiveLookup(rawUrl: string): boolean {
    try {
        const parsed = new URL(rawUrl.trim());
        const host = parsed.hostname.toLowerCase();
        if (
            host !== 'www.youtube.com' &&
            host !== 'youtube.com' &&
            host !== 'm.youtube.com'
        ) {
            return false;
        }
        if (parsed.searchParams.get('v')) {
            return false;
        }
        const path = parsed.pathname.replace(/\/+$/, '');
        if (/^\/(embed|live|shorts)\/[A-Za-z0-9_-]{6,}/.test(path)) {
            return false;
        }

        return path.endsWith('/live');
    } catch {
        return false;
    }
}

function youtubeIsLiveUrl(rawUrl: string, path: string): boolean {
    if (youtubeNeedsLiveLookup(rawUrl)) {
        return true;
    }
    return (
        path.startsWith('/live/') || path.replace(/\/+$/, '').endsWith('/live')
    );
}

function clampVolume(value: unknown, fallback = 70): number {
    const n = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(n)) {
        return fallback;
    }
    return Math.min(100, Math.max(0, Math.round(n)));
}

function withPlayback(
    partial: Omit<
        ResolvedLiveMedia,
        'autoplay' | 'muted' | 'loop' | 'controls' | 'volume'
    >,
    autoplay: boolean,
    muted: boolean,
    loop: boolean,
    controls: boolean,
    volume: number,
): ResolvedLiveMedia {
    return { ...partial, autoplay, muted, loop, controls, volume };
}

function youtubeId(
    host: string,
    path: string,
    queryV: string | null,
): string | null {
    if (host === 'youtu.be') {
        const id = path.replace(/^\//, '').split('/')[0] ?? '';
        return /^[A-Za-z0-9_-]{6,}$/.test(id) ? id : null;
    }
    const embed = path.match(/^\/embed\/([A-Za-z0-9_-]{6,})/);
    if (embed) return embed[1];
    const shorts = path.match(/^\/shorts\/([A-Za-z0-9_-]{6,})/);
    if (shorts) return shorts[1];
    const live = path.match(/^\/live\/([A-Za-z0-9_-]{6,})/);
    if (live) return live[1];
    if (queryV && /^[A-Za-z0-9_-]{6,}$/.test(queryV)) return queryV;
    return null;
}

function vimeoId(host: string, path: string): string | null {
    if (host.includes('player.vimeo.com')) {
        const m = path.match(/^\/video\/(\d+)/);
        return m?.[1] ?? null;
    }
    const m = path.match(/^\/(\d+)/);
    return m?.[1] ?? null;
}

export type ResolveLiveMediaOptions = {
    autoplay?: boolean;
    muted?: boolean;
    loop?: boolean;
    controls?: boolean;
    volume?: number;
    kind?: string | null;
};

/** Canonical client resolver used by Editor, Preview, and Player. */
export function resolveLiveMedia(
    rawUrl: string,
    options: ResolveLiveMediaOptions = {},
): ResolvedLiveMedia {
    const sourceUrl = rawUrl.trim();
    const autoplay = options.autoplay ?? true;
    const muted = options.muted ?? true;
    const loop = options.loop ?? true;
    const controls = options.controls ?? true;
    const volume = clampVolume(options.volume, 70);

    if (sourceUrl === '') {
        return withPlayback(
            {
                kind: 'empty',
                sourceUrl,
                playUrl: null,
                message: 'Paste an https live or video URL',
                isLive: false,
                needsLookup: false,
                status: 'empty',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    let parsed: URL;
    try {
        parsed = new URL(sourceUrl);
    } catch {
        return withPlayback(
            {
                kind: 'unsupported',
                sourceUrl,
                playUrl: null,
                message: 'This URL is invalid.',
                isLive: false,
                needsLookup: false,
                status: 'unsupported',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    if (parsed.protocol !== 'https:') {
        return withPlayback(
            {
                kind: 'unsupported',
                sourceUrl,
                playUrl: null,
                message: 'Live media URLs must use HTTPS.',
                isLive: false,
                needsLookup: false,
                status: 'unsupported',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    const host = parsed.hostname.toLowerCase();
    const path = parsed.pathname;
    const pathLower = path.toLowerCase();
    const urlLower = sourceUrl.toLowerCase();
    const queryV = parsed.searchParams.get('v');

    if (DRM_HOSTS.has(host) || options.kind === 'drm') {
        return withPlayback(
            {
                kind: 'drm',
                sourceUrl,
                playUrl: null,
                message: drmMessage(),
                isLive: false,
                needsLookup: false,
                status: 'restricted',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    if (
        options.kind === 'dash' ||
        pathLower.endsWith('.mpd') ||
        urlLower.includes('.mpd?')
    ) {
        return withPlayback(
            {
                kind: 'dash',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: true,
                needsLookup: false,
                status: 'supported',
            },
            autoplay,
            muted,
            false,
            controls,
            volume,
        );
    }

    const conference = resolveConferencing(host, path, sourceUrl, urlLower);
    if (conference) {
        return withPlayback(
            conference,
            autoplay,
            muted,
            false,
            controls,
            volume,
        );
    }

    if (
        options.kind === 'youtube' ||
        host === 'www.youtube.com' ||
        host === 'youtube.com' ||
        host === 'youtu.be' ||
        host === 'www.youtube-nocookie.com' ||
        host === 'm.youtube.com'
    ) {
        const id =
            youtubeId(host, path, queryV) ??
            (path.startsWith('/embed/') ? (path.split('/')[2] ?? null) : null);
        const isLive = youtubeIsLiveUrl(sourceUrl, path);
        if (!id) {
            if (youtubeNeedsLiveLookup(sourceUrl)) {
                return withPlayback(
                    {
                        kind: 'youtube',
                        sourceUrl,
                        playUrl: null,
                        message: 'Resolving YouTube live video…',
                        isLive: true,
                        needsLookup: true,
                        status: 'resolving',
                    },
                    autoplay,
                    muted,
                    loop,
                    controls,
                    volume,
                );
            }

            return withPlayback(
                {
                    kind: 'unsupported',
                    sourceUrl,
                    playUrl: null,
                    message:
                        'Could not resolve a YouTube video id from this URL.',
                    isLive: false,
                    needsLookup: false,
                    status: 'unsupported',
                },
                autoplay,
                muted,
                loop,
                controls,
                volume,
            );
        }
        const params = new URLSearchParams({
            autoplay: autoplay ? '1' : '0',
            mute: muted ? '1' : '0',
            controls: '0',
            loop: !isLive && loop ? '1' : '0',
            modestbranding: '1',
            rel: '0',
            playsinline: '1',
            enablejsapi: '1',
        });
        if (!isLive && loop) {
            params.set('playlist', id);
        }
        if (typeof window !== 'undefined' && window.location?.origin) {
            params.set('origin', window.location.origin);
        }
        const playUrl = `https://www.youtube.com/embed/${id}?${params.toString()}`;
        return withPlayback(
            {
                kind: 'youtube',
                sourceUrl,
                playUrl,
                message: null,
                isLive,
                needsLookup: false,
                status: 'supported',
            },
            autoplay,
            muted,
            isLive ? false : loop,
            controls,
            volume,
        );
    }

    if (
        options.kind === 'vimeo' ||
        host === 'vimeo.com' ||
        host === 'www.vimeo.com' ||
        host === 'player.vimeo.com'
    ) {
        const id = vimeoId(host, path);
        if (!id) {
            return withPlayback(
                {
                    kind: 'unsupported',
                    sourceUrl,
                    playUrl: null,
                    message:
                        'Could not resolve a Vimeo video id from this URL.',
                    isLive: false,
                    needsLookup: false,
                    status: 'unsupported',
                },
                autoplay,
                muted,
                loop,
                controls,
                volume,
            );
        }
        const params = new URLSearchParams({
            autoplay: autoplay ? '1' : '0',
            muted: muted ? '1' : '0',
            loop: loop ? '1' : '0',
            controls: '0',
            title: '0',
            byline: '0',
            playsinline: '1',
            api: '1',
        });
        return withPlayback(
            {
                kind: 'vimeo',
                sourceUrl,
                playUrl: `https://player.vimeo.com/video/${id}?${params.toString()}`,
                message: null,
                isLive: false,
                needsLookup: false,
                status: 'supported',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    if (
        options.kind === 'hls' ||
        pathLower.endsWith('.m3u8') ||
        urlLower.includes('.m3u8?') ||
        parsed.searchParams.get('format')?.toLowerCase() === 'm3u8'
    ) {
        return withPlayback(
            {
                kind: 'hls',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: true,
                needsLookup: false,
                status: 'supported',
            },
            autoplay,
            muted,
            false,
            controls,
            volume,
        );
    }

    if (
        options.kind === 'video' ||
        ['.mp4', '.webm', '.ogg', '.ogv'].some((ext) => pathLower.endsWith(ext))
    ) {
        return withPlayback(
            {
                kind: 'video',
                sourceUrl,
                playUrl: sourceUrl,
                message: null,
                isLive: false,
                needsLookup: false,
                status: 'supported',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    if (KNOWN_BLOCKED.has(host) || options.kind === 'blocked') {
        return withPlayback(
            {
                kind: 'blocked',
                sourceUrl,
                playUrl: null,
                message: blockedSiteMessage(host),
                isLive: false,
                needsLookup: false,
                status: 'restricted',
            },
            autoplay,
            muted,
            loop,
            controls,
            volume,
        );
    }

    return withPlayback(
        {
            kind: 'website',
            sourceUrl,
            playUrl: sourceUrl,
            message: null,
            isLive: false,
            needsLookup: false,
            status: 'supported',
        },
        autoplay,
        muted,
        loop,
        controls,
        volume,
    );
}

/** @deprecated Prefer resolveLiveMedia. */
export function resolveEmbedUrl(
    rawUrl: string,
    options: ResolveLiveMediaOptions = {},
): ResolvedLiveMedia {
    return resolveLiveMedia(rawUrl, options);
}

export function liveMediaKindLabel(kind: LiveMediaKind): string {
    switch (kind) {
        case 'youtube':
            return 'YouTube';
        case 'vimeo':
            return 'Vimeo';
        case 'hls':
            return 'HLS Live Stream';
        case 'dash':
            return 'DASH Stream';
        case 'video':
            return 'Direct Video';
        case 'website':
            return 'Embeddable Website';
        case 'teams':
            return 'Microsoft Teams';
        case 'zoom':
            return 'Zoom';
        case 'webex':
            return 'Webex';
        case 'blocked':
            return 'Embedding restricted';
        case 'drm':
            return 'DRM / provider-restricted';
        case 'unsupported':
            return 'Unsupported';
        default:
            return 'Live / Video';
    }
}

/** @deprecated Prefer liveMediaKindLabel. */
export function embedKindLabel(kind: LiveMediaKind): string {
    return liveMediaKindLabel(kind);
}

export function liveMediaStatusLabel(
    resolved: ResolvedLiveMedia,
): 'supported' | 'restricted' | 'unavailable' | 'resolving' | 'empty' {
    if (resolved.status === 'resolving') {
        return 'resolving';
    }
    if (resolved.status === 'empty') {
        return 'empty';
    }
    if (
        resolved.kind === 'blocked' ||
        resolved.kind === 'drm' ||
        resolved.status === 'restricted'
    ) {
        return 'restricted';
    }
    if (resolved.kind === 'unsupported' || !resolved.playUrl) {
        return 'unavailable';
    }
    return 'supported';
}
