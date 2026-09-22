<?php

namespace App\Actions\ScreenDesigns;

use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\LayoutSchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveScreenDesignDraft
{
    /**
     * @param  array{
     *     schema?: array<string, mixed>|null,
     *     name?: string|null
     * }  $data
     */
    public function handle(User $user, ScreenDesign $design, array $data): ScreenDesign
    {
        return DB::transaction(function () use ($user, $design, $data) {
            if (array_key_exists('name', $data) && $data['name'] !== null) {
                $design->forceFill([
                    'name' => trim((string) $data['name']),
                    'updated_by' => $user->id,
                ])->save();
            } else {
                $design->forceFill(['updated_by' => $user->id])->save();
            }

            if (array_key_exists('schema', $data) && is_array($data['schema'])) {
                $schema = LayoutSchemaValidator::validate(
                    LayoutSchemaNormalizer::normalize($data['schema']),
                );
                $this->assertMediaBelongsToWorkspace($design, $schema);
                $this->saveSchemaVersion($user, $design, $schema);
            }

            return $design->fresh(['versions']) ?? $design;
        });
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function assertMediaBelongsToWorkspace(ScreenDesign $design, array $schema): void
    {
        $ids = ScreenDesign::mediaIdsFromSchema($schema);
        if ($ids === []) {
            return;
        }

        $count = MediaAsset::query()
            ->where('workspace_id', $design->workspace_id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'schema' => 'One or more media references are invalid for this workspace.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function saveSchemaVersion(User $user, ScreenDesign $design, array $schema): ScreenDesignVersion
    {
        $latest = $design->versions()
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        if ($latest === null) {
            throw ValidationException::withMessages([
                'schema' => 'Screen design has no versions to update.',
            ]);
        }

        if (! $latest->isPublished()) {
            $latest->forceFill([
                'schema' => $schema,
                'created_by' => $user->id,
            ])->save();

            return $latest;
        }

        return ScreenDesignVersion::query()->create([
            'screen_design_id' => $design->id,
            'version_number' => $latest->version_number + 1,
            'schema' => $schema,
            'created_by' => $user->id,
            'published_at' => null,
        ]);
    }
}
