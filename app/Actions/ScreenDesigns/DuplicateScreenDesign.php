<?php

namespace App\Actions\ScreenDesigns;

use App\Enums\ScreenDesignStatus;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Support\ScreenDesigns\ScreenDesignContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DuplicateScreenDesign
{
    public function handle(User $user, ScreenDesign $design): ScreenDesign
    {
        $sourceVersion = $design->latestVersion() ?? $design->publishedVersion;
        $schema = $sourceVersion !== null
            ? $sourceVersion->schema
            : ['schemaVersion' => 1, 'canvas' => [], 'theme' => 'blank', 'elements' => []];

        if (! ScreenDesignContent::isMeaningful($schema)) {
            throw ValidationException::withMessages([
                'design' => 'Empty designs cannot be duplicated. Add content first.',
            ]);
        }

        return DB::transaction(function () use ($user, $design, $schema) {
            $copy = ScreenDesign::query()->create([
                'workspace_id' => $design->workspace_id,
                'name' => $design->name.' Copy',
                'orientation' => $design->orientation,
                'canvas_width' => $design->canvas_width,
                'canvas_height' => $design->canvas_height,
                'source_template_id' => $design->source_template_id,
                'source_template_version_id' => $design->source_template_version_id,
                'status' => ScreenDesignStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ScreenDesignVersion::query()->create([
                'screen_design_id' => $copy->id,
                'version_number' => 1,
                'schema' => $schema,
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $copy->fresh(['versions']) ?? $copy;
        });
    }
}
