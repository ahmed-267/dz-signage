<?php

namespace App\Actions\ScreenDesigns;

use App\Enums\ScreenDesignStatus;
use App\Models\ScreenDesign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishScreenDesign
{
    public function handle(User $user, ScreenDesign $design): ScreenDesign
    {
        return DB::transaction(function () use ($user, $design) {
            $latest = $design->versions()
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if ($latest === null) {
                throw ValidationException::withMessages([
                    'design' => 'Nothing to publish.',
                ]);
            }

            if (! $latest->isPublished()) {
                $latest->forceFill([
                    'published_at' => now(),
                    'created_by' => $user->id,
                ])->save();
            }

            $design->forceFill([
                'status' => ScreenDesignStatus::Published,
                'published_version_id' => $latest->id,
                'updated_by' => $user->id,
            ])->save();

            return $design->fresh(['publishedVersion', 'versions']) ?? $design;
        });
    }
}
