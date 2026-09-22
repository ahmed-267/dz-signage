<?php

namespace App\Support\Widgets\Weather;

interface WeatherProvider
{
    /**
     * @return array{lat: float, lon: float, name: string}|null
     */
    public function geocode(string $location): ?array;

    /**
     * @param  'c'|'f'  $units
     * @return array{
     *     temp: float|int|null,
     *     condition: string|null,
     *     high: float|int|null,
     *     low: float|int|null
     * }
     */
    public function forecast(float $lat, float $lon, string $units): array;
}
