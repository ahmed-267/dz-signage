<?php

namespace App\Actions\Deployments;

use App\Models\Deployment;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Thin wrapper kept for existing call sites. Prefer PublishContentToScreens.
 */
class PublishDesignToScreens
{
    public function __construct(private readonly PublishContentToScreens $publisher) {}

    /**
     * @param  list<int>  $screenIds
     * @return Collection<int, Deployment>
     */
    public function handle(
        User $user,
        Workspace $workspace,
        ScreenDesign $design,
        array $screenIds,
    ): Collection {
        return $this->publisher->publishDesign($user, $workspace, $design, $screenIds)['deployments'];
    }
}
