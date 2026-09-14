<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceIndustry;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateWorkspace
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Workspace $workspace, array $data): Workspace
    {
        $payload = [];

        foreach (['name', 'country', 'timezone'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('industry', $data)) {
            $payload['industry'] = $data['industry'] instanceof WorkspaceIndustry
                ? $data['industry']
                : WorkspaceIndustry::from((string) $data['industry']);
        }

        if (($data['remove_logo'] ?? false) === true) {
            if ($workspace->logo_path) {
                Storage::disk('public')->delete($workspace->logo_path);
            }
            $payload['logo_path'] = null;
        } elseif (($data['logo'] ?? null) instanceof UploadedFile) {
            if ($workspace->logo_path) {
                Storage::disk('public')->delete($workspace->logo_path);
            }
            $payload['logo_path'] = $data['logo']->store('workspace-logos', 'public');
        }

        $workspace->fill($payload)->save();

        return $workspace->fresh();
    }
}
