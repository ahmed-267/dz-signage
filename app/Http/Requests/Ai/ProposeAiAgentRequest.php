<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposeAiAgentRequest extends FormRequest
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
            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'purpose' => ['nullable', 'string', Rule::in(config('ai.design.purposes', []))],
            'style' => ['nullable', 'string', Rule::in(config('ai.design.styles', []))],
            'name' => ['nullable', 'string', 'max:120'],
            'preferred_intent' => ['nullable', 'string', Rule::in(config('ai.agent.intents', []))],
            'screen_hints' => ['nullable', 'array', 'max:20'],
            'screen_hints.*' => ['string', 'max:120'],
            'activate' => ['sometimes', 'boolean'],
            'deploy' => ['sometimes', 'boolean'],
            'generate_matching_image' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
