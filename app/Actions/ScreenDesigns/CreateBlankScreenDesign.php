<?php

namespace App\Actions\ScreenDesigns;

use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use App\Support\ScreenDesigns\ScreenDesignContent;
use Illuminate\Support\Facades\DB;

class CreateBlankScreenDesign
{
    public function handle(
        User $user,
        Workspace $workspace,
        string $name,
        TemplateOrientation $orientation,
        ?TemplateTheme $theme = null,
    ): ScreenDesign {
        $theme ??= TemplateTheme::Blank;
        $resolvedName = trim($name) !== ''
            ? trim($name)
            : ScreenDesignContent::defaultNameForOrientation($orientation->value);

        return DB::transaction(function () use ($user, $workspace, $resolvedName, $orientation, $theme) {
            $design = ScreenDesign::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $resolvedName,
                'orientation' => $orientation,
                'canvas_width' => $orientation->canvasWidth(),
                'canvas_height' => $orientation->canvasHeight(),
                'status' => ScreenDesignStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ScreenDesignVersion::query()->create([
                'screen_design_id' => $design->id,
                'version_number' => 1,
                'schema' => LayoutSchema::blank($orientation, $theme),
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $design->fresh(['versions']) ?? $design;
        });
    }
}
