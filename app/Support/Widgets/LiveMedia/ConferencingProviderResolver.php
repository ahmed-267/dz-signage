<?php

namespace App\Support\Widgets\LiveMedia;

/**
 * Detect Microsoft Teams / Zoom / Webex URLs and decide whether RMSignage can
 * embed them. Never bypasses auth, lobby, or frame restrictions.
 */
final class ConferencingProviderResolver
{
    public const KIND_TEAMS = 'teams';

    public const KIND_ZOOM = 'zoom';

    public const KIND_WEBEX = 'webex';

    /**
     * @return array{
     *     kind: string,
     *     provider: string,
     *     play_url: string|null,
     *     message: string|null,
     *     is_live: bool,
     *     embeddable: bool
     * }|null
     */
    public static function resolve(string $url, string $host, string $path): ?array
    {
        $host = strtolower($host);
        $path = strtolower($path);
        $urlLower = strtolower($url);

        if (self::isTeamsHost($host)) {
            return self::teams($url, $host, $path, $urlLower);
        }

        if (self::isZoomHost($host)) {
            return self::zoom($url, $host, $path, $urlLower);
        }

        if (self::isWebexHost($host)) {
            return self::webex($url, $host, $path, $urlLower);
        }

        return null;
    }

    private static function isTeamsHost(string $host): bool
    {
        return $host === 'teams.microsoft.com'
            || $host === 'teams.live.com'
            || str_ends_with($host, '.teams.microsoft.com')
            || $host === 'events.teams.microsoft.com'
            || $host === 'www.microsoftstream.com'
            || $host === 'web.microsoftstream.com'
            || $host === 'microsoftstream.com'
            || str_ends_with($host, '.stream.azure.net');
    }

    private static function isZoomHost(string $host): bool
    {
        return $host === 'zoom.us'
            || $host === 'www.zoom.us'
            || str_ends_with($host, '.zoom.us')
            || $host === 'zoom.com'
            || $host === 'www.zoom.com';
    }

    private static function isWebexHost(string $host): bool
    {
        return $host === 'webex.com'
            || $host === 'www.webex.com'
            || str_ends_with($host, '.webex.com');
    }

    /**
     * @return array{kind: string, provider: string, play_url: string|null, message: string|null, is_live: bool, embeddable: bool}
     */
    private static function teams(string $url, string $host, string $path, string $urlLower): array
    {
        // Official Stream / embed video players — iframe-capable when public.
        if (
            str_contains($path, '/embed/')
            || str_contains($path, '/video/') && str_contains($host, 'stream')
            || str_contains($urlLower, '/embed/video/')
        ) {
            return [
                'kind' => self::KIND_TEAMS,
                'provider' => 'Microsoft Teams / Stream',
                'play_url' => $url,
                'message' => null,
                'is_live' => true,
                'embeddable' => true,
            ];
        }

        // Town halls / live events sometimes expose a public viewer path.
        if (
            str_contains($path, '/l/meetup-join/') === false
            && (
                str_contains($path, '/live')
                || str_contains($host, 'events.teams')
                || str_contains($path, '/webinar')
            )
            && str_contains($path, '/embed')
        ) {
            return [
                'kind' => self::KIND_TEAMS,
                'provider' => 'Microsoft Teams',
                'play_url' => $url,
                'message' => null,
                'is_live' => true,
                'embeddable' => true,
            ];
        }

        return [
            'kind' => self::KIND_TEAMS,
            'provider' => 'Microsoft Teams',
            'play_url' => null,
            'message' => 'This Teams link requires the Microsoft Teams experience and cannot be embedded directly in RMSignage. Use a public Stream/embed URL, an HLS stream, or an official embeddable player link.',
            'is_live' => true,
            'embeddable' => false,
        ];
    }

    /**
     * @return array{kind: string, provider: string, play_url: string|null, message: string|null, is_live: bool, embeddable: bool}
     */
    private static function zoom(string $url, string $host, string $path, string $urlLower): array
    {
        // Rare official embed / webcast player URLs.
        if (
            str_contains($path, '/embed')
            || str_contains($urlLower, 'embed=true')
            || (str_contains($host, 'webcast') && str_contains($path, '/viewer'))
        ) {
            return [
                'kind' => self::KIND_ZOOM,
                'provider' => 'Zoom',
                'play_url' => $url,
                'message' => null,
                'is_live' => true,
                'embeddable' => true,
            ];
        }

        return [
            'kind' => self::KIND_ZOOM,
            'provider' => 'Zoom',
            'play_url' => null,
            'message' => 'This Zoom link requires the Zoom client or an authenticated participant session and cannot be embedded directly in RMSignage. Use an official webcast/embed viewer URL, HLS, or another supported live stream.',
            'is_live' => true,
            'embeddable' => false,
        ];
    }

    /**
     * @return array{kind: string, provider: string, play_url: string|null, message: string|null, is_live: bool, embeddable: bool}
     */
    private static function webex(string $url, string $host, string $path, string $urlLower): array
    {
        if (
            str_contains($path, '/embed')
            || str_contains($urlLower, 'embed=true')
            || str_contains($path, '/widget')
        ) {
            return [
                'kind' => self::KIND_WEBEX,
                'provider' => 'Webex',
                'play_url' => $url,
                'message' => null,
                'is_live' => true,
                'embeddable' => true,
            ];
        }

        return [
            'kind' => self::KIND_WEBEX,
            'provider' => 'Webex',
            'play_url' => null,
            'message' => 'This Webex link requires the Webex experience and cannot be embedded directly in RMSignage. Use an official embed/widget URL, HLS, or another supported live stream.',
            'is_live' => true,
            'embeddable' => false,
        ];
    }
}
