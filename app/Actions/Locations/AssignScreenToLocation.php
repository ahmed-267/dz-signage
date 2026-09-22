<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignScreenToLocation
{
    public function handle(User $user, Screen $screen, ?Location $location): Screen
    {
        if ($location !== null && (int) $location->workspace_id !== (int) $screen->workspace_id) {
            throw ValidationException::withMessages([
                'location_id' => 'That location does not belong to this workspace.',
            ]);
        }

        if ($location !== null && $location->isArchived()) {
            throw ValidationException::withMessages([
                'location_id' => 'Archived locations cannot receive screens.',
            ]);
        }

        $screen->forceFill([
            'location_id' => $location?->id,
        ])->save();

        return $screen->fresh() ?? $screen;
    }
}
