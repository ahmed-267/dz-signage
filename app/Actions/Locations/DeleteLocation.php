<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteLocation
{
    public function handle(User $user, Location $location): void
    {
        if ($location->screens()->exists()) {
            throw ValidationException::withMessages([
                'location' => 'This location still has screens. Archive it instead, or move the screens first.',
            ]);
        }

        $location->managers()->detach();
        $location->delete();
    }
}
