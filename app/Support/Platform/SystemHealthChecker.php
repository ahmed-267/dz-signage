<?php

namespace App\Support\Platform;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SystemHealthChecker
{
    private const CACHE_SECONDS = 45;

    /**
     * @return array{
     *     checks: list<array{key: string, label: string, status: string, message: string}>,
     *     overall: string
     * }
     */
    public static function check(): array
    {
        return Cache::remember('platform:system_health', self::CACHE_SECONDS, function () {
            $checks = [
                self::appCheck(),
                self::databaseCheck(),
                self::redisCheck(),
                self::storageCheck(),
                self::schedulerCheck(),
            ];

            $statuses = array_column($checks, 'status');
            $overall = 'healthy';

            if (in_array('unavailable', $statuses, true)) {
                $overall = 'unavailable';
            } elseif (in_array('degraded', $statuses, true)) {
                $overall = 'degraded';
            }

            return [
                'checks' => $checks,
                'overall' => $overall,
            ];
        });
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private static function appCheck(): array
    {
        return [
            'key' => 'app',
            'label' => 'Application',
            'status' => 'healthy',
            'message' => 'Application process responding.',
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private static function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => 'healthy',
                'message' => 'Database connection OK.',
            ];
        } catch (Throwable $e) {
            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => 'unavailable',
                'message' => 'Database connection failed.',
            ];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private static function redisCheck(): array
    {
        try {
            $pong = Redis::connection()->ping();

            if ($pong === true || $pong === 'PONG' || $pong === '+PONG') {
                return [
                    'key' => 'redis',
                    'label' => 'Redis',
                    'status' => 'healthy',
                    'message' => 'Redis connection OK.',
                ];
            }

            return [
                'key' => 'redis',
                'label' => 'Redis',
                'status' => 'degraded',
                'message' => 'Redis responded unexpectedly.',
            ];
        } catch (Throwable) {
            return [
                'key' => 'redis',
                'label' => 'Redis',
                'status' => 'degraded',
                'message' => 'Redis unavailable or not configured.',
            ];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private static function storageCheck(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $probe = '.platform_health_probe_'.uniqid('', true);

            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);
            $disk->delete($probe);

            if (! $exists) {
                return [
                    'key' => 'storage',
                    'label' => 'Storage',
                    'status' => 'degraded',
                    'message' => 'Storage write probe failed.',
                ];
            }

            return [
                'key' => 'storage',
                'label' => 'Storage',
                'status' => 'healthy',
                'message' => 'Default storage disk OK.',
            ];
        } catch (Throwable) {
            return [
                'key' => 'storage',
                'label' => 'Storage',
                'status' => 'unavailable',
                'message' => 'Storage check failed.',
            ];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private static function schedulerCheck(): array
    {
        $heartbeat = Cache::get('platform:scheduler_heartbeat_at');

        if (is_string($heartbeat) && $heartbeat !== '') {
            try {
                $at = Carbon::parse($heartbeat);
                if ($at->greaterThan(now()->subMinutes(5))) {
                    return [
                        'key' => 'scheduler',
                        'label' => 'Scheduler',
                        'status' => 'healthy',
                        'message' => 'Scheduler heartbeat received within 5 minutes.',
                    ];
                }

                return [
                    'key' => 'scheduler',
                    'label' => 'Scheduler',
                    'status' => 'degraded',
                    'message' => 'Scheduler heartbeat is stale. Confirm cron runs `php artisan schedule:run`.',
                ];
            } catch (Throwable) {
                // fall through
            }
        }

        $eventsConfigured = false;

        try {
            $eventsConfigured = count(app()->make(Schedule::class)->events()) > 0;
        } catch (Throwable) {
            $eventsConfigured = file_exists(base_path('routes/console.php'));
        }

        return [
            'key' => 'scheduler',
            'label' => 'Scheduler',
            'status' => $eventsConfigured ? 'degraded' : 'degraded',
            'message' => $eventsConfigured
                ? 'Scheduled tasks are configured, but no recent heartbeat yet.'
                : 'No scheduled tasks detected.',
        ];
    }
}
