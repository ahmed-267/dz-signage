<?php

namespace App\Support\Locations;

use App\Enums\WorkspaceRole;
use App\Models\Location;
use App\Models\Screen;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Location Manager scoping for Locations and Screens.
 * Owner/Admin see the whole workspace; Location Managers only assigned sites.
 */
final class LocationAccess
{
    public static function isLocationManager(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace) === WorkspaceRole::LocationManager;
    }

    /**
     * @return list<int>
     */
    public static function managedLocationIds(User $user, Workspace $workspace): array
    {
        $ids = [];
        foreach (
            $user->managedLocations()
                ->where('locations.workspace_id', $workspace->id)
                ->whereNull('locations.archived_at')
                ->pluck('locations.id') as $id
        ) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public static function scopeLocationsForUser(Builder $query, User $user, Workspace $workspace): Builder
    {
        if (! self::isLocationManager($user, $workspace)) {
            return $query;
        }

        $ids = self::managedLocationIds($user, $workspace);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('locations.id', $ids);
    }

    /**
     * @param  Builder<Screen>  $query
     * @return Builder<Screen>
     */
    public static function scopeScreensForUser(Builder $query, User $user, Workspace $workspace): Builder
    {
        if (! self::isLocationManager($user, $workspace)) {
            return $query;
        }

        $ids = self::managedLocationIds($user, $workspace);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('location_id', $ids);
    }

    public static function canAccessLocation(User $user, Location $location): bool
    {
        $workspace = $user->currentWorkspace;

        if ($workspace === null
            || ! $user->belongsToWorkspace($workspace)
            || (int) $location->workspace_id !== (int) $workspace->id) {
            return false;
        }

        if (! self::isLocationManager($user, $workspace)) {
            return true;
        }

        return $location->managers()->where('users.id', $user->id)->exists();
    }

    public static function canAccessScreen(User $user, Screen $screen): bool
    {
        $workspace = $user->currentWorkspace;

        if ($workspace === null
            || ! $user->belongsToWorkspace($workspace)
            || (int) $screen->workspace_id !== (int) $workspace->id) {
            return false;
        }

        if (! self::isLocationManager($user, $workspace)) {
            return true;
        }

        if ($screen->location_id === null) {
            return false;
        }

        return in_array((int) $screen->location_id, self::managedLocationIds($user, $workspace), true);
    }

    public static function canAssignLocation(User $user, Workspace $workspace, ?int $locationId): bool
    {
        if ($locationId === null) {
            return ! self::isLocationManager($user, $workspace);
        }

        $location = Location::query()
            ->forWorkspace($workspace)
            ->active()
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            return false;
        }

        return self::canAccessLocation($user, $location);
    }
}
