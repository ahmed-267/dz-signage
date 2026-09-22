<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MediaAsset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', Rule::enum(MediaType::class)],
            'name' => ['required', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'text_content' => ['nullable', 'string', 'max:50000'],
            'url' => ['nullable', 'string', 'max:2048', 'url'],
            'file' => ['nullable', 'file'],
        ];

        if (in_array($type, [MediaType::Image->value, MediaType::Logo->value, MediaType::Video->value, MediaType::Document->value], true)) {
            $rules['file'] = $this->fileRulesForType(MediaType::from($type));
        }

        if ($type === MediaType::Text->value) {
            $rules['text_content'] = ['required', 'string', 'max:50000'];
        }

        if ($type === MediaType::Link->value) {
            $rules['url'] = ['required', 'string', 'max:2048', 'url'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            if (! is_string($type) || $validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $mediaType = MediaType::from($type);
            } catch (\ValueError) {
                return;
            }

            if ($mediaType->isFileBased() && ! $this->hasFile('file')) {
                $validator->errors()->add('file', 'A file is required for this media type.');
            }

            if ($mediaType->isFileBased() && $this->hasFile('file')) {
                $this->assertExtensionAllowed($validator, $mediaType);
            }
        });
    }

    /**
     * @return list<string|object>
     */
    private function fileRulesForType(MediaType $type): array
    {
        $config = config('media.allowed.'.$type->value, []);
        $maxKb = (int) config('media.max_sizes.'.$type->value, 10240);
        $extensions = $config['extensions'] ?? [];

        return [
            'required',
            'file',
            'max:'.$maxKb,
            'mimes:'.implode(',', $extensions),
        ];
    }

    private function assertExtensionAllowed(Validator $validator, MediaType $type): void
    {
        $file = $this->file('file');
        if (! $file) {
            return;
        }

        $allowed = config('media.allowed.'.$type->value.'.extensions', []);
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            $validator->errors()->add('file', 'This file extension is not allowed for '.$type->label().' media.');
        }
    }
}
