<?php

namespace App\Support\Rendering;

use App\Models\MediaAsset;

/**
 * Builds the `media_map` that `LayoutRenderer` needs to resolve
 * `props.mediaAssetId` references in a LayoutSchema v1 payload.
 */
final class MediaMap
{
    /**
     * @param  list<int>  $mediaIds
     * @return array<string, array<string, mixed>>
     */
    public static function build(int $workspaceId, array $mediaIds): array
    {
        $mediaIds = array_values(array_unique(array_filter($mediaIds)));

        if ($mediaIds === []) {
            return [];
        }

        return MediaAsset::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $mediaIds)
            ->get()
            ->keyBy('id')
            ->map(fn (MediaAsset $media) => [
                'id' => $media->id,
                'name' => $media->name,
                'type' => $media->type->value,
                'url' => $media->publicUrl() ?? $media->url,
                'text_content' => $media->text_content,
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
            ])
            ->all();
    }
}
