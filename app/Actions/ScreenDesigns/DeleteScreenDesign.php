<?php

namespace App\Actions\ScreenDesigns;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\ScreenDesign;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteScreenDesign
{
    public function handle(ScreenDesign $design): void
    {
        $hasActiveDeployment = Deployment::query()
            ->where('screen_design_id', $design->id)
            ->where('status', DeploymentStatus::Active)
            ->exists();

        if ($hasActiveDeployment) {
            throw ValidationException::withMessages([
                'design' => 'This screen design cannot be deleted while it is actively deployed to a screen.',
            ]);
        }

        $playlistCount = $this->referencingPlaylistCount($design);
        if ($playlistCount > 0) {
            throw ValidationException::withMessages([
                'design' => "This design is used in {$playlistCount} playlist(s). Remove it from those playlists before deleting it.",
            ]);
        }

        DB::transaction(function () use ($design) {
            // Clear historical deployments so restrict FKs on design/version allow delete.
            Deployment::query()
                ->where('screen_design_id', $design->id)
                ->delete();

            $design->forceFill(['published_version_id' => null])->save();
            $design->versions()->delete();
            $design->delete();
        });
    }

    /**
     * Playlists (any version) that pin this design or one of its versions.
     * `playlist_items` FKs are RESTRICT, so this keeps the failure readable.
     */
    private function referencingPlaylistCount(ScreenDesign $design): int
    {
        $versionIds = $design->versions()->select('id');

        return Playlist::query()
            ->whereHas('versions.items', fn ($items) => $items
                ->where('playlist_items.screen_design_id', $design->id)
                ->orWhereIn('playlist_items.screen_design_version_id', $versionIds)
            )
            ->count();
    }
}
