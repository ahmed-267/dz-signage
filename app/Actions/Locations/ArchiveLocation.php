<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ArchiveLocation
{
    public function handle(User $user, Location $location): Location
    {
        if ($location->isArchived()) {
            throw ValidationException::withMessages([
                'location' => 'This location is already archived.',
            ]);
        }

        $location->forceFill([
            'archived_at' => now(),
        ])->save();

        return $location->fresh() ?? $location;
    }
}
