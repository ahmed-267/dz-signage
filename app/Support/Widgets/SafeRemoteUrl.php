<?php

namespace App\Support\Widgets;

use Illuminate\Validation\ValidationException;

/**
 * SSRF-safe validation for outbound RSS / ICS feed URLs.
 */
final class SafeRemoteUrl
{
    /**
     * @throws ValidationException
     */
    public static function assertSafe(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'url' => 'A feed URL is required.',
            ]);
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'url' => 'The feed URL is invalid.',
            ]);
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            throw ValidationException::withMessages([
                'url' => 'The feed URL is invalid.',
            ]);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['https', 'http'], true)) {
            throw ValidationException::withMessages([
                'url' => 'Only HTTP and HTTPS feed URLs are allowed.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw ValidationException::withMessages([
                'url' => 'The feed URL must include a host.',
            ]);
        }

        if (self::isBlockedHostname($host)) {
            throw ValidationException::withMessages([
                'url' => 'The feed URL host is not allowed.',
            ]);
        }

        if (self::isBlockedIpLiteral($host) || self::hostResolvesToPrivate($host)) {
            throw ValidationException::withMessages([
                'url' => 'The feed URL must not target a private or local network address.',
            ]);
        }

        $normalized = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }
        $normalized .= ($parts['path'] ?? '/');
        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }

    public static function isSafe(string $url): bool
    {
        try {
            self::assertSafe($url);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private static function isBlockedHostname(string $host): bool
    {
        $blocked = [
            'localhost',
            'metadata.google.internal',
            'metadata',
        ];

        if (in_array($host, $blocked, true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        return false;
    }

    private static function isBlockedIpLiteral(string $host): bool
    {
        $candidate = $host;
        if (str_starts_with($candidate, '[') && str_ends_with($candidate, ']')) {
            $candidate = substr($candidate, 1, -1);
        }

        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return self::isPrivateOrReservedIp($candidate);
    }

    private static function hostResolvesToPrivate(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            // Fall back to gethostbynamel for environments without dns_get_record AAAA support.
            $ipv4 = @gethostbynamel($host);
            if ($ipv4 === false || $ipv4 === []) {
                throw ValidationException::withMessages([
                    'url' => 'The feed URL host could not be resolved.',
                ]);
            }

            foreach ($ipv4 as $ip) {
                if (self::isPrivateOrReservedIp($ip)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && self::isPrivateOrReservedIp($ip)) {
                return true;
            }
        }

        return false;
    }

    private static function isPrivateOrReservedIp(string $ip): bool
    {
        $ip = strtolower(trim($ip));

        if (in_array($ip, ['127.0.0.1', '0.0.0.0', '::1', '::'], true)) {
            return true;
        }

        // Cloud metadata link-local.
        if ($ip === '169.254.169.254') {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }

            // 10.0.0.0/8
            if (($long & 0xFF000000) === 0x0A000000) {
                return true;
            }
            // 172.16.0.0/12
            if (($long & 0xFFF00000) === 0xAC100000) {
                return true;
            }
            // 192.168.0.0/16
            if (($long & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }
            // 169.254.0.0/16
            if (($long & 0xFFFF0000) === 0xA9FE0000) {
                return true;
            }
            // 127.0.0.0/8
            if (($long & 0xFF000000) === 0x7F000000) {
                return true;
            }
            // 0.0.0.0/8
            if (($long & 0xFF000000) === 0x00000000) {
                return true;
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // ::1 already handled. fc00::/7 unique local, fe80::/10 link-local.
            if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
                return true;
            }
            if (preg_match('/^fe[89ab]/', $ip) === 1) {
                return true;
            }
            if ($ip === '::' || str_starts_with($ip, '::ffff:127.') || str_starts_with($ip, '::ffff:10.')) {
                return true;
            }
            if (preg_match('/^::ffff:(172\.(1[6-9]|2\d|3[0-1])\.|192\.168\.|169\.254\.)/', $ip) === 1) {
                return true;
            }

            return false;
        }

        return true;
    }
}
