<?php

namespace App\Actions\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Support\Facades\DB;

class CreateTemplate
{
    /**
     * @param  array{
     *     name: string,
     *     orientation: TemplateOrientation|string,
     *     theme: TemplateTheme|string,
     *     category?: TemplateCategory|string|null,
     *     industry?: string|null,
     *     description?: string|null
     * }  $data
     */
    public function handle(User $user, ?Workspace $workspace, array $data): Template
    {
        // Templates are platform-owned only. Workspace templates are not supported.
        if ($workspace !== null) {
            throw new \InvalidArgumentException('Templates must be platform-owned.');
        }

        $orientation = $data['orientation'] instanceof TemplateOrientation
            ? $data['orientation']
            : TemplateOrientation::from((string) $data['orientation']);

        $theme = $data['theme'] instanceof TemplateTheme
            ? $data['theme']
            : TemplateTheme::from((string) $data['theme']);

        $category = $data['category'] ?? TemplateCategory::Other;
        if (! $category instanceof TemplateCategory) {
            $category = TemplateCategory::from((string) $category);
        }

        return DB::transaction(function () use ($user, $data, $orientation, $theme, $category) {
            $template = Template::query()->create([
                'workspace_id' => null,
                'name' => trim((string) $data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'category' => $category,
                'industry' => $data['industry'] ?? null,
                'orientation' => $orientation,
                'canvas_width' => $orientation->canvasWidth(),
                'canvas_height' => $orientation->canvasHeight(),
                'theme' => $theme,
                'status' => TemplateStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            TemplateVersion::query()->create([
                'template_id' => $template->id,
                'version_number' => 1,
                'schema' => LayoutSchema::blank($orientation, $theme),
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $template->fresh(['versions']) ?? $template;
        });
    }
}
