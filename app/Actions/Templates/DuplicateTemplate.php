<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DuplicateTemplate
{
    public function handle(User $user, Workspace $workspace, Template $source): Template
    {
        return DB::transaction(function () use ($user, $workspace, $source) {
            $sourceVersion = $source->publishedVersion
                ?? $source->versions()->orderByDesc('version_number')->first();

            $copy = Template::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $this->copyName($source->name),
                'description' => $source->description,
                'category' => $source->category,
                'industry' => $source->industry,
                'orientation' => $source->orientation,
                'canvas_width' => $source->canvas_width,
                'canvas_height' => $source->canvas_height,
                'theme' => $source->theme,
                'status' => TemplateStatus::Draft,
                'thumbnail_path' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'published_version_id' => null,
            ]);

            TemplateVersion::query()->create([
                'template_id' => $copy->id,
                'version_number' => 1,
                'schema' => $sourceVersion !== null ? $sourceVersion->schema : [
                    'schemaVersion' => 1,
                    'canvas' => [
                        'width' => $source->canvas_width,
                        'height' => $source->canvas_height,
                        'orientation' => $source->orientation->value,
                        'background' => [
                            'type' => 'color',
                            'value' => $source->theme->defaultBackground(),
                        ],
                    ],
                    'theme' => $source->theme->value,
                    'elements' => [],
                ],
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $copy->fresh(['versions']) ?? $copy;
        });
    }

    private function copyName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            $base = 'Template';
        }

        return $base.' copy';
    }
}
