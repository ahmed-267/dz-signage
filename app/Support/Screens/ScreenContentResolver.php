<?php

namespace App\Support\Screens;

use App\Models\Screen;
use App\Support\Schedules\ScheduleEvaluator;
use Carbon\CarbonInterface;

/**
 * The only place that decides which content a Screen plays.
 *
 * Precedence:
 *   1. a matching active Schedule (plays its pinned published PlaylistVersion)
 *   2. otherwise the active Deployment (Publish to Screen — design or playlist)
 *   3. otherwise nothing
 *
 * Never re-implement this order in controllers, the player, or React.
 */
class ScreenContentResolver
{
    public function __construct(private readonly ScheduleEvaluator $evaluator) {}

    public function resolve(Screen $screen, ?CarbonInterface $at = null): ResolvedScreenContent
    {
        $at ??= now();

        $window = $this->evaluator->resolveWindowForScreen($screen, $at);

        if ($window !== null) {
            $window->schedule->loadMissing([
                'playlist',
                'playlistVersion.items.screenDesign',
                'playlistVersion.items.screenDesignVersion',
            ]);

            $version = $window->schedule->playlistVersion;

            // A schedule with no playable items falls through to the
            // deployment rather than blanking the screen.
            if ($version !== null && $version->items->contains(fn ($item) => $item->is_active)) {
                return ResolvedScreenContent::fromSchedule($window);
            }
        }

        $deployment = $screen->activeDeployment();

        if ($deployment !== null) {
            $deployment->loadMissing([
                'screenDesign',
                'screenDesignVersion',
                'playlist',
                'playlistVersion.items.screenDesign',
                'playlistVersion.items.screenDesignVersion',
            ]);

            return ResolvedScreenContent::fromDeployment($deployment);
        }

        return ResolvedScreenContent::none();
    }
}
