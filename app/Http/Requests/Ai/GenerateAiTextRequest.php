<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAiTextRequest extends FormRequest
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
        $modes = config('ai.text.modes', []);
        $purposes = config('ai.text.purposes', []);

        return [
            'prompt' => ['required', 'string', 'max:'.(int) config('ai.limits.prompt_max', 2000)],
            'mode' => ['nullable', 'string', Rule::in($modes)],
            'purpose' => ['nullable', 'string', Rule::in(array_merge($purposes, $modes))],
            'tone' => ['nullable', 'string', Rule::in(config('ai.text.tones', []))],
            'length' => ['nullable', 'string', Rule::in(config('ai.text.lengths', []))],
            'variants' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('ai.limits.text_variants_max', 3)],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
