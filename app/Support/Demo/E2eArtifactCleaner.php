<?php

namespace App\Support\Demo;

use App\Models\AiGeneration;
use App\Models\AuditLog;
use App\Models\BrandKit;
use App\Models\Deployment;
use App\Models\Location;
use App\Models\MediaAsset;
use App\Models\PlatformError;
use App\Models\PlaybackEvent;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\SupportRequest;
use App\Models\Template;
use App\Models\TemplateFavourite;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes disposable Playwright / E2E fixtures (names prefixed with "E2E ").
 * Safe for local/demo databases — never run in production callers.
 */
final class E2eArtifactCleaner
{
    public static function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        DB::transaction(function (): void {
            self::cleanupTemplates();
            self::cleanupPlaylistsAndSchedules();
            self::cleanupScreenDesigns();
            self::cleanupScreens();
            self::cleanupMedia();
            self::cleanupE2eWorkspaces();
        });
    }

    private static function cleanupTemplates(): void
    {
        $e2eTemplateIds = Template::query()
            ->where(function ($query): void {
                $query->where('name', 'like', 'E2E %')
                    ->orWhere('name', 'like', 'E2E Platform %')
                    ->orWhere('name', 'like', 'E2E Portrait %');
            })
            ->pluck('id');

        if ($e2eTemplateIds->isEmpty()) {
            return;
        }

        Template::query()->whereIn('id', $e2eTemplateIds)->update([
            'published_version_id' => null,
        ]);
        TemplateFavourite::query()->whereIn('template_id', $e2eTemplateIds)->delete();
        ScreenDesign::query()
            ->whereIn('source_template_id', $e2eTemplateIds)
            ->update([
                'source_template_id' => null,
                'source_template_version_id' => null,
            ]);
        TemplateVersion::query()->whereIn('template_id', $e2eTemplateIds)->delete();
        Template::query()->whereIn('id', $e2eTemplateIds)->delete();
    }

    private static function cleanupPlaylistsAndSchedules(): void
    {
        $e2ePlaylistIds = Playlist::query()
            ->where('name', 'like', 'E2E %')
            ->pluck('id');

        Schedule::query()
            ->where(function ($query) use ($e2ePlaylistIds): void {
                $query->where('name', 'like', 'E2E %');
                if ($e2ePlaylistIds->isNotEmpty()) {
                    $query->orWhereIn('playlist_id', $e2ePlaylistIds);
                }
            })
            ->get()
            ->each(function (Schedule $schedule): void {
                $schedule->screens()->detach();
                $schedule->delete();
            });

        if ($e2ePlaylistIds->isEmpty()) {
            return;
        }

        Playlist::query()->whereIn('id', $e2ePlaylistIds)->update([
            'published_version_id' => null,
        ]);
        Deployment::query()->whereIn('playlist_id', $e2ePlaylistIds)->delete();
        Playlist::query()->whereIn('id', $e2ePlaylistIds)->delete();
    }

    private static function cleanupScreenDesigns(): void
    {
        $e2eDesignIds = ScreenDesign::query()
            ->where(function ($query): void {
                $query->where('name', 'like', 'E2E %')
                    ->orWhere('name', 'like', 'E2E Design %')
                    ->orWhere('name', 'like', 'Untitled%')
                    ->orWhere('name', 'like', 'New Landscape%')
                    ->orWhere('name', 'like', 'New Portrait%');
            })
            ->pluck('id');

        if ($e2eDesignIds->isEmpty()) {
            return;
        }

        ScreenDesign::query()->whereIn('id', $e2eDesignIds)->update([
            'published_version_id' => null,
        ]);
        Deployment::query()->whereIn('screen_design_id', $e2eDesignIds)->delete();
        ScreenDesignVersion::query()->whereIn('screen_design_id', $e2eDesignIds)->delete();
        ScreenDesign::query()->whereIn('id', $e2eDesignIds)->delete();
    }

    private static function cleanupScreens(): void
    {
        $screens = Screen::query()
            ->where('name', 'like', 'E2E %')
            ->get();

        foreach ($screens as $screen) {
            $screen->schedules()->detach();
            $screen->delete();
        }
    }

    private static function cleanupMedia(): void
    {
        MediaAsset::query()
            ->where(function ($query): void {
                $query->where('name', 'like', 'E2E %')
                    ->orWhere('name', 'like', 'E2E AI %')
                    ->orWhere('name', 'like', 'Design Img %')
                    ->orWhere('metadata->seed', 'e2e');
            })
            ->get()
            ->each(function (MediaAsset $asset): void {
                if (filled($asset->storage_path) && filled($asset->storage_disk)) {
                    try {
                        Storage::disk($asset->storage_disk)->delete($asset->storage_path);
                    } catch (\Throwable) {
                        // ignore missing files
                    }
                }
                $asset->delete();
            });
    }

    /**
     * Delete disposable Playwright onboarding workspaces so they never clutter
     * the local switcher or leave orphaned demo pollution behind.
     * Never touches local-dev-workspace / owner@dz.local canonical demo.
     */
    private static function cleanupE2eWorkspaces(): void
    {
        $workspaces = Workspace::query()
            ->where(function ($query): void {
                $query->where('slug', 'like', 'e2e-workspace-%')
                    ->orWhere('name', 'like', 'E2E Workspace%');
            })
            ->where('slug', '!=', 'local-dev-workspace')
            ->get();

        foreach ($workspaces as $workspace) {
            self::purgeWorkspace($workspace);
        }
    }

    private static function purgeWorkspace(Workspace $workspace): void
    {
        $id = $workspace->id;

        User::query()
            ->where('current_workspace_id', $id)
            ->update(['current_workspace_id' => null]);

        BrandKit::query()->where('workspace_id', $id)->delete();
        AiGeneration::query()->where('workspace_id', $id)->delete();
        ScreenDailyStat::query()->where('workspace_id', $id)->delete();
        PlaybackEvent::query()->where('workspace_id', $id)->delete();
        SupportRequest::query()->where('workspace_id', $id)->delete();
        AuditLog::query()->where('workspace_id', $id)->delete();
        PlatformError::query()->where('workspace_id', $id)->delete();

        Schedule::query()
            ->where('workspace_id', $id)
            ->get()
            ->each(function (Schedule $schedule): void {
                $schedule->screens()->detach();
                $schedule->delete();
            });

        Playlist::query()
            ->where('workspace_id', $id)
            ->get()
            ->each(function (Playlist $playlist): void {
                $playlist->forceFill(['published_version_id' => null])->save();
                Deployment::query()->where('playlist_id', $playlist->id)->delete();
                $playlist->delete();
            });

        ScreenDesign::query()
            ->where('workspace_id', $id)
            ->get()
            ->each(function (ScreenDesign $design): void {
                $design->forceFill(['published_version_id' => null])->save();
                Deployment::query()->where('screen_design_id', $design->id)->delete();
                ScreenDesignVersion::query()->where('screen_design_id', $design->id)->delete();
                $design->delete();
            });

        Screen::query()
            ->where('workspace_id', $id)
            ->get()
            ->each(function (Screen $screen): void {
                $screen->schedules()->detach();
                $screen->delete();
            });

        Location::query()->where('workspace_id', $id)->delete();
        Deployment::query()->where('workspace_id', $id)->delete();

        MediaAsset::query()
            ->where('workspace_id', $id)
            ->get()
            ->each(function (MediaAsset $asset): void {
                if (filled($asset->storage_path) && filled($asset->storage_disk)) {
                    try {
                        Storage::disk($asset->storage_disk)->delete($asset->storage_path);
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                $asset->delete();
            });

        WorkspaceMember::query()->where('workspace_id', $id)->delete();
        $workspace->delete();
    }
}
