<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceIndustry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
        ];
    }
}
