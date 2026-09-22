<?php

namespace App\Support\Widgets\Weather;

use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenMeteoWeatherProvider implements WeatherProvider
{
    public function geocode(string $location): ?array
    {
        $timeout = (int) config('widgets.weather.http_timeout', 8);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get('https://geocoding-api.open-meteo.com/v1/search', [
                    'name' => $location,
                    'count' => 1,
                    'language' => 'en',
                    'format' => 'json',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('results');
        if (! is_array($results) || $results === []) {
            return null;
        }

        $first = $results[0];
        if (! is_array($first) || ! isset($first['latitude'], $first['longitude'])) {
            return null;
        }

        $parts = array_filter([
            $first['name'] ?? null,
            $first['admin1'] ?? null,
            $first['country'] ?? null,
        ], fn ($v) => is_string($v) && $v !== '');

        return [
            'lat' => (float) $first['latitude'],
            'lon' => (float) $first['longitude'],
            'name' => $parts !== [] ? implode(', ', $parts) : $location,
        ];
    }

    public function forecast(float $lat, float $lon, string $units): array
    {
        $timeout = (int) config('widgets.weather.http_timeout', 8);
        $temperatureUnit = $units === 'f' ? 'fahrenheit' : 'celsius';

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current' => 'temperature_2m,weather_code',
                    'daily' => 'temperature_2m_max,temperature_2m_min',
                    'temperature_unit' => $temperatureUnit,
                    'forecast_days' => 1,
                    'timezone' => 'auto',
                ]);
        } catch (Throwable) {
            return [
                'temp' => null,
                'condition' => null,
                'high' => null,
                'low' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'temp' => null,
                'condition' => null,
                'high' => null,
                'low' => null,
            ];
        }

        $current = $response->json('current');
        $daily = $response->json('daily');

        $temp = is_array($current) ? ($current['temperature_2m'] ?? null) : null;
        $code = is_array($current) ? ($current['weather_code'] ?? null) : null;
        $high = is_array($daily) && isset($daily['temperature_2m_max'][0])
            ? $daily['temperature_2m_max'][0]
            : null;
        $low = is_array($daily) && isset($daily['temperature_2m_min'][0])
            ? $daily['temperature_2m_min'][0]
            : null;

        return [
            'temp' => is_numeric($temp) ? round((float) $temp, 1) : null,
            'condition' => $this->conditionFromCode(is_numeric($code) ? (int) $code : null),
            'high' => is_numeric($high) ? round((float) $high, 1) : null,
            'low' => is_numeric($low) ? round((float) $low, 1) : null,
        ];
    }

    private function conditionFromCode(?int $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return match (true) {
            $code === 0 => 'Clear',
            $code <= 3 => 'Partly cloudy',
            $code <= 48 => 'Fog',
            $code <= 57 => 'Drizzle',
            $code <= 67 => 'Rain',
            $code <= 77 => 'Snow',
            $code <= 82 => 'Showers',
            $code <= 86 => 'Snow showers',
            $code <= 99 => 'Thunderstorm',
            default => 'Unknown',
        };
    }
}
