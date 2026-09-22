<?php

namespace Database\Factories;

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'screen_id' => Screen::factory(),
            'content_type' => DeploymentContentType::ScreenDesign,
            'screen_design_id' => ScreenDesign::factory(),
            'screen_design_version_id' => ScreenDesignVersion::factory(),
            'playlist_id' => null,
            'playlist_version_id' => null,
            'status' => DeploymentStatus::Active,
            'deployed_by' => User::factory(),
            'deployed_at' => now(),
            'superseded_at' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
        ]);
    }

    public function forScreen(Screen $screen): static
    {
        return $this->state(fn () => [
            'workspace_id' => $screen->workspace_id,
            'screen_id' => $screen->id,
        ]);
    }

    public function forDesign(ScreenDesign $design, ?ScreenDesignVersion $version = null): static
    {
        $version ??= $design->publishedVersion;

        return $this->state(fn () => [
            'content_type' => DeploymentContentType::ScreenDesign,
            'workspace_id' => $design->workspace_id,
            'screen_design_id' => $design->id,
            'screen_design_version_id' => $version?->id,
            'playlist_id' => null,
            'playlist_version_id' => null,
        ]);
    }

    public function forPlaylist(Playlist $playlist, ?PlaylistVersion $version = null): static
    {
        $version ??= $playlist->publishedVersion;

        return $this->state(fn () => [
            'content_type' => DeploymentContentType::Playlist,
            'workspace_id' => $playlist->workspace_id,
            'screen_design_id' => null,
            'screen_design_version_id' => null,
            'playlist_id' => $playlist->id,
            'playlist_version_id' => $version?->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => DeploymentStatus::Active,
            'deployed_at' => now(),
            'superseded_at' => null,
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn () => [
            'status' => DeploymentStatus::Superseded,
            'superseded_at' => now(),
        ]);
    }
}
