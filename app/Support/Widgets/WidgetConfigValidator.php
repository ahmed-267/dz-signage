<?php

namespace App\Support\Widgets;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Throwable;

final class WidgetConfigValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validateSchema(array $schema): array
    {
        $elements = $schema['elements'] ?? [];
        if (! is_array($elements)) {
            return $schema;
        }

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            if (($element['type'] ?? null) !== 'widget') {
                continue;
            }

            $props = is_array($element['props'] ?? null) ? $element['props'] : [];
            self::validateElementProps($props, $index);

            // Persist normalized embed play URLs + kind so Editor/Player share one source.
            $type = self::resolveType($props);
            if ($type === WidgetType::Embed) {
                $config = is_array($props['config'] ?? null) ? $props['config'] : [];
                $url = trim((string) ($config['url'] ?? ''));
                if ($url !== '') {
                    $resolved = EmbedUrlValidator::resolve($url, [
                        'autoplay' => (bool) ($config['autoplay'] ?? true),
                        'muted' => (bool) ($config['muted'] ?? true),
                        'loop' => (bool) ($config['loop'] ?? true),
                        'controls' => (bool) ($config['controls'] ?? true),
                        'volume' => $config['volume'] ?? 70,
                    ]);
                    if (
                        $resolved['kind'] === EmbedUrlValidator::KIND_BLOCKED
                        || $resolved['kind'] === EmbedUrlValidator::KIND_DRM
                        || $resolved['kind'] === EmbedUrlValidator::KIND_UNSUPPORTED
                        || $resolved['play_url'] === null
                    ) {
                        throw ValidationException::withMessages([
                            "schema.elements.$index.props.config.url" => $resolved['message']
                                ?? 'This URL cannot be embedded.',
                        ]);
                    }
                    $config['url'] = EmbedUrlValidator::normalize($url);
                    $config['kind'] = $resolved['kind'];
                    $config['source_url'] = $resolved['source_url'];
                    $config['autoplay'] = $resolved['autoplay'];
                    $config['muted'] = $resolved['muted'];
                    $config['loop'] = $resolved['loop'];
                    $config['controls'] = $resolved['controls'];
                    $config['volume'] = $resolved['volume'];
                    $props['config'] = $config;
                    $element['props'] = $props;
                    $elements[$index] = $element;
                }
            }
        }

        $schema['elements'] = $elements;

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $props
     *
     * @throws ValidationException
     */
    public static function validateElementProps(array $props, int $index = 0): void
    {
        $prefix = "elements.$index";
        $type = self::resolveType($props);
        if ($type === null) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.widgetType" => 'A valid widget type is required.',
            ]);
        }

        $config = is_array($props['config'] ?? null) ? $props['config'] : [];

        match ($type) {
            WidgetType::Clock => self::validateClock($config, $prefix),
            WidgetType::Countdown => self::validateCountdown($config, $prefix),
            WidgetType::Weather => self::validateWeather($config, $prefix),
            WidgetType::News => self::validateNews($config, $prefix),
            WidgetType::Calendar => self::validateCalendar($config, $prefix),
            WidgetType::Alert => self::validateAlert($config, $prefix),
            WidgetType::InfoCard => self::validateInfoCard($config, $prefix),
            WidgetType::Embed => self::validateEmbed($config, $prefix),
        };
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public static function resolveType(array $props): ?WidgetType
    {
        if (isset($props['widgetType']) && is_string($props['widgetType'])) {
            $normalized = strtolower(trim($props['widgetType']));
            $normalized = str_replace('-', '_', $normalized);
            $type = WidgetType::tryFrom($normalized);
            if ($type !== null) {
                return $type;
            }
        }

        if (isset($props['widgetName']) && is_string($props['widgetName'])) {
            return WidgetType::tryFromLegacyName($props['widgetName']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateClock(array $config, string $prefix): void
    {
        self::assertTimezone($config['timezone'] ?? 'UTC', "$prefix.props.config.timezone");

        foreach (['showSeconds', 'showDate', 'showWeekday'] as $flag) {
            if (array_key_exists($flag, $config) && ! is_bool($config[$flag])) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.$flag" => 'Must be a boolean.',
                ]);
            }
        }

        if (array_key_exists('hourFormat', $config)) {
            $format = (string) $config['hourFormat'];
            if (! in_array($format, ['12', '24'], true)) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.hourFormat" => 'Hour format must be 12 or 24.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateCountdown(array $config, string $prefix): void
    {
        self::assertTimezone($config['timezone'] ?? 'UTC', "$prefix.props.config.timezone");

        $hasTargetAt = isset($config['targetAt']) && is_string($config['targetAt']) && trim($config['targetAt']) !== '';
        $hasDateTime = isset($config['date'], $config['time'])
            && is_string($config['date'])
            && is_string($config['time']);

        if (! $hasTargetAt && ! $hasDateTime) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.targetAt" => 'A countdown target datetime is required.',
            ]);
        }

        if ($hasTargetAt) {
            self::assertIsoDateTime((string) $config['targetAt'], "$prefix.props.config.targetAt");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateWeather(array $config, string $prefix): void
    {
        $location = trim((string) ($config['location'] ?? ''));
        if ($location === '') {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.location" => 'A weather location is required.',
            ]);
        }

        if (mb_strlen($location) > 200) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.location" => 'Weather location is too long.',
            ]);
        }

        $units = strtolower((string) ($config['units'] ?? 'c'));
        if (! in_array($units, ['c', 'f'], true)) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.units" => 'Units must be c or f.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateNews(array $config, string $prefix): void
    {
        $feedUrl = trim((string) ($config['feedUrl'] ?? ''));
        // Empty allowed in drafts; when set, must be a safe public URL.
        if ($feedUrl !== '') {
            try {
                SafeRemoteUrl::assertSafe($feedUrl);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.feedUrl" => $e->errors()['url'] ?? ['The feed URL is not allowed.'],
                ]);
            }
        }

        $maxItems = (int) ($config['maxItems'] ?? 5);
        if ($maxItems < 1 || $maxItems > 20) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.maxItems" => 'maxItems must be between 1 and 20.',
            ]);
        }

        $rotation = $config['rotationSeconds'] ?? $config['rotateSeconds'] ?? 8;
        $rotation = (int) $rotation;
        if ($rotation < 3 || $rotation > 120) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.rotationSeconds" => 'Rotation must be between 3 and 120 seconds.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateCalendar(array $config, string $prefix): void
    {
        $feedUrl = trim((string) ($config['feedUrl'] ?? ''));
        // Empty allowed in drafts; when set, must be a safe public URL.
        if ($feedUrl !== '') {
            try {
                SafeRemoteUrl::assertSafe($feedUrl);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.feedUrl" => $e->errors()['url'] ?? ['The feed URL is not allowed.'],
                ]);
            }
        }

        $maxEvents = (int) ($config['maxEvents'] ?? 5);
        if ($maxEvents < 1 || $maxEvents > 20) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.maxEvents" => 'maxEvents must be between 1 and 20.',
            ]);
        }

        if (array_key_exists('timezone', $config)) {
            self::assertTimezone((string) $config['timezone'], "$prefix.props.config.timezone");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateAlert(array $config, string $prefix): void
    {
        foreach (['title', 'message'] as $field) {
            if (array_key_exists($field, $config) && ! is_string($config[$field])) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.$field" => 'Must be a string.',
                ]);
            }
            if (isset($config[$field]) && mb_strlen((string) $config[$field]) > 2000) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.$field" => 'Is too long.',
                ]);
            }
        }

        if (array_key_exists('severity', $config)) {
            $severity = strtolower((string) $config['severity']);
            if (! in_array($severity, ['info', 'notice', 'warning', 'important'], true)) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.severity" => 'Severity must be info, notice, warning, or important.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateInfoCard(array $config, string $prefix): void
    {
        foreach (['heading', 'body', 'subheading', 'value', 'footer'] as $field) {
            if (array_key_exists($field, $config) && $config[$field] !== null && ! is_string($config[$field])) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.$field" => 'Must be a string.',
                ]);
            }
        }

        if (array_key_exists('mediaAssetId', $config) && $config['mediaAssetId'] !== null) {
            if (! is_int($config['mediaAssetId']) && ! ctype_digit((string) $config['mediaAssetId'])) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.mediaAssetId" => 'mediaAssetId must be an integer.',
                ]);
            }
            if ((int) $config['mediaAssetId'] < 1) {
                throw ValidationException::withMessages([
                    "schema.$prefix.props.config.mediaAssetId" => 'mediaAssetId must be a positive integer.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validateEmbed(array $config, string $prefix): void
    {
        $url = trim((string) ($config['url'] ?? ''));
        // Empty allowed in drafts; when set, must be a supported HTTPS embed.
        if ($url === '') {
            return;
        }

        try {
            EmbedUrlValidator::normalize($url);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                "schema.$prefix.props.config.url" => $e->errors()['url'] ?? ['The embed URL is not allowed.'],
            ]);
        }
    }

    private static function assertTimezone(string $timezone, string $key): void
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            throw ValidationException::withMessages([
                "schema.$key" => 'A timezone is required.',
            ]);
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                "schema.$key" => 'Invalid IANA timezone.',
            ]);
        }
    }

    private static function assertIsoDateTime(string $value, string $key): void
    {
        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                "schema.$key" => 'Must be a valid ISO datetime.',
            ]);
        }
    }
}
