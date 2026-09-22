<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateMediaAsset
{
    /**
     * Update display name and/or editable text/link content.
     *
     * @param  array{
     *     name?: string|null,
     *     text_content?: string|null,
     *     url?: string|null
     * }  $data
     */
    public function handle(User $user, MediaAsset $mediaAsset, array $data): MediaAsset
    {
        return DB::transaction(function () use ($user, $mediaAsset, $data) {
            if (array_key_exists('name', $data) && filled($data['name'])) {
                $mediaAsset->name = trim((string) $data['name']);
            }

            if ($mediaAsset->type->isEditableContent()) {
                if ($mediaAsset->type->value === 'text' && array_key_exists('text_content', $data)) {
                    $mediaAsset->text_content = (string) $data['text_content'];
                }

                if ($mediaAsset->type->value === 'link' && array_key_exists('url', $data)) {
                    $mediaAsset->url = (string) $data['url'];
                }
            } elseif (array_key_exists('text_content', $data) || array_key_exists('url', $data)) {
                throw ValidationException::withMessages([
                    'type' => 'This media type does not support content editing. Use replace for files.',
                ]);
            }

            $mediaAsset->updated_by = $user->id;
            $mediaAsset->save();

            return $mediaAsset->fresh() ?? $mediaAsset;
        });
    }
}
