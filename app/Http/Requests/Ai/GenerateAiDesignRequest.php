<?php

namespace App\Http\Requests\Ai;

use App\Enums\TemplateOrientation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAiDesignRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:'.(int) config('ai.limits.prompt_max', 2000)],
            'orientation' => ['required', 'string', Rule::enum(TemplateOrientation::class)],
            'purpose' => ['required', 'string', Rule::in(config('ai.design.purposes', []))],
            'style' => ['nullable', 'string', Rule::in(config('ai.design.styles', []))],
            'name' => ['nullable', 'string', 'max:120'],
            'brand_colors' => ['nullable', 'array', 'max:6'],
            'brand_colors.*' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'use_brand_kit' => ['nullable', 'boolean'],
            'generate_matching_image' => ['nullable', 'boolean'],
            'variant_count' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('ai.limits.design_concepts_max', 3)],
            'concept_count' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('ai.limits.design_concepts_max', 3)],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
