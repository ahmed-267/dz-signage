<?php

namespace App\Actions\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateTheme;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\LayoutSchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTemplateDraft
{
    /**
     * @param  array{
     *     schema?: array<string, mixed>|null,
     *     name?: string|null,
     *     description?: string|null,
     *     category?: TemplateCategory|string|null,
     *     industry?: string|null,
     *     theme?: TemplateTheme|string|null
     * }  $data
     */
    public function handle(User $user, Template $template, array $data): Template
    {
        return DB::transaction(function () use ($user, $template, $data) {
            $updates = [
                'updated_by' => $user->id,
            ];

            if (array_key_exists('name', $data) && $data['name'] !== null) {
                $updates['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('description', $data)) {
                $updates['description'] = $data['description'] !== null
                    ? trim((string) $data['description'])
                    : null;
            }

            if (array_key_exists('category', $data) && $data['category'] !== null) {
                $updates['category'] = $data['category'] instanceof TemplateCategory
                    ? $data['category']
                    : TemplateCategory::from((string) $data['category']);
            }

            if (array_key_exists('industry', $data)) {
                $updates['industry'] = $data['industry'];
            }

            if (array_key_exists('theme', $data) && $data['theme'] !== null) {
                $updates['theme'] = $data['theme'] instanceof TemplateTheme
                    ? $data['theme']
                    : TemplateTheme::from((string) $data['theme']);
            }

            $template->fill($updates)->save();

            if (array_key_exists('schema', $data) && is_array($data['schema'])) {
                $schema = LayoutSchemaValidator::validate(
                    LayoutSchemaNormalizer::normalize($data['schema']),
                );
                $this->saveSchemaVersion($user, $template, $schema);
            }

            return $template->fresh(['versions']) ?? $template;
        });
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function saveSchemaVersion(User $user, Template $template, array $schema): TemplateVersion
    {
        $latest = $template->versions()
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        if ($latest === null) {
            throw ValidationException::withMessages([
                'schema' => 'Template has no versions to update.',
            ]);
        }

        if (! $latest->isPublished()) {
            $latest->forceFill([
                'schema' => $schema,
                'created_by' => $user->id,
            ])->save();

            return $latest;
        }

        return TemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_number' => $latest->version_number + 1,
            'schema' => $schema,
            'created_by' => $user->id,
            'published_at' => null,
        ]);
    }
}
