<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Lightweight production probes. Do not expose diagnostics here —
 * detailed checks belong on /admin/system-health.
 */
class HealthController extends Controller
{
    /** Liveness — process is up. */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok'], 200);
    }

    /** Readiness — required dependencies respond. */
    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            // Redis is required for sessions/cache in production configs,
            // but local/test may use array drivers — only fail when redis is configured.
            if (config('cache.default') === 'redis' || config('session.driver') === 'redis') {
                return response()->json(['status' => 'unavailable'], 503);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
