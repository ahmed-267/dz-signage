<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\DB;

class DuplicateMediaAsset
{
    /**
     * Duplicate the MediaAsset. File-based media gets a physical file copy
     * so deleting one copy cannot remove the other asset's bytes.
     */
    public function handle(User $user, MediaAsset $mediaAsset): MediaAsset
    {
        return DB::transaction(function () use ($user, $mediaAsset) {
            $copy = $mediaAsset->replicate([
                'created_at',
                'updated_at',
            ]);

            $copy->name = $this->copyName($mediaAsset->name);
            $copy->created_by = $user->id;
            $copy->updated_by = $user->id;

            if ($mediaAsset->hasStoredFile()) {
                $copy->storage_path = MediaStorage::copyStoredFile(
                    (string) $mediaAsset->storage_disk,
                    (string) $mediaAsset->storage_path,
                    (int) $mediaAsset->workspace_id,
                );
                $copy->storage_disk = $mediaAsset->storage_disk;
            }

            $copy->save();

            return $copy->fresh() ?? $copy;
        });
    }

    private function copyName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            $base = 'Media';
        }

        return $base.' copy';
    }
}
