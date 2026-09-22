<?php

namespace App\Support\Widgets;

use App\Support\Widgets\LiveMedia\ConferencingProviderResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Classify and resolve Embed / live-stream URLs into a playable form.
 *
 * Supported kinds: youtube, vimeo, hls, dash, video, website, teams, zoom,
 * webex, blocked, drm, unsupported.
 * Never bypasses third-party framing restrictions — blocked sites stay blocked.
 */
final class EmbedUrlValidator
{
    public const KIND_YOUTUBE = 'youtube';

    public const KIND_VIMEO = 'vimeo';

    public const KIND_HLS = 'hls';

    public const KIND_DASH = 'dash';

    public const KIND_VIDEO = 'video';

    public const KIND_WEBSITE = 'website';

    public const KIND_TEAMS = 'teams';

    public const KIND_ZOOM = 'zoom';

    public const KIND_WEBEX = 'webex';

    public const KIND_BLOCKED = 'blocked';

    public const KIND_DRM = 'drm';

    public const KIND_UNSUPPORTED = 'unsupported';

    /**
     * @param  array{
     *     autoplay?: bool,
     *     muted?: bool,
     *     loop?: bool,
     *     controls?: bool,
     *     volume?: int|float,
     *     check_framing?: bool,
     *     check_embeddable?: bool
     * }  $options
     * @return array{
     *     kind: string,
     *     source_url: string,
     *     play_url: string|null,
     *     message: string|null,
     *     autoplay: bool,
     *     muted: bool,
     *     loop: bool,
     *     controls: bool,
     *     volume: int,
     *     is_live: bool
     * }
     *
     * @throws ValidationException
     */
    public static function resolve(string $url, array $options = []): array
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'url' => 'An embed URL is required.',
            ]);
        }

        $lower = strtolower($trimmed);
        foreach (['javascript:', 'data:', 'file:', 'vbscript:', 'ftp:'] as $blocked) {
            if (str_starts_with($lower, $blocked)) {
                throw ValidationException::withMessages([
                    'url' => 'This embed URL scheme is not allowed.',
                ]);
            }
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'url' => 'The embed URL is invalid.',
            ]);
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            throw ValidationException::withMessages([
                'url' => 'The embed URL is invalid.',
            ]);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            throw ValidationException::withMessages([
                'url' => 'Embed URLs must use HTTPS.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw ValidationException::withMessages([
                'url' => 'The embed URL is invalid.',
            ]);
        }

        // Block obvious local/metadata hosts without DNS (keeps CI offline-safe for YouTube/Vimeo).
        if (self::isBlockedLocalHost($host)) {
            throw ValidationException::withMessages([
                'url' => 'This embed host is not allowed.',
            ]);
        }

        $path = (string) ($parts['path'] ?? '');
        $queryV = null;
        $queryFormat = null;
        if (array_key_exists('query', $parts)) {
            $parsedQuery = [];
            parse_str((string) $parts['query'], $parsedQuery);
            if (isset($parsedQuery['v']) && is_string($parsedQuery['v'])) {
                $queryV = $parsedQuery['v'];
            }
            if (isset($parsedQuery['format']) && is_string($parsedQuery['format'])) {
                $queryFormat = strtolower($parsedQuery['format']);
            }
        }

        $autoplay = (bool) ($options['autoplay'] ?? true);
        $muted = (bool) ($options['muted'] ?? true);
        $loop = (bool) ($options['loop'] ?? true);
        $controls = (bool) ($options['controls'] ?? true);
        $volume = self::clampVolume($options['volume'] ?? 70);

        if (self::isDrmHost($host)) {
            return [
                'kind' => self::KIND_DRM,
                'source_url' => $trimmed,
                'play_url' => null,
                'message' => 'This provider does not support playback inside third-party signage applications.',
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $loop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => false,
            ];
        }

        $pathLower = strtolower($path);
        if (
            str_ends_with($pathLower, '.mpd')
            || str_contains(strtolower($trimmed), '.mpd?')
        ) {
            self::assertSafeRemote($trimmed);

            return [
                'kind' => self::KIND_DASH,
                'source_url' => $trimmed,
                'play_url' => $trimmed,
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => false,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => true,
            ];
        }

        $conference = ConferencingProviderResolver::resolve($trimmed, $host, $path);
        if ($conference !== null) {
            $kind = match ($conference['kind']) {
                ConferencingProviderResolver::KIND_TEAMS => self::KIND_TEAMS,
                ConferencingProviderResolver::KIND_ZOOM => self::KIND_ZOOM,
                ConferencingProviderResolver::KIND_WEBEX => self::KIND_WEBEX,
                default => self::KIND_UNSUPPORTED,
            };

            if ($conference['embeddable'] && $conference['play_url'] !== null) {
                self::assertSafeRemote($conference['play_url']);
            }

            return [
                'kind' => $conference['embeddable'] ? $kind : self::KIND_UNSUPPORTED,
                'source_url' => $trimmed,
                'play_url' => $conference['embeddable'] ? $conference['play_url'] : null,
                'message' => $conference['message'],
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => false,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => $conference['is_live'],
                'provider' => $conference['provider'],
            ];
        }

        if (self::isYoutubeHost($host)) {
            $id = self::youtubeId($host, $path, $queryV);
            if ($id === null && self::isYoutubeLiveLookup($path)) {
                $id = self::youtubeIdFromOembed($trimmed);
            }
            if ($id === null) {
                throw ValidationException::withMessages([
                    'url' => 'Could not resolve a YouTube video id from this URL.',
                ]);
            }

            $isLive = self::isYoutubeLiveLookup($path) || (bool) preg_match('#^/live/#', $path);

            if (($options['check_embeddable'] ?? false) && ! self::youtubeAllowsEmbedding($id)) {
                return [
                    'kind' => self::KIND_BLOCKED,
                    'source_url' => $trimmed,
                    'play_url' => null,
                    'message' => 'This YouTube video does not allow embedding.',
                    'autoplay' => $autoplay,
                    'muted' => $muted,
                    'loop' => $loop,
                    'controls' => $controls,
                    'volume' => $volume,
                    'is_live' => $isLive,
                ];
            }

            $effectiveLoop = $isLive ? false : $loop;
            $params = http_build_query(array_filter([
                'autoplay' => $autoplay ? '1' : '0',
                'mute' => $muted ? '1' : '0',
                'controls' => '0',
                'loop' => $effectiveLoop ? '1' : '0',
                'playlist' => $effectiveLoop ? $id : null,
                'modestbranding' => '1',
                'rel' => '0',
                'playsinline' => '1',
                'enablejsapi' => '1',
            ]));

            return [
                'kind' => self::KIND_YOUTUBE,
                'source_url' => $trimmed,
                'play_url' => 'https://www.youtube.com/embed/'.$id.($params !== '' ? '?'.$params : ''),
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $effectiveLoop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => $isLive,
            ];
        }

        if (self::isVimeoHost($host)) {
            $id = self::vimeoId($host, $path);
            if ($id === null) {
                throw ValidationException::withMessages([
                    'url' => 'Could not resolve a Vimeo video id from this URL.',
                ]);
            }

            $params = http_build_query(array_filter([
                'autoplay' => $autoplay ? '1' : '0',
                'muted' => $muted ? '1' : '0',
                'loop' => $loop ? '1' : '0',
                'controls' => $controls ? '1' : '0',
                'title' => '0',
                'byline' => '0',
            ]));

            return [
                'kind' => self::KIND_VIMEO,
                'source_url' => $trimmed,
                'play_url' => 'https://player.vimeo.com/video/'.$id.($params !== '' ? '?'.$params : ''),
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $loop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => false,
            ];
        }

        if (self::looksLikeHls($path, $trimmed) || $queryFormat === 'm3u8') {
            self::assertSafeRemote($trimmed);

            return [
                'kind' => self::KIND_HLS,
                'source_url' => $trimmed,
                'play_url' => $trimmed,
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => false,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => true,
            ];
        }

        if (self::looksLikeDirectVideo($path)) {
            self::assertSafeRemote($trimmed);

            return [
                'kind' => self::KIND_VIDEO,
                'source_url' => $trimmed,
                'play_url' => $trimmed,
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $loop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => false,
            ];
        }

        // Known non-embeddable news hosts — guide toward News widget.
        if (self::isKnownNonEmbeddableHost($host)) {
            return [
                'kind' => self::KIND_BLOCKED,
                'source_url' => $trimmed,
                'play_url' => null,
                'message' => self::blockedWebsiteMessage($host),
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $loop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => false,
            ];
        }

        // Generic HTTPS website — allowed for iframe attempt; client/preflight may still report blocked.
        if (self::websiteEmbedsEnabled()) {
            self::assertSafeRemote($trimmed);

            if (($options['check_framing'] ?? false) === true) {
                $framing = self::framingBlockMessage($trimmed, $host);
                if ($framing !== null) {
                    return [
                        'kind' => self::KIND_BLOCKED,
                        'source_url' => $trimmed,
                        'play_url' => null,
                        'message' => $framing,
                        'autoplay' => $autoplay,
                        'muted' => $muted,
                        'loop' => $loop,
                        'controls' => $controls,
                        'volume' => $volume,
                        'is_live' => false,
                    ];
                }
            }

            return [
                'kind' => self::KIND_WEBSITE,
                'source_url' => $trimmed,
                'play_url' => $trimmed,
                'message' => null,
                'autoplay' => $autoplay,
                'muted' => $muted,
                'loop' => $loop,
                'controls' => $controls,
                'volume' => $volume,
                'is_live' => false,
            ];
        }

        throw ValidationException::withMessages([
            'url' => 'This URL type is not supported for embedding. Use YouTube, Vimeo, an HLS (.m3u8) or MP4/WebM URL.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public static function normalize(string $url): string
    {
        $resolved = self::resolve($url);
        if (
            $resolved['kind'] === self::KIND_BLOCKED
            || $resolved['kind'] === self::KIND_DRM
            || $resolved['kind'] === self::KIND_UNSUPPORTED
            || $resolved['play_url'] === null
        ) {
            throw ValidationException::withMessages([
                'url' => $resolved['message'] ?? 'This URL cannot be embedded.',
            ]);
        }

        // Persist the canonical play URL for YouTube/Vimeo; keep source for media streams.
        if (in_array($resolved['kind'], [self::KIND_YOUTUBE, self::KIND_VIMEO], true)) {
            // Strip query for stable storage; playback params applied at render time.
            $play = (string) $resolved['play_url'];
            $base = strtok($play, '?');

            return $base !== false ? $base : $play;
        }

        return (string) $resolved['play_url'];
    }

    public static function isAllowed(string $url): bool
    {
        try {
            $resolved = self::resolve($url);

            return $resolved['kind'] !== self::KIND_BLOCKED
                && $resolved['kind'] !== self::KIND_UNSUPPORTED
                && $resolved['play_url'] !== null;
        } catch (ValidationException) {
            return false;
        }
    }

    private static function websiteEmbedsEnabled(): bool
    {
        return (bool) config('widgets.embed.allow_generic_websites', true);
    }

    private static function assertSafeRemote(string $url): void
    {
        if (! SafeRemoteUrl::isSafe($url)) {
            throw ValidationException::withMessages([
                'url' => 'This embed host is not allowed.',
            ]);
        }
    }

    private static function isBlockedLocalHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'metadata', 'metadata.google.internal'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        $candidate = $host;
        if (str_starts_with($candidate, '[') && str_ends_with($candidate, ']')) {
            $candidate = substr($candidate, 1, -1);
        }

        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            // Reuse SafeRemoteUrl path for IP literals via isSafe on a synthetic URL.
            return ! SafeRemoteUrl::isSafe('https://'.$host.'/');
        }

        return false;
    }

    private static function isYoutubeHost(string $host): bool
    {
        return in_array($host, [
            'www.youtube.com',
            'youtube.com',
            'youtu.be',
            'www.youtube-nocookie.com',
            'm.youtube.com',
        ], true);
    }

    private static function isVimeoHost(string $host): bool
    {
        return in_array($host, ['vimeo.com', 'player.vimeo.com', 'www.vimeo.com'], true);
    }

    private static function isKnownNonEmbeddableHost(string $host): bool
    {
        $blocked = config('widgets.embed.known_blocked_hosts', [
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

        return is_array($blocked) && in_array($host, $blocked, true);
    }

    private static function looksLikeHls(string $path, string $url): bool
    {
        $pathLower = strtolower($path);
        $urlLower = strtolower($url);

        return str_ends_with($pathLower, '.m3u8')
            || str_contains($urlLower, '.m3u8?')
            || str_contains($urlLower, 'format=m3u8');
    }

    private static function looksLikeDirectVideo(string $path): bool
    {
        $pathLower = strtolower($path);

        foreach (['.mp4', '.webm', '.ogg', '.ogv'] as $ext) {
            if (str_ends_with($pathLower, $ext)) {
                return true;
            }
        }

        return false;
    }

    private static function youtubeId(string $host, string $path, ?string $queryV): ?string
    {
        if ($host === 'youtu.be') {
            $segments = explode('/', ltrim($path, '/'));

            return self::validVideoId($segments[0]);
        }

        if (preg_match('#^/embed/([A-Za-z0-9_-]{6,})#', $path, $m) === 1) {
            return self::validVideoId($m[1]);
        }

        if (preg_match('#^/shorts/([A-Za-z0-9_-]{6,})#', $path, $m) === 1) {
            return self::validVideoId($m[1]);
        }

        if (preg_match('#^/live/([A-Za-z0-9_-]{6,})#', $path, $m) === 1) {
            return self::validVideoId($m[1]);
        }

        if ($queryV !== null) {
            return self::validVideoId($queryV);
        }

        return null;
    }

    private static function vimeoId(string $host, string $path): ?string
    {
        if (str_contains($host, 'player.vimeo.com') && preg_match('#^/video/(\d+)#', $path, $m) === 1) {
            return $m[1];
        }

        if (preg_match('#^/(\d+)#', $path, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private static function isYoutubeLiveLookup(string $path): bool
    {
        $path = rtrim($path, '/');

        return preg_match('#^/@[^/]+/live$#', $path) === 1
            || preg_match('#^/(channel|c|user)/[^/]+/live$#', $path) === 1;
    }

    private static function youtubeIdFromOembed(string $pageUrl): string
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get('https://www.youtube.com/oembed', [
                    'url' => $pageUrl,
                    'format' => 'json',
                ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'url' => 'Could not resolve this YouTube live URL. Use a youtube.com/watch or /live/VIDEO_ID link.',
            ]);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw ValidationException::withMessages([
                'url' => 'This YouTube video does not allow embedding.',
            ]);
        }

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'url' => 'Could not resolve a YouTube video from this live URL.',
            ]);
        }

        $html = (string) $response->json('html', '');
        if (preg_match('#/embed/([A-Za-z0-9_-]{6,})#', $html, $matches) === 1) {
            $id = self::validVideoId($matches[1]);
            if ($id !== null) {
                return $id;
            }
        }

        throw ValidationException::withMessages([
            'url' => 'Could not resolve a YouTube video from this live URL.',
        ]);
    }

    /**
     * Fail open when YouTube cannot be reached so a saved design still attempts playback.
     */
    private static function youtubeAllowsEmbedding(string $id): bool
    {
        try {
            $response = Http::timeout(5)
                ->get('https://www.youtube.com/oembed', [
                    'url' => 'https://www.youtube.com/watch?v='.$id,
                    'format' => 'json',
                ]);
        } catch (Throwable) {
            return true;
        }

        return ! in_array($response->status(), [401, 403], true);
    }

    private static function blockedWebsiteMessage(string $host): string
    {
        if (in_array($host, ['bbc.co.uk', 'www.bbc.co.uk', 'bbc.com', 'www.bbc.com'], true)) {
            return 'BBC does not permit this page to be embedded directly. Use the News Widget, an official embeddable video, or a supported live-stream URL.';
        }

        return 'This website does not allow external embedding.';
    }

    private static function clampVolume(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 70;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private static function isDrmHost(string $host): bool
    {
        return in_array($host, [
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
        ], true);
    }

    /**
     * HEAD the page without following redirects automatically, so a redirect
     * onto a private address is rejected instead of fetched.
     */
    private static function framingBlockMessage(string $url, string $host): ?string
    {
        $current = $url;

        try {
            for ($hop = 0; $hop < 3; $hop++) {
                if (! SafeRemoteUrl::isSafe($current)) {
                    return 'This embed host is not allowed.';
                }

                $response = Http::withOptions(['allow_redirects' => false])
                    ->timeout(4)
                    ->withHeaders(['User-Agent' => 'RMSignageEmbedCheck/1.0'])
                    ->head($current);

                if ($response->status() >= 300 && $response->status() < 400) {
                    $location = (string) $response->header('Location');
                    if ($location === '') {
                        return null;
                    }
                    $current = self::resolveRedirect($current, $location);

                    continue;
                }

                $xfo = strtolower((string) $response->header('X-Frame-Options'));
                if (str_contains($xfo, 'deny') || str_contains($xfo, 'sameorigin')) {
                    return self::blockedWebsiteMessage($host);
                }

                $csp = strtolower((string) $response->header('Content-Security-Policy'));
                if (preg_match('/frame-ancestors\s+([^;]+)/', $csp, $matches) === 1) {
                    $ancestors = $matches[1];
                    if (! str_contains($ancestors, '*')) {
                        return self::blockedWebsiteMessage($host);
                    }
                }

                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function resolveRedirect(string $current, string $location): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $parts = parse_url($current);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin.$dir.'/'.$location;
    }

    private static function validVideoId(string $id): ?string
    {
        $id = trim($id);
        if ($id === '' || preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) !== 1) {
            return null;
        }

        return $id;
    }
}
