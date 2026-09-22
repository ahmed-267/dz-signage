<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReplaceMediaAssetRequest extends FormRequest
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

        return $user->can('replace', $media);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MediaAsset|null $media */
        $media = $this->route('media');
        $type = $media?->type;

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'file' => ['required', 'file'],
        ];

        if ($type instanceof MediaType && $type->isFileBased()) {
            $config = config('media.allowed.'.$type->value, []);
            $maxKb = (int) config('media.max_sizes.'.$type->value, 10240);
            $extensions = $config['extensions'] ?? [];

            $rules['file'] = [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', $extensions),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var MediaAsset|null $media */
            $media = $this->route('media');
            $file = $this->file('file');

            if (! $media || ! $file || ! $media->type->isFileBased()) {
                return;
            }

            $allowed = config('media.allowed.'.$media->type->value.'.extensions', []);
            $extension = strtolower($file->getClientOriginalExtension() ?: '');

            if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
                $validator->errors()->add('file', 'This file extension is not allowed.');
            }
        });
    }
}
