<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\User;
use App\Support\Platform\AuditLogger;
use Illuminate\Support\Facades\DB;

class ArchiveTemplate
{
    public function handle(User $user, Template $template): Template
    {
        return DB::transaction(function () use ($user, $template) {
            $template->forceFill([
                'status' => TemplateStatus::Archived,
                'updated_by' => $user->id,
            ])->save();

            AuditLogger::record(
                $user,
                'template.archived',
                'template',
                $template->id,
                null,
                ['name' => $template->name],
            );

            return $template->fresh() ?? $template;
        });
    }
}
