<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceMediaAsset
{
    /**
     * Replace the stored file while keeping the MediaAsset ID stable.
     * Old file is removed only after the new file is stored and the DB updated.
     *
     * @param  array{
     *     file: UploadedFile,
     *     name?: string|null,
     *     duration_seconds?: int|null,
     *     width?: int|null,
     *     height?: int|null
     * }  $data
     */
    public function handle(User $user, MediaAsset $mediaAsset, array $data): MediaAsset
    {
        if (! $mediaAsset->type->isFileBased()) {
            throw ValidationException::withMessages([
                'file' => 'Only file-based media can be replaced.',
            ]);
        }

        $file = $data['file'];

        $oldDisk = $mediaAsset->storage_disk;
        $oldPath = $mediaAsset->storage_path;

        $stored = MediaStorage::storeUpload($file, (int) $mediaAsset->workspace_id, $mediaAsset->type);

        try {
            return DB::transaction(function () use ($user, $mediaAsset, $data, $stored, $oldDisk, $oldPath) {
                $mediaAsset->fill([
                    ...$stored,
                    'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : $mediaAsset->name,
                    'duration_seconds' => $data['duration_seconds'] ?? null,
                    'width' => $data['width'] ?? $stored['width'],
                    'height' => $data['height'] ?? $stored['height'],
                    'updated_by' => $user->id,
                ]);
                $mediaAsset->save();

                MediaStorage::deleteStoredFile($oldDisk, $oldPath);

                return $mediaAsset->fresh() ?? $mediaAsset;
            });
        } catch (\Throwable $e) {
            MediaStorage::deleteStoredFile($stored['storage_disk'], $stored['storage_path']);

            throw $e;
        }
    }
}
