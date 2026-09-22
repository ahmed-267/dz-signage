<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MediaAsset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['all', ...array_column(MediaType::cases(), 'value')])],
            'sort' => ['nullable', 'string', Rule::in([
                'newest',
                'oldest',
                'name_asc',
                'name_desc',
                'largest',
                'smallest',
            ])],
            'view' => ['nullable', 'string', Rule::in(['grid', 'list'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{q: string, type: string, sort: string, view: string}
     */
    public function filters(): array
    {
        return [
            'q' => trim((string) $this->input('q', '')),
            'type' => (string) $this->input('type', 'all'),
            'sort' => (string) $this->input('sort', 'newest'),
            'view' => (string) $this->input('view', 'grid'),
        ];
    }
}
