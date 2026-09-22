<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AcceptAiDesignConceptRequest extends FormRequest
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
            'generation_id' => ['required', 'integer', 'exists:ai_generations,id'],
            'concept_index' => ['required', 'integer', 'min:0', 'max:2'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
