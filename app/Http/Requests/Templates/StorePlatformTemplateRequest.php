<?php

namespace App\Http\Requests\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createPlatform', Template::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'orientation' => ['required', Rule::enum(TemplateOrientation::class)],
            'theme' => ['required', Rule::enum(TemplateTheme::class)],
            'category' => ['nullable', Rule::enum(TemplateCategory::class)],
            'industry' => ['nullable', 'string', Rule::in(WorkspaceIndustry::values())],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
