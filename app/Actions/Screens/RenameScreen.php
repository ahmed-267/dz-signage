<?php

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Models\User;

class RenameScreen
{
    public function handle(User $user, Screen $screen, string $name): Screen
    {
        $screen->forceFill([
            'name' => trim($name),
        ])->save();

        return $screen->fresh() ?? $screen;
    }
}
