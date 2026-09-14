<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceIndustry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null
            && $this->user()->can('update', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'industry' => ['required', Rule::enum(WorkspaceIndustry::class)],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone:all'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
