<?php

namespace App\Support\Widgets\Calendar;

use App\Support\Widgets\SafeRemoteUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

final class IcsCalendarService
{
    /**
     * @return array{
     *     title: string|null,
     *     events: list<array{summary: string, startsAt: string|null, endsAt: string|null, location: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    public function fetch(string $feedUrl, int $maxEvents = 5, ?string $timezone = null): ?array
    {
        try {
            $feedUrl = SafeRemoteUrl::assertSafe($feedUrl);
        } catch (ValidationException) {
            return null;
        }

        $maxEvents = max(1, min(20, $maxEvents));
        $timezone = $timezone !== null && trim($timezone) !== '' ? trim($timezone) : 'UTC';
        $cacheKey = 'widget:calendar:'.md5($feedUrl.'|'.$maxEvents.'|'.$timezone);
        $ttl = max(60, (int) config('widgets.calendar.cache_ttl_seconds', 600));

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($feedUrl, $maxEvents, $timezone, $cacheKey) {
                $payload = $this->downloadAndParse($feedUrl, $maxEvents, $timezone);
                if ($payload === null) {
                    throw new \RuntimeException('calendar_fetch_failed');
                }

                Cache::forever($cacheKey.':last', $payload);

                return $payload;
            });
        } catch (Throwable) {
            return $this->normalizeCached(Cache::get($cacheKey.':last'));
        }
    }

    /**
     * @return array{
     *     title: string|null,
     *     events: list<array{summary: string, startsAt: string|null, endsAt: string|null, location: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    private function normalizeCached(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['fetchedAt']) || ! is_array($value['events'] ?? null)) {
            return null;
        }

        $events = [];
        foreach ($value['events'] as $event) {
            if (! is_array($event) || ! isset($event['summary']) || ! is_string($event['summary'])) {
                continue;
            }
            $events[] = [
                'summary' => $event['summary'],
                'startsAt' => isset($event['startsAt']) && is_string($event['startsAt']) ? $event['startsAt'] : null,
                'endsAt' => isset($event['endsAt']) && is_string($event['endsAt']) ? $event['endsAt'] : null,
                'location' => isset($event['location']) && is_string($event['location']) ? $event['location'] : null,
            ];
        }

        return [
            'title' => isset($value['title']) && is_string($value['title']) ? $value['title'] : null,
            'events' => $events,
            'fetchedAt' => (string) $value['fetchedAt'],
        ];
    }

    /**
     * @return array{
     *     title: string|null,
     *     events: list<array{summary: string, startsAt: string|null, endsAt: string|null, location: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    private function downloadAndParse(string $feedUrl, int $maxEvents, string $timezone): ?array
    {
        $timeout = (int) config('widgets.calendar.http_timeout', 8);
        $maxBytes = (int) config('widgets.calendar.max_bytes', 524288);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Accept' => 'text/calendar, text/plain, */*'])
                ->get($feedUrl);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentLength = $response->header('Content-Length');
        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        return $this->parse($body, $maxEvents, $timezone);
    }

    /**
     * @return array{
     *     title: string|null,
     *     events: list<array{summary: string, startsAt: string|null, endsAt: string|null, location: string|null}>,
     *     fetchedAt: string
     * }
     */
    private function parse(string $body, int $maxEvents, string $timezone): array
    {
        $unfolded = preg_replace("/\r\n[ \t]/", '', str_replace("\r\n", "\n", $body)) ?? $body;
        $lines = explode("\n", $unfolded);

        $calendarName = null;
        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strcasecmp($line, 'BEGIN:VEVENT') === 0) {
                $current = [];

                continue;
            }

            if (strcasecmp($line, 'END:VEVENT') === 0) {
                if (is_array($current)) {
                    $events[] = $current;
                }
                $current = null;

                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$rawKey, $value] = $parts;
            $keyParts = explode(';', $rawKey);
            $name = strtoupper($keyParts[0]);
            $params = [];
            foreach (array_slice($keyParts, 1) as $param) {
                $pair = explode('=', $param, 2);
                if (count($pair) === 2) {
                    $params[strtoupper($pair[0])] = $pair[1];
                }
            }

            if ($current === null) {
                if ($name === 'X-WR-CALNAME') {
                    $calendarName = $this->unescape($value);
                }

                continue;
            }

            if ($name === 'SUMMARY') {
                $current['summary'] = $this->unescape($value);
            } elseif ($name === 'LOCATION') {
                $current['location'] = $this->unescape($value);
            } elseif ($name === 'DTSTART') {
                $current['startsAt'] = $this->parseIcsDate($value, $params, $timezone);
            } elseif ($name === 'DTEND') {
                $current['endsAt'] = $this->parseIcsDate($value, $params, $timezone);
            }
        }

        $now = now();
        $upcoming = [];
        foreach ($events as $event) {
            $summary = trim((string) ($event['summary'] ?? ''));
            if ($summary === '') {
                continue;
            }

            $startsAt = $event['startsAt'] ?? null;
            $endsAt = $event['endsAt'] ?? null;

            $end = null;
            if (is_string($endsAt)) {
                try {
                    $end = Carbon::parse($endsAt);
                } catch (Throwable) {
                    $end = null;
                }
            }

            $start = null;
            if (is_string($startsAt)) {
                try {
                    $start = Carbon::parse($startsAt);
                } catch (Throwable) {
                    $start = null;
                }
            }

            if ($end !== null && $end->lt($now)) {
                continue;
            }
            if ($end === null && $start !== null && $start->lt($now->copy()->subDay())) {
                continue;
            }

            $upcoming[] = [
                'summary' => $summary,
                'startsAt' => is_string($startsAt) ? $startsAt : null,
                'endsAt' => is_string($endsAt) ? $endsAt : null,
                'location' => array_key_exists('location', $event)
                    ? (string) $event['location']
                    : null,
                '_sort' => $start !== null ? $start->timestamp : PHP_INT_MAX,
            ];
        }

        usort($upcoming, fn (array $a, array $b): int => $a['_sort'] <=> $b['_sort']);
        $upcoming = array_slice($upcoming, 0, $maxEvents);

        return [
            'title' => $calendarName,
            'events' => array_map(static function (array $event): array {
                unset($event['_sort']);

                return $event;
            }, $upcoming),
            'fetchedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function parseIcsDate(string $value, array $params, string $fallbackTimezone): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $tzid = $params['TZID'] ?? $fallbackTimezone;

        try {
            if (isset($params['VALUE']) && strtoupper($params['VALUE']) === 'DATE') {
                return Carbon::createFromFormat('Ymd', $value, $tzid)?->startOfDay()->toIso8601String();
            }

            if (str_ends_with($value, 'Z')) {
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC')?->toIso8601String();
            }

            if (preg_match('/^\d{8}T\d{6}$/', $value) === 1) {
                return Carbon::createFromFormat('Ymd\THis', $value, $tzid)?->toIso8601String();
            }

            if (preg_match('/^\d{8}$/', $value) === 1) {
                return Carbon::createFromFormat('Ymd', $value, $tzid)?->startOfDay()->toIso8601String();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function unescape(string $value): string
    {
        $value = str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);

        return trim($value);
    }
}
