<?php

namespace App\Actions\Locations;

use App\Enums\WorkspaceRole;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLocation
{
    /**
     * @param  array{
     *     name: string,
     *     address_line1?: string|null,
     *     address_line2?: string|null,
     *     city?: string|null,
     *     region?: string|null,
     *     postcode?: string|null,
     *     country?: string|null,
     *     timezone: string,
     *     notes?: string|null,
     *     manager_ids?: list<int>|null,
     * }  $data
     */
    public function handle(User $user, Location $location, array $data): Location
    {
        return DB::transaction(function () use ($user, $location, $data) {
            $location->forceFill([
                'name' => trim($data['name']),
                'address_line1' => $this->nullableString($data['address_line1'] ?? null),
                'address_line2' => $this->nullableString($data['address_line2'] ?? null),
                'city' => $this->nullableString($data['city'] ?? null),
                'region' => $this->nullableString($data['region'] ?? null),
                'postcode' => $this->nullableString($data['postcode'] ?? null),
                'country' => $this->nullableString($data['country'] ?? null) ?? 'UK',
                'timezone' => $data['timezone'],
                'notes' => $this->nullableString($data['notes'] ?? null),
            ])->save();

            if (array_key_exists('manager_ids', $data)) {
                $this->syncManagers($user, $location, $data['manager_ids']);
            }

            return $location->fresh(['managers']) ?? $location;
        });
    }

    /**
     * @param  list<int>|null  $requested
     */
    private function syncManagers(User $user, Location $location, ?array $requested): void
    {
        $workspace = $location->workspace;
        $role = $user->roleIn($workspace);

        if ($role !== WorkspaceRole::Owner && $role !== WorkspaceRole::Admin) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $requested ?? [])));

        if ($ids === []) {
            $location->managers()->sync([]);

            return;
        }

        $valid = $workspace->members()
            ->where('role', WorkspaceRole::LocationManager->value)
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $invalid = array_diff($ids, $valid);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'manager_ids' => 'Only Location Managers in this workspace can be assigned.',
            ]);
        }

        $location->managers()->sync($valid);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
