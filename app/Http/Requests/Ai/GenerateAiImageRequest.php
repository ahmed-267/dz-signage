<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAiImageRequest extends FormRequest
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
            'aspect' => ['nullable', 'string', Rule::in(array_keys(config('ai.image.aspects', [])))],
            'style' => ['nullable', 'string', Rule::in(array_keys(config('ai.image.styles', [])))],
            'refinement' => ['nullable', 'string', Rule::in(array_keys(config('ai.image.refinements', [])))],
            'variants' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('ai.limits.image_variants_max', 3)],
            'width' => ['nullable', 'integer', 'min:64', 'max:3840'],
            'height' => ['nullable', 'integer', 'min:64', 'max:3840'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
