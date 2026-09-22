<?php

namespace App\Support\Player;

use App\Enums\DeploymentContentType;
use App\Enums\ScreenContentSource;
use App\Models\Deployment;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Support\Screens\ResolvedScreenContent;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared Player manifest / offline-package content bodies.
 * Keep schedule and deployment playlist shapes identical.
 */
final class ManifestContentBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function schedulePayload(Screen $screen, ResolvedScreenContent $content): array
    {
        $schedule = $content->schedule;
        $playlist = $content->playlist;
        $version = $content->playlistVersion;

        if ($schedule === null || $playlist === null || $version === null) {
            return $this->emptyPayload($screen, 'no_content');
        }

        $body = $this->playlistBody($screen, $playlist, $version);

        if ($body === null) {
            return $this->emptyPayload($screen, 'no_content');
        }

        return [
            'manifestVersion' => 1,
            'screenId' => $screen->id,
            'contentSource' => ScreenContentSource::Schedule->value,
            'scheduleId' => $schedule->id,
            'scheduleName' => $schedule->name,
            'schedulePriority' => $schedule->priority,
            'deploymentId' => null,
            'deploymentVersion' => $content->versionLabel(),
            'contentType' => DeploymentContentType::Playlist->value,
            ...$body,
            'validity' => [
                'timezone' => $schedule->timezone,
                'startsAt' => $content->windowStartsAt()?->toIso8601String(),
                'endsAt' => $content->windowEndsAt()?->toIso8601String(),
            ],
            'generatedAt' => now()->toIso8601String(),
            'status' => 'ready',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deploymentPayload(Screen $screen, Deployment $deployment): array
    {
        if ($deployment->isPlaylist()) {
            $deployment->loadMissing([
                'playlist',
                'playlistVersion.items.screenDesign',
                'playlistVersion.items.screenDesignVersion',
            ]);

            $playlist = $deployment->playlist;
            $version = $deployment->playlistVersion;

            if ($playlist === null || $version === null) {
                return $this->emptyPayload($screen, 'no_content');
            }

            $body = $this->playlistBody($screen, $playlist, $version);

            if ($body === null) {
                return $this->emptyPayload($screen, 'no_content');
            }

            return [
                'manifestVersion' => 1,
                'screenId' => $screen->id,
                'contentSource' => ScreenContentSource::Deployment->value,
                'deploymentId' => $deployment->id,
                'deploymentVersion' => $deployment->versionLabel(),
                'contentType' => DeploymentContentType::Playlist->value,
                ...$body,
                'generatedAt' => now()->toIso8601String(),
                'status' => 'ready',
            ];
        }

        $deployment->loadMissing(['screenDesign', 'screenDesignVersion']);
        $design = $deployment->screenDesign;
        $version = $deployment->screenDesignVersion;
        $schema = is_array($version?->schema) ? $version->schema : [];
        $mediaIds = ScreenDesign::mediaIdsFromSchema($schema);
        $assets = $this->mediaAssets($screen->workspace_id, $mediaIds);

        $orientation = $screen->orientation
            ?? ($design !== null ? $design->orientation->value : null)
            ?? 'landscape';

        return [
            'manifestVersion' => 1,
            'screenId' => $screen->id,
            'contentSource' => ScreenContentSource::Deployment->value,
            'deploymentId' => $deployment->id,
            'deploymentVersion' => $deployment->versionLabel(),
            'contentType' => DeploymentContentType::ScreenDesign->value,
            'screenDesignId' => $deployment->screen_design_id,
            'screenDesignVersionId' => $deployment->screen_design_version_id,
            'canvas' => [
                'width' => $design !== null ? $design->canvas_width : 1920,
                'height' => $design !== null ? $design->canvas_height : 1080,
                'orientation' => $orientation,
            ],
            'schema' => $schema,
            'media' => $this->mediaRefs($assets, $mediaIds),
            'generatedAt' => now()->toIso8601String(),
            'status' => 'ready',
        ];
    }

    /**
     * Playlist body for a pinned published version (schedule or deployment).
     *
     * @return array<string, mixed>|null
     */
    public function playlistBody(Screen $screen, Playlist $playlist, PlaylistVersion $version): ?array
    {
        $version->loadMissing(['items.screenDesign', 'items.screenDesignVersion']);

        $items = $version->items->filter(fn (PlaylistItem $item) => $item->is_active)->values();

        if ($items->isEmpty()) {
            return null;
        }

        $mediaIdsByItem = [];
        $allMediaIds = [];
        foreach ($items as $item) {
            $schema = is_array($item->screenDesignVersion?->schema) ? $item->screenDesignVersion->schema : [];
            $ids = ScreenDesign::mediaIdsFromSchema($schema);
            $mediaIdsByItem[$item->id] = $ids;
            $allMediaIds = [...$allMediaIds, ...$ids];
        }

        $assets = $this->mediaAssets($screen->workspace_id, array_values(array_unique($allMediaIds)));

        $orientation = $playlist->orientation->value ?? $screen->orientation ?? 'landscape';

        return [
            'playlistId' => $playlist->id,
            'playlistVersionId' => $version->id,
            'orientation' => $orientation,
            'items' => $items->map(function (PlaylistItem $item) use ($assets, $mediaIdsByItem, $orientation): array {
                $design = $item->screenDesign;
                $designVersion = $item->screenDesignVersion;
                $schema = is_array($designVersion?->schema) ? $designVersion->schema : [];

                return [
                    'position' => $item->position,
                    'durationSeconds' => $item->duration_seconds,
                    'loopCount' => max(1, (int) $item->loop_count),
                    'transition' => $item->transition->value,
                    'transitionSpeed' => $item->transition_speed->value,
                    'transitionMs' => $item->transition_speed->milliseconds(),
                    'screenDesignId' => $item->screen_design_id,
                    'screenDesignVersionId' => $item->screen_design_version_id,
                    'name' => $design?->name,
                    'canvas' => [
                        'width' => $design !== null ? $design->canvas_width : 1920,
                        'height' => $design !== null ? $design->canvas_height : 1080,
                        'orientation' => $design?->orientation->value ?? $orientation,
                    ],
                    'schema' => $schema,
                    'media' => $this->mediaRefs($assets, $mediaIdsByItem[$item->id] ?? []),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(Screen $screen, string $status): array
    {
        return [
            'manifestVersion' => 1,
            'screenId' => $screen->id,
            'contentSource' => ScreenContentSource::None->value,
            'status' => $status,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Collect media refs from one or more content payloads.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array{id: int, url: string, mime: string|null, type: string|null, updatedAt: string|null, sizeBytes: int|null, cacheKey: string}>
     */
    public function collectAssets(Screen $screen, array $payloads): array
    {
        $ids = [];
        foreach ($payloads as $payload) {
            foreach ($this->mediaIdsInPayload($payload) as $id) {
                $ids[$id] = true;
            }
        }

        $mediaIds = array_map('intval', array_keys($ids));
        $assets = $this->mediaAssets($screen->workspace_id, $mediaIds);

        $out = [];
        foreach ($mediaIds as $id) {
            $asset = $assets->get($id);
            if ($asset === null) {
                continue;
            }
            $updated = $asset->updated_at?->toIso8601String();
            $out[] = [
                'id' => $asset->id,
                'url' => '/player/api/media/'.$asset->id,
                'mime' => $asset->mime_type,
                'type' => $asset->type->value,
                'updatedAt' => $updated,
                'sizeBytes' => $asset->size_bytes,
                'cacheKey' => $asset->id.'@'.($updated ?? '0'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    public function mediaIdsInPayload(array $payload): array
    {
        $ids = [];
        if (isset($payload['media']) && is_array($payload['media'])) {
            foreach (array_keys($payload['media']) as $key) {
                $ids[] = (int) $key;
            }
        }
        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                if (! is_array($item) || ! isset($item['media']) || ! is_array($item['media'])) {
                    continue;
                }
                foreach (array_keys($item['media']) as $key) {
                    $ids[] = (int) $key;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Rewrite media URLs in a payload to Cache Storage friendly absolute paths
     * (already relative) — used when injecting blob: URLs client-side.
     *
     * @param  list<int>  $mediaIds
     * @return Collection<int, MediaAsset>
     */
    public function mediaAssets(int $workspaceId, array $mediaIds): Collection
    {
        return MediaAsset::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $mediaIds ?: [0])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, MediaAsset>  $assets
     * @param  list<int>  $mediaIds
     * @return array<int|string, array<string, mixed>>
     */
    public function mediaRefs(Collection $assets, array $mediaIds): array
    {
        $media = [];
        foreach ($mediaIds as $mediaId) {
            $asset = $assets->get($mediaId);
            if ($asset === null) {
                continue;
            }
            $media[(string) $mediaId] = [
                'id' => $asset->id,
                'type' => $asset->type->value,
                'url' => '/player/api/media/'.$asset->id,
                'mime' => $asset->mime_type,
            ];
        }

        return $media;
    }
}
