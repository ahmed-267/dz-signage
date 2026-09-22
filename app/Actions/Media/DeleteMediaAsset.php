<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteMediaAsset
{
    /**
     * Delete the MediaAsset and remove its physical file when present.
     * Blocks deletion when Screen Designs still reference this media.
     */
    public function handle(MediaAsset $mediaAsset): void
    {
        $usageCount = $mediaAsset->referencingScreenDesignsCount();
        if ($usageCount > 0) {
            throw ValidationException::withMessages([
                'media' => 'This media is used in '.$usageCount.' Screen Design'.($usageCount === 1 ? '' : 's').' and cannot be deleted.',
            ]);
        }

        $disk = $mediaAsset->storage_disk;
        $path = $mediaAsset->storage_path;

        DB::transaction(function () use ($mediaAsset) {
            $mediaAsset->delete();
        });

        MediaStorage::deleteStoredFile($disk, $path);
    }
}
