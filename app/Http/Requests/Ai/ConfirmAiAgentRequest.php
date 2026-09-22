<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAiAgentRequest extends FormRequest
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
            'proposal_id' => ['required', 'integer', 'exists:ai_generations,id'],
            'confirm' => ['required', 'accepted'],
            'activate' => ['sometimes', 'boolean'],
            'deploy' => ['sometimes', 'boolean'],
        ];
    }
}
