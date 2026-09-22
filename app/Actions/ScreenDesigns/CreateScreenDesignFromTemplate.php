<?php

namespace App\Actions\ScreenDesigns;

use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateStatus;
use App\Models\BrandKit;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BrandKit\BrandKitSchemaApplier;
use App\Support\Rendering\LayoutSchemaNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateScreenDesignFromTemplate
{
    public function handle(User $user, Workspace $workspace, Template $template): ScreenDesign
    {
        if (! $template->isPlatform() || $template->status !== TemplateStatus::Published) {
            throw ValidationException::withMessages([
                'template' => 'Only published platform templates can be used.',
            ]);
        }

        $published = $template->publishedVersion;
        if ($published === null) {
            throw ValidationException::withMessages([
                'template' => 'This template has no published version.',
            ]);
        }

        // Copy schema into the design — independent of future template edits.
        $schema = LayoutSchemaNormalizer::normalize($published->schema);

        $brandKit = BrandKit::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        $schema = BrandKitSchemaApplier::apply($schema, $brandKit, $workspace);

        // Always use the Template name — Brand Kit name belongs in schema bindings, not the library title.
        $designName = $template->name;

        return DB::transaction(function () use ($user, $workspace, $template, $published, $schema, $designName) {
            $design = ScreenDesign::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $designName,
                'orientation' => $template->orientation,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'source_template_id' => $template->id,
                'source_template_version_id' => $published->id,
                'status' => ScreenDesignStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ScreenDesignVersion::query()->create([
                'screen_design_id' => $design->id,
                'version_number' => 1,
                'schema' => $schema,
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $design->fresh(['versions', 'sourceTemplate']) ?? $design;
        });
    }
}
