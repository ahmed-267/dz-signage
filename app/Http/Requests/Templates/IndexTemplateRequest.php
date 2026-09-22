<?php

namespace App\Http\Requests\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Template::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(['all', 'dz', 'favourites'])],
            'q' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', Rule::in(['all', ...WorkspaceIndustry::values()])],
            'category' => ['nullable', 'string', Rule::in(['all', ...array_column(TemplateCategory::cases(), 'value')])],
            'orientation' => ['nullable', 'string', Rule::in(['all', ...array_column(TemplateOrientation::cases(), 'value')])],
            'theme' => ['nullable', 'string', Rule::in(['all', ...array_column(TemplateTheme::cases(), 'value')])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{tab: string, q: string, industry: string, category: string, orientation: string, theme: string}
     */
    public function filters(): array
    {
        return [
            'tab' => (string) $this->input('tab', 'all'),
            'q' => trim((string) $this->input('q', '')),
            'industry' => (string) $this->input('industry', 'all'),
            'category' => (string) $this->input('category', 'all'),
            'orientation' => (string) $this->input('orientation', 'all'),
            'theme' => (string) $this->input('theme', 'all'),
        ];
    }
}
