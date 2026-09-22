<?php

namespace App\Support\Platform;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\ProductBrand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Allowlisted platform settings stored in DB.
 * Runtime (ScreenPresence, widgets, player) still reads config unless callers use effective().
 */
final class PlatformSettingsStore
{
    /**
     * @var array<string, array{type: string, rules: list<string|object>, config_default?: mixed, config_path?: string}>
     */
    private const ALLOWLIST = [
        'platform_name' => [
            'type' => 'string',
            'rules' => ['required', 'string', 'max:100'],
            'config_default' => ProductBrand::NAME,
        ],
        'support_email' => [
            'type' => 'string',
            'rules' => ['required', 'email', 'max:255'],
            'config_default' => 'support@dz.local',
        ],
        'heartbeat_interval_seconds' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:15', 'max:300'],
            'config_path' => 'screens.heartbeat_interval_seconds',
        ],
        'online_threshold_seconds' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:30', 'max:600'],
            'config_path' => 'screens.online_threshold_seconds',
        ],
        'offline_horizon_hours' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:1', 'max:168'],
            'config_path' => 'player.offline_horizon_hours',
        ],
        'widget_weather_cache_ttl_seconds' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:1', 'max:86400'],
            'config_path' => 'widgets.weather.cache_ttl_seconds',
        ],
        'widget_rss_cache_ttl_seconds' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:1', 'max:86400'],
            'config_path' => 'widgets.rss.cache_ttl_seconds',
        ],
        'widget_calendar_cache_ttl_seconds' => [
            'type' => 'int',
            'rules' => ['required', 'integer', 'min:1', 'max:86400'],
            'config_path' => 'widgets.calendar.cache_ttl_seconds',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALLOWLIST);
    }

    public static function isAllowed(string $key): bool
    {
        return array_key_exists($key, self::ALLOWLIST);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! self::isAllowed($key)) {
            return $default;
        }

        $row = PlatformSetting::query()->where('key', $key)->first();

        if ($row === null || $row->value === null) {
            return $default;
        }

        return self::castValue($key, $row->value);
    }

    /**
     * DB override when present, otherwise config / hardcoded default.
     */
    public static function effective(string $key): mixed
    {
        if (! self::isAllowed($key)) {
            return null;
        }

        $stored = self::get($key);

        if ($stored !== null) {
            return $stored;
        }

        return self::configDefault($key);
    }

    public static function configDefault(string $key): mixed
    {
        $meta = self::ALLOWLIST[$key] ?? null;

        if ($meta === null) {
            return null;
        }

        if (isset($meta['config_path'])) {
            return config($meta['config_path']);
        }

        return $meta['config_default'];
    }

    /**
     * @throws ValidationException
     */
    public static function set(string $key, mixed $value, User $actor): PlatformSetting
    {
        if (! self::isAllowed($key)) {
            throw ValidationException::withMessages([
                'key' => 'Setting key is not allowlisted.',
            ]);
        }

        $meta = self::ALLOWLIST[$key];
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $meta['rules']],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated()['value'];
        $stored = is_bool($validated) || is_int($validated) || is_float($validated)
            ? (string) $validated
            : (string) $validated;

        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'updated_by' => $actor->id,
            ],
        );

        AuditLogger::record(
            $actor,
            'platform_setting.updated',
            'platform_setting',
            $setting->id,
            null,
            [
                'key' => $key,
                'value' => $stored,
            ],
        );

        return $setting;
    }

    /**
     * @return list<array{key: string, value: mixed, effective: mixed, source: string, label: string}>
     */
    public static function allForAdmin(): array
    {
        $rows = [];

        foreach (self::ALLOWLIST as $key => $meta) {
            $stored = self::get($key);
            $configDefault = self::configDefault($key);
            $effective = $stored ?? $configDefault;

            $rows[] = [
                'key' => $key,
                'value' => $stored,
                'effective' => $effective,
                'source' => $stored !== null ? 'database' : 'config',
                'label' => str_replace('_', ' ', ucfirst($key)),
                'type' => $meta['type'],
            ];
        }

        return $rows;
    }

    private static function castValue(string $key, string $value): mixed
    {
        $type = self::ALLOWLIST[$key]['type'] ?? 'string';

        if ($type === 'int') {
            return (int) $value;
        }

        return $value;
    }
}
