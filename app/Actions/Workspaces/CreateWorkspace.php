<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data) {
            $industry = $data['industry'] instanceof WorkspaceIndustry
                ? $data['industry']
                : WorkspaceIndustry::from((string) $data['industry']);

            $logoPath = null;
            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $logoPath = $data['logo']->store('workspace-logos', 'public');
            }

            $workspace = Workspace::query()->create([
                'name' => (string) $data['name'],
                'slug' => $this->uniqueSlug((string) $data['name']),
                'industry' => $industry,
                'country' => (string) $data['country'],
                'timezone' => (string) $data['timezone'],
                'logo_path' => $logoPath,
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->forceFill([
                'current_workspace_id' => $workspace->id,
            ])->save();

            return $workspace->fresh();
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'workspace';
        }

        $slug = $base;
        $i = 1;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
