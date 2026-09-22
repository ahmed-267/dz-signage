<?php

namespace App\Http\Requests\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');
        $user = $this->user();

        if (! $template instanceof Template || ! $user) {
            return false;
        }

        if (! $template->isPlatform()) {
            $workspace = $user->currentWorkspace;
            if (! $workspace || (int) $template->workspace_id !== (int) $workspace->id) {
                abort(404);
            }
        }

        return $user->can('update', $template);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'nullable', Rule::enum(TemplateCategory::class)],
            'industry' => ['sometimes', 'nullable', 'string', Rule::in(WorkspaceIndustry::values())],
            'theme' => ['sometimes', 'nullable', Rule::enum(TemplateTheme::class)],
            'schema' => ['sometimes', 'required', 'array'],
            'schema.schemaVersion' => ['required_with:schema', 'integer'],
            'schema.canvas' => ['required_with:schema', 'array'],
            'schema.theme' => ['required_with:schema', 'string'],
            'schema.elements' => ['required_with:schema', 'array'],
        ];
    }
}
