<?php

namespace App\Actions\Screens;

use App\Models\ScreenDevice;
use App\Models\User;
use Illuminate\Support\Str;

class RevokeScreenDevice
{
    public function handle(User $user, ScreenDevice $device): ScreenDevice
    {
        $device->forceFill([
            'revoked_at' => now(),
            // Rotate hash so any cached plaintext token can never match again.
            'device_token_hash' => ScreenDevice::hashToken(Str::random(64)),
        ])->save();

        return $device->fresh() ?? $device;
    }
}
