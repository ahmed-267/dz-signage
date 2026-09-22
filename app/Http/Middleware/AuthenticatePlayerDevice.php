<?php

namespace App\Http\Middleware;

use App\Models\ScreenDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlayerDevice
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        if ($token === null || $token === '') {
            abort(401, 'Device authentication required.');
        }

        $device = ScreenDevice::findByToken($token);
        if ($device === null || $device->isRevoked()) {
            abort(401, 'Invalid or revoked device token.');
        }

        $screen = $device->screen;
        if ($screen === null) {
            abort(401, 'Invalid or revoked device token.');
        }

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subSeconds(30))) {
            $device->touchLastSeen();
        }

        $request->attributes->set('player_device', $device);
        $request->attributes->set('player_screen', $screen);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $headerToken = $request->header('X-Device-Token');
        if (is_string($headerToken) && $headerToken !== '') {
            return $headerToken;
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $cookie = $request->cookie('dz_player_device_token');

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }
}
