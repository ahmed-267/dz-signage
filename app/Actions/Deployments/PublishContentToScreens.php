<?php

namespace App\Actions\Deployments;

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\ScreenOperationalStatus;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single publishing entry point for direct Screen Design / Playlist Deployments.
 * Controllers and entry points must call this (or thin wrappers) — do not
 * duplicate supersede/create logic.
 *
 * Offline Connected Screens still receive an Active Deployment; acknowledgement
 * comes from Player heartbeats (`reported_deployment_id`), not from blocking publish.
 */
class PublishContentToScreens
{
    /**
     * @param  list<int>  $screenIds
     * @return array{deployments: Collection<int, Deployment>, warnings: list<string>}
     */
    public function publishDesign(
        User $user,
        Workspace $workspace,
        ScreenDesign $design,
        array $screenIds,
    ): array {
        $this->assertCanPublish($user, $workspace);

        if ((int) $design->workspace_id !== (int) $workspace->id) {
            throw ValidationException::withMessages([
                'design' => 'Screen design does not belong to this workspace.',
            ]);
        }

        if ($design->published_version_id === null || $design->publishedVersion === null) {
            throw ValidationException::withMessages([
                'design' => 'Only published screen designs can be deployed to screens.',
            ]);
        }

        $screens = $this->resolveScreens($workspace, $screenIds);
        $warnings = $this->warningsForScreens($screens, $design->orientation->value);

        $deployments = $this->deploy($user, $workspace, $screens, [
            'content_type' => DeploymentContentType::ScreenDesign,
            'screen_design_id' => $design->id,
            'screen_design_version_id' => $design->published_version_id,
            'playlist_id' => null,
            'playlist_version_id' => null,
        ]);

        return ['deployments' => $deployments, 'warnings' => $warnings];
    }

    /**
     * @param  list<int>  $screenIds
     * @return array{deployments: Collection<int, Deployment>, warnings: list<string>}
     */
    public function publishPlaylist(
        User $user,
        Workspace $workspace,
        Playlist $playlist,
        array $screenIds,
    ): array {
        $this->assertCanPublish($user, $workspace);

        if ((int) $playlist->workspace_id !== (int) $workspace->id) {
            throw ValidationException::withMessages([
                'playlist' => 'Playlist does not belong to this workspace.',
            ]);
        }

        if ($playlist->published_version_id === null || $playlist->publishedVersion === null) {
            throw ValidationException::withMessages([
                'playlist' => 'Only published playlists can be deployed to screens.',
            ]);
        }

        $screens = $this->resolveScreens($workspace, $screenIds);
        $warnings = $this->warningsForScreens($screens, $playlist->orientation?->value);

        $deployments = $this->deploy($user, $workspace, $screens, [
            'content_type' => DeploymentContentType::Playlist,
            'screen_design_id' => null,
            'screen_design_version_id' => null,
            'playlist_id' => $playlist->id,
            'playlist_version_id' => $playlist->published_version_id,
        ]);

        return ['deployments' => $deployments, 'warnings' => $warnings];
    }

    /**
     * Create a NEW Active Deployment from a historical row's pinned version.
     * Never mutates the historical Deployment.
     *
     * @return array{deployments: Collection<int, Deployment>, warnings: list<string>}
     */
    public function republish(
        User $user,
        Workspace $workspace,
        Deployment $source,
    ): array {
        $this->assertCanPublish($user, $workspace);

        if ((int) $source->workspace_id !== (int) $workspace->id) {
            throw ValidationException::withMessages([
                'deployment' => 'Deployment does not belong to this workspace.',
            ]);
        }

        $source->loadMissing(['screenDesign', 'playlist', 'screen']);

        if ($source->isPlaylist()) {
            $playlist = $source->playlist;
            if ($playlist === null || $source->playlist_version_id === null) {
                throw ValidationException::withMessages([
                    'deployment' => 'This deployment no longer has a valid playlist version to republish.',
                ]);
            }

            $screens = $this->resolveScreens($workspace, [(int) $source->screen_id]);
            $warnings = $this->warningsForScreens($screens, $playlist->orientation?->value);

            $deployments = $this->deploy($user, $workspace, $screens, [
                'content_type' => DeploymentContentType::Playlist,
                'screen_design_id' => null,
                'screen_design_version_id' => null,
                'playlist_id' => $source->playlist_id,
                'playlist_version_id' => $source->playlist_version_id,
            ]);

            return ['deployments' => $deployments, 'warnings' => $warnings];
        }

        $design = $source->screenDesign;
        if ($design === null || $source->screen_design_version_id === null) {
            throw ValidationException::withMessages([
                'deployment' => 'This deployment no longer has a valid screen design version to republish.',
            ]);
        }

        $screens = $this->resolveScreens($workspace, [(int) $source->screen_id]);
        $warnings = $this->warningsForScreens($screens, $design->orientation->value);

        $deployments = $this->deploy($user, $workspace, $screens, [
            'content_type' => DeploymentContentType::ScreenDesign,
            'screen_design_id' => $source->screen_design_id,
            'screen_design_version_id' => $source->screen_design_version_id,
            'playlist_id' => null,
            'playlist_version_id' => null,
        ]);

        return ['deployments' => $deployments, 'warnings' => $warnings];
    }

