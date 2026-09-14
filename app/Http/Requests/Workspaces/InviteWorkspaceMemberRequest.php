<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null
            && $this->user()->can('invite', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                Rule::enum(WorkspaceRole::class)->except([WorkspaceRole::Owner]),
            ],
        ];
    }
}
