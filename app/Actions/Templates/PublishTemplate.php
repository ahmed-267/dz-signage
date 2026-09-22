<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\User;
use App\Support\Platform\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishTemplate
{
    public function handle(User $user, Template $template): Template
    {
        return DB::transaction(function () use ($user, $template) {
            $latest = $template->versions()
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if ($latest === null) {
                throw ValidationException::withMessages([
                    'template' => 'Template has no versions to publish.',
                ]);
            }

            if ($latest->isPublished()) {
                throw ValidationException::withMessages([
                    'template' => 'The latest version is already published.',
                ]);
            }

            $latest->forceFill([
                'published_at' => now(),
            ])->save();

            $template->forceFill([
                'status' => TemplateStatus::Published,
                'published_version_id' => $latest->id,
                'updated_by' => $user->id,
            ])->save();

            AuditLogger::record(
                $user,
                'template.published',
                'template',
                $template->id,
                null,
                [
                    'version_id' => $latest->id,
                    'version_number' => $latest->version_number,
                    'name' => $template->name,
                ],
            );

            return $template->fresh(['versions', 'publishedVersion']) ?? $template;
        });
    }
}
