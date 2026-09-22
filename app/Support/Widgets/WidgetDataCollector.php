<?php

namespace App\Support\Widgets;

use App\Enums\PlatformErrorCategory;
use App\Models\PlatformError;
use App\Support\Widgets\Calendar\IcsCalendarService;
use App\Support\Widgets\Rss\RssFeedService;
use App\Support\Widgets\Weather\WeatherService;
use Throwable;

final class WidgetDataCollector
{
    public function __construct(
        private readonly WeatherService $weather,
        private readonly RssFeedService $rss,
        private readonly IcsCalendarService $calendar,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed|null>
     */
    public function collectFromSchema(array $schema): array
    {
        $data = [];

        foreach ($this->extractWidgetConfigs($schema) as $entry) {
            $type = $entry['widgetType'];
            $config = $entry['config'];
            $key = $this->dataKey($type, $config);
            if ($key === null || array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = $this->fetchPayload($type, $config);
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $schemas
     * @return array<string, mixed|null>
     */
    public function collectFromSchemas(array $schemas): array
    {
        $merged = [];
        foreach ($schemas as $schema) {
            foreach ($this->collectFromSchema($schema) as $key => $payload) {
                $merged[$key] = $payload;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array{widgetType: WidgetType, config: array<string, mixed>}>
     */
    public function extractWidgetConfigs(array $schema): array
    {
        $out = [];

        foreach ($schema['elements'] ?? [] as $element) {
            if (! is_array($element) || ($element['type'] ?? null) !== 'widget') {
                continue;
            }

            $props = is_array($element['props'] ?? null) ? $element['props'] : [];
            $type = WidgetConfigValidator::resolveType($props);
            if ($type === null) {
                continue;
            }

            $config = is_array($props['config'] ?? null) ? $props['config'] : [];
            $out[] = [
                'widgetType' => $type,
                'config' => $config,
            ];
        }

        return $out;
    }

    /**
     * Extract LayoutSchema arrays from player / offline content payloads.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    public function schemasFromPayloads(array $payloads): array
    {
        $schemas = [];

        foreach ($payloads as $payload) {
            if (isset($payload['schema']) && is_array($payload['schema'])) {
                $schemas[] = $payload['schema'];
            }

            foreach ($payload['items'] ?? [] as $item) {
                if (is_array($item) && isset($item['schema']) && is_array($item['schema'])) {
                    $schemas[] = $item['schema'];
                }
            }
        }

        return $schemas;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function dataKey(WidgetType $type, array $config): ?string
    {
        return match ($type) {
            WidgetType::Weather => 'weather:'.md5(
                mb_strtolower(trim((string) ($config['location'] ?? ''))).'|'.(strtolower((string) ($config['units'] ?? 'c')) === 'f' ? 'f' : 'c')
            ),
            WidgetType::News => 'news:'.md5(trim((string) ($config['feedUrl'] ?? ''))),
            WidgetType::Calendar => 'calendar:'.md5(trim((string) ($config['feedUrl'] ?? ''))),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function fetchPayload(WidgetType $type, array $config): mixed
    {
        try {
            return match ($type) {
                WidgetType::Weather => $this->weather->fetch(
                    (string) ($config['location'] ?? ''),
                    (string) ($config['units'] ?? 'c'),
                ),
                WidgetType::News => $this->rss->fetch(
                    (string) ($config['feedUrl'] ?? ''),
                    (int) ($config['maxItems'] ?? 5),
                ),
                WidgetType::Calendar => $this->calendar->fetch(
                    (string) ($config['feedUrl'] ?? ''),
                    (int) ($config['maxEvents'] ?? 5),
                    isset($config['timezone']) ? (string) $config['timezone'] : null,
                ),
                default => null,
            };
        } catch (Throwable $e) {
            PlatformError::record(
                PlatformErrorCategory::WidgetData,
                'Widget data fetch failed: '.$type->value,
                null,
                null,
                null,
                ['widget_type' => $type->value, 'error' => mb_substr($e->getMessage(), 0, 200)],
            );

            return null;
        }
    }
}
