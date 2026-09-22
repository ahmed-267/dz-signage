<?php

namespace App\Actions\ScreenDesigns;

use App\Models\Deployment;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\Workspace;
use App\Support\ScreenDesigns\ScreenDesignContent;
use Illuminate\Support\Facades\DB;

class PruneAbandonedScreenDesigns
{
    /**
     * Delete abandoned empty drafts for a workspace. Returns how many were removed.
     */
    public function handle(Workspace $workspace): int
    {
        $candidates = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->get();

        $removed = 0;

        foreach ($candidates as $design) {
            if (! ScreenDesignContent::isAbandonedDraft($design)) {
                continue;
            }

            $this->deleteCascade($design);
            $removed++;
        }

        return $removed;
    }

    public function deleteCascade(ScreenDesign $design): void
    {
        DB::transaction(function () use ($design): void {
            $design->forceFill(['published_version_id' => null])->save();
            Deployment::query()->where('screen_design_id', $design->id)->delete();
            ScreenDesignVersion::query()->where('screen_design_id', $design->id)->delete();
            $design->delete();
        });
    }
}
