<?php

namespace App\Support\Widgets\Weather;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class WeatherService
{
    public function __construct(
        private readonly WeatherProvider $provider,
    ) {}

    /**
     * @return array{
     *     locationLabel: string,
     *     temp: float|int|null,
     *     units: string,
     *     condition: string|null,
     *     high: float|int|null,
     *     low: float|int|null,
     *     fetchedAt: string
     * }|null
     */
    public function fetch(string $location, string $units = 'c'): ?array
    {
        $location = trim($location);
        $units = strtolower($units) === 'f' ? 'f' : 'c';

        if ($location === '') {
            return null;
        }

        $cacheKey = $this->cacheKey($location, $units);
        $ttl = max(60, (int) config('widgets.weather.cache_ttl_seconds', 900));

        $cached = $this->normalizeCached(Cache::get($cacheKey));
        if ($cached !== null) {
            return $cached;
        }

        try {
            $payload = $this->resolve($location, $units);
            if ($payload === null) {
                return $this->normalizeCached(Cache::get($cacheKey.':last'));
            }

            Cache::put($cacheKey, $payload, $ttl);
            Cache::forever($cacheKey.':last', $payload);

            return $payload;
        } catch (Throwable) {
            return $this->normalizeCached(Cache::get($cacheKey.':last'));
        }
    }

    /**
     * @return array{
     *     locationLabel: string,
     *     temp: float|int|null,
     *     units: string,
     *     condition: string|null,
     *     high: float|int|null,
     *     low: float|int|null,
     *     fetchedAt: string
     * }|null
     */
    private function resolve(string $location, string $units): ?array
    {
        $geocodeTtl = max(60, (int) config('widgets.weather.geocode_cache_ttl_seconds', 86400));
        $geocodeKey = 'widget:weather:geocode:'.md5(mb_strtolower($location));

        $geo = Cache::get($geocodeKey);
        if (! is_array($geo) || ! array_key_exists('lat', $geo) || ! array_key_exists('lon', $geo)) {
            $geo = $this->provider->geocode($location);
            if ($geo !== null) {
                Cache::put($geocodeKey, $geo, $geocodeTtl);
            }
        } else {
            $geo = [
                'lat' => (float) $geo['lat'],
                'lon' => (float) $geo['lon'],
                'name' => isset($geo['name']) && is_string($geo['name']) ? $geo['name'] : $location,
            ];
        }

        if ($geo === null) {
            return null;
        }

        $unitEnum = $units === 'f' ? 'f' : 'c';
        $forecast = $this->provider->forecast($geo['lat'], $geo['lon'], $unitEnum);
        if ($forecast['temp'] === null && $forecast['condition'] === null) {
            return null;
        }

        return [
            'locationLabel' => $geo['name'] !== '' ? $geo['name'] : $location,
            'temp' => $forecast['temp'],
            'units' => $unitEnum,
            'condition' => $forecast['condition'],
            'high' => $forecast['high'],
            'low' => $forecast['low'],
            'fetchedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     locationLabel: string,
     *     temp: float|int|null,
     *     units: string,
     *     condition: string|null,
     *     high: float|int|null,
     *     low: float|int|null,
     *     fetchedAt: string
     * }|null
     */
    private function normalizeCached(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! isset($value['locationLabel'], $value['units'], $value['fetchedAt'])) {
            return null;
        }

        return [
            'locationLabel' => (string) $value['locationLabel'],
            'temp' => is_numeric($value['temp'] ?? null) ? $value['temp'] + 0 : null,
            'units' => (string) $value['units'],
            'condition' => isset($value['condition']) && is_string($value['condition']) ? $value['condition'] : null,
            'high' => is_numeric($value['high'] ?? null) ? $value['high'] + 0 : null,
            'low' => is_numeric($value['low'] ?? null) ? $value['low'] + 0 : null,
            'fetchedAt' => (string) $value['fetchedAt'],
        ];
    }

    private function cacheKey(string $location, string $units): string
    {
        return 'widget:weather:'.md5(mb_strtolower($location).'|'.$units);
    }
}
