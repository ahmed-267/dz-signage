<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RewriteAiTextRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:'.(int) config('ai.limits.prompt_max', 2000)],
            'action' => ['required', 'string', Rule::in(config('ai.text.rewrite_actions', []))],
            'variants' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('ai.limits.text_variants_max', 3)],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
