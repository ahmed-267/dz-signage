<?php

namespace App\Actions\Locations;

use App\Enums\WorkspaceRole;
use App\Models\Location;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLocation
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
    public function handle(User $user, Workspace $workspace, array $data): Location
    {
        return DB::transaction(function () use ($user, $workspace, $data) {
            $location = Location::query()->create([
                'workspace_id' => $workspace->id,
                'name' => trim($data['name']),
                'address_line1' => $this->nullableString($data['address_line1'] ?? null),
                'address_line2' => $this->nullableString($data['address_line2'] ?? null),
                'city' => $this->nullableString($data['city'] ?? null),
                'region' => $this->nullableString($data['region'] ?? null),
                'postcode' => $this->nullableString($data['postcode'] ?? null),
                'country' => $this->nullableString($data['country'] ?? null) ?? 'UK',
                'timezone' => $data['timezone'],
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => $user->id,
            ]);

            $managerIds = $this->resolveManagerIds($user, $workspace, $data['manager_ids'] ?? null);
            if ($managerIds !== []) {
                $location->managers()->sync($managerIds);
            }

            return $location->fresh(['managers']) ?? $location;
        });
    }

    /**
     * @param  list<int>|null  $requested
     * @return list<int>
     */
    private function resolveManagerIds(User $user, Workspace $workspace, ?array $requested): array
    {
        $role = $user->roleIn($workspace);

        if ($role === WorkspaceRole::LocationManager) {
            return [$user->id];
        }

        if ($role !== WorkspaceRole::Owner && $role !== WorkspaceRole::Admin) {
            return [];
        }

        if ($requested === null) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $requested)));

        if ($ids === []) {
            return [];
        }

        $valid = [];
        foreach (
            $workspace->members()
                ->where('role', WorkspaceRole::LocationManager->value)
                ->whereIn('user_id', $ids)
                ->pluck('user_id') as $id
        ) {
            $valid[] = (int) $id;
        }

        $invalid = array_diff($ids, $valid);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'manager_ids' => 'Only Location Managers in this workspace can be assigned.',
            ]);
        }

        return $valid;
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
