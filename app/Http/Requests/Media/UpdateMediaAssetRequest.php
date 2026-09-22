<?php

namespace App\Http\Requests\Media;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');
        $user = $this->user();

        if (! $media instanceof MediaAsset || ! $user) {
            return false;
        }

        $workspace = $user->currentWorkspace;
        if (! $workspace || (int) $media->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        return $user->can('update', $media);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MediaAsset|null $media */
        $media = $this->route('media');

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];

        if ($media?->type->value === 'text') {
            $rules['text_content'] = ['sometimes', 'required', 'string', 'max:50000'];
        }

        if ($media?->type->value === 'link') {
            $rules['url'] = ['sometimes', 'required', 'string', 'max:2048', 'url'];
        }

        return $rules;
    }
}
