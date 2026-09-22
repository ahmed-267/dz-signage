<?php

namespace App\Actions\Screens;

use App\Enums\ScreenOperationalStatus;
use App\Models\Screen;
use App\Models\User;

class SetScreenOperationalStatus
{
    public function handle(User $user, Screen $screen, ScreenOperationalStatus $status): Screen
    {
        $screen->forceFill([
            'operational_status' => $status,
        ])->save();

        return $screen->fresh() ?? $screen;
    }
}
