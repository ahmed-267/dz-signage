<?php

namespace App\Support\Screens;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\MediaMap;

/**
 * Read-only "what should this TV be showing?" preview payload for Paired TVs.
 *
 * Uses {@see ScreenContentResolver} — never invents Schedule/Deployment precedence.
 */
final class TvContentPreview
{
    public function __construct(
        private readonly ScreenContentResolver $resolver,
        private readonly NowShowing $nowShowing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forScreen(Screen $screen): array
    {
        $device = $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $activeDeployment = $screen->relationLoaded('deployments')
            ? $screen->deployments->first(
                fn (Deployment $d) => $d->status === DeploymentStatus::Active,
            )
            : $screen->activeDeployment();

        $nowShowing = $this->nowShowing->forScreen($screen, $device, $activeDeployment);
        $network = ScreenPresence::networkState($device);
        $resolved = $this->resolver->resolve($screen);

        $reportedName = null;
        $reportedDeploymentId = $device?->reported_deployment_id;
        if ($reportedDeploymentId !== null) {
            $reported = Deployment::query()
                ->where('workspace_id', $screen->workspace_id)
                ->whereKey($reportedDeploymentId)
                ->first();
            $reportedName = $reported?->contentName();
        }

        $expectedName = $nowShowing['content_name'] ?? null;
        $syncLabel = match (true) {
            $resolved->isNone() => 'No content',
            $network === 'offline' => 'TV Offline',
            ($nowShowing['sync_state'] ?? null) === 'out_of_sync' => 'Out of Sync',
            ($nowShowing['ack_state'] ?? null) === 'live' => 'Live & Synced',
            ($nowShowing['ack_state'] ?? null) === 'scheduled' => 'Scheduled',
            default => $nowShowing['ack_label'] ?? 'Pending',
        };

        $base = [
            'screen_id' => $screen->id,
            'screen_name' => $screen->name,
            'orientation' => $screen->orientation,
            'network_state' => $network,
            'now_showing' => $nowShowing,
            'preview_status' => $syncLabel,
            'expected_content_name' => $expectedName,
            'reported_content_name' => $reportedName,
            'is_offline' => $network === 'offline',
            'is_out_of_sync' => ($nowShowing['sync_state'] ?? null) === 'out_of_sync',
            'source_label' => $this->sourceLabel($nowShowing),
            'kind' => 'empty',
            'items' => [],
            'media_map' => [],
            'canvas' => [
                'width' => $screen->orientation === 'portrait' ? 1080 : 1920,
                'height' => $screen->orientation === 'portrait' ? 1920 : 1080,
                'orientation' => $screen->orientation ?? 'landscape',
            ],
        ];

        if ($resolved->isNone()) {
            return $base;
        }

        if ($resolved->isSchedule() || ($resolved->deployment?->isPlaylist() === true)) {
            $playlist = $resolved->playlist;
            $version = $resolved->playlistVersion;

            if ($playlist === null || $version === null) {
                return $base;
            }

            $version->loadMissing(['items.screenDesign', 'items.screenDesignVersion']);
            $playlistOrientation = $playlist->orientation->value;
            $items = $version->items
                ->filter(fn (PlaylistItem $item) => $item->is_active)
                ->values();

            $mediaIds = [];
            $payloadItems = $items->map(function (PlaylistItem $item) use (&$mediaIds, $playlistOrientation): array {
                $design = $item->screenDesign;
                $designVersion = $item->screenDesignVersion;
                $schema = LayoutSchemaNormalizer::normalize(
                    is_array($designVersion?->schema) ? $designVersion->schema : [],
                );
                $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($schema)];

                return [
                    'id' => $item->id,
                    'position' => $item->position,
                    'name' => $design !== null ? $design->name : null,
                    'schema' => $schema,
                    'duration_seconds' => $item->duration_seconds,
                    'loop_count' => max(1, (int) $item->loop_count),
                    'transition' => $item->transition->value,
                    'transition_speed' => $item->transition_speed->value,
                    'is_active' => true,
                    'canvas_width' => $design !== null ? $design->canvas_width : 1920,
                    'canvas_height' => $design !== null ? $design->canvas_height : 1080,
                    'orientation' => $design !== null
                        ? $design->orientation->value
                        : $playlistOrientation,
                ];
            })->all();

            return [
                ...$base,
                'kind' => 'playlist',
                'content_name' => $playlist->name,
                'items' => $payloadItems,
                'media_map' => MediaMap::build($screen->workspace_id, $mediaIds),
                'canvas' => [
                    'width' => $playlistOrientation === 'portrait' ? 1080 : 1920,
                    'height' => $playlistOrientation === 'portrait' ? 1920 : 1080,
                    'orientation' => $playlistOrientation,
                ],
            ];
        }

        $deployment = $resolved->deployment;
        if ($deployment === null) {
            return $base;
        }

        $deployment->loadMissing(['screenDesign', 'screenDesignVersion']);
        $design = $deployment->screenDesign;
        $version = $deployment->screenDesignVersion;
        $schema = LayoutSchemaNormalizer::normalize(
            is_array($version?->schema) ? $version->schema : [],
        );
        $mediaIds = ScreenDesign::mediaIdsFromSchema($schema);
        $orientation = $design !== null
            ? $design->orientation->value
            : ($screen->orientation ?? 'landscape');
        $contentName = $design !== null
            ? $design->name
            : ($nowShowing['content_name'] ?? 'Screen');

        return [
            ...$base,
            'kind' => 'screen',
            'content_name' => $contentName,
            'items' => [[
                'id' => $design !== null ? $design->id : 0,
                'position' => 1,
                'name' => $design !== null ? $design->name : 'Screen',
                'schema' => $schema,
                'duration_seconds' => 15,
                'loop_count' => 1,
                'transition' => 'none',
                'transition_speed' => 'normal',
                'is_active' => true,
                'canvas_width' => $design !== null ? $design->canvas_width : 1920,
                'canvas_height' => $design !== null ? $design->canvas_height : 1080,
                'orientation' => $orientation,
            ]],
            'media_map' => MediaMap::build($screen->workspace_id, $mediaIds),
            'canvas' => [
                'width' => $design !== null
                    ? $design->canvas_width
                    : ($orientation === 'portrait' ? 1080 : 1920),
                'height' => $design !== null
                    ? $design->canvas_height
                    : ($orientation === 'portrait' ? 1920 : 1080),
                'orientation' => $orientation,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $nowShowing
     */
    private function sourceLabel(array $nowShowing): string
    {
        if (($nowShowing['content_source'] ?? null) === 'schedule') {
            $parts = ['Source: Schedule'];
            if (! empty($nowShowing['schedule_name'])) {
                $parts[] = (string) $nowShowing['schedule_name'];
            }
            if (! empty($nowShowing['window_ends_at_local'])) {
                $parts[] = 'until '.$nowShowing['window_ends_at_local'];
            }

            return implode(' · ', $parts);
        }

        if (($nowShowing['content_source'] ?? null) === 'deployment') {
            $type = $nowShowing['content_type_label'] ?? 'Published';

            return 'Source: Direct Publish ('.$type.')';
        }

        return 'Source: None';
    }
}
