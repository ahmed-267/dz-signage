<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::enum(WorkspaceRole::class)->except([
                    WorkspaceRole::Owner,
                    WorkspaceRole::Viewer,
                    WorkspaceRole::LocationManager,
                ]),
            ],
        ];
    }
}