    private function assertCanPublish(User $user, Workspace $workspace): void
    {
        if (! $user->belongsToWorkspace($workspace)) {
            throw ValidationException::withMessages([
                'workspace' => 'You are not a member of this workspace.',
            ]);
        }

        if (! ($user->roleIn($workspace)?->canPublishContent() ?? false)) {
            throw ValidationException::withMessages([
                'content' => 'You are not allowed to publish content to screens.',
            ]);
        }

        if (! BillingEntitlement::canPublish($workspace)) {
            throw ValidationException::withMessages([
                'billing' => 'An active subscription is required to publish content to screens.',
            ]);
        }
    }

    /**
     * @param  list<int>  $screenIds
     * @return Collection<int, Screen>
     */
    private function resolveScreens(Workspace $workspace, array $screenIds): Collection
    {
        $screenIds = array_values(array_unique(array_map('intval', $screenIds)));
        if ($screenIds === []) {
            throw ValidationException::withMessages([
                'screen_ids' => 'Select at least one screen.',
            ]);
        }

        $screens = Screen::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $screenIds)
            ->get();

        if ($screens->count() !== count($screenIds)) {
            throw ValidationException::withMessages([
                'screen_ids' => 'One or more screens are invalid for this workspace.',
            ]);
        }

        return $screens;
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @return list<string>
     */
    private function warningsForScreens(Collection $screens, ?string $contentOrientation): array
    {
        $warnings = [];

        foreach ($screens as $screen) {
            if ($screen->operational_status === ScreenOperationalStatus::Inactive) {
                $warnings[] = "\"{$screen->name}\" is Inactive. Content will be assigned but the Player stays inactive until the screen is reactivated.";
            }

            if (
                $contentOrientation !== null
                && $screen->orientation !== null
                && $screen->orientation !== $contentOrientation
            ) {
                $warnings[] = "\"{$screen->name}\" is {$screen->orientation} but this content is {$contentOrientation}.";
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @param  array{
     *     content_type: DeploymentContentType,
     *     screen_design_id: int|null,
     *     screen_design_version_id: int|null,
     *     playlist_id: int|null,
     *     playlist_version_id: int|null
     * }  $payload
     * @return Collection<int, Deployment>
     */
    private function deploy(
        User $user,
        Workspace $workspace,
        Collection $screens,
        array $payload,
    ): Collection {
        return DB::transaction(function () use ($user, $workspace, $screens, $payload) {
            $deployments = new Collection;

            foreach ($screens as $screen) {
                Deployment::query()
                    ->where('screen_id', $screen->id)
                    ->where('status', DeploymentStatus::Active)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Deployment $existing): void {
                        $existing->forceFill([
                            'status' => DeploymentStatus::Superseded,
                            'superseded_at' => now(),
                        ])->save();
                    });

                $deployments->push(Deployment::query()->create([
                    'workspace_id' => $workspace->id,
                    'screen_id' => $screen->id,
                    'content_type' => $payload['content_type'],
                    'screen_design_id' => $payload['screen_design_id'],
                    'screen_design_version_id' => $payload['screen_design_version_id'],
                    'playlist_id' => $payload['playlist_id'],
                    'playlist_version_id' => $payload['playlist_version_id'],
                    // Active immediately so offline Players pick it up on reconnect.
                    // Sync acknowledgement is derived from heartbeats, not status.
                    'status' => DeploymentStatus::Active,
                    'deployed_by' => $user->id,
                    'deployed_at' => now(),
                    'superseded_at' => null,
                ]));
            }

            return $deployments;
        });
    }
}
