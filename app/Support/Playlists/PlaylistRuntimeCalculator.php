<?php

namespace App\Support\Playlists;

use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use Illuminate\Support\Collection;

/**
 * Single source of truth for playlist runtime math.
 *
 * Effective seconds for an active item = duration_seconds × max(1, loop_count).
 * Inactive items contribute 0.
 *
 * Transitions are NOT included — fade/slide timing is cosmetic and does not
 * change the configured loop runtime shown in the library, editor, or schedules.
 */
class PlaylistRuntimeCalculator
{
    /**
     * Per-item contribution to playlist runtime.
     *
     * Accepts a PlaylistItem model or an array shaped like editor/API payloads
     * (`duration_seconds`, `loop_count`, optional `is_active`).
     *
     * @param  PlaylistItem|array<string, mixed>  $item
     */
    public static function itemEffectiveSeconds(PlaylistItem|array $item): int
    {
        if (is_array($item)) {
            $active = ! array_key_exists('is_active', $item) || (bool) $item['is_active'];
            if (! $active) {
                return 0;
            }

            $duration = max(0, (int) ($item['duration_seconds'] ?? 0));
            $loops = self::effectiveLoopCount(
                array_key_exists('loop_count', $item) ? (int) $item['loop_count'] : null,
            );

            return $duration * $loops;
        }

        if (! $item->is_active) {
            return 0;
        }

        return max(0, (int) $item->duration_seconds) * self::effectiveLoopCount((int) $item->loop_count);
    }

    /**
     * Sum of active-item effective seconds for a version or item collection.
     *
     * @param  PlaylistVersion|Collection<int, PlaylistItem|array<string, mixed>>|iterable<int, PlaylistItem|array<string, mixed>>|null  $versionOrItems
     */
    public static function totalSeconds(PlaylistVersion|iterable|null $versionOrItems): int
    {
        if ($versionOrItems === null) {
            return 0;
        }

        $items = $versionOrItems instanceof PlaylistVersion
            ? $versionOrItems->items
            : $versionOrItems;

        $total = 0;
        foreach ($items as $item) {
            /** @var PlaylistItem|array<string, mixed> $item */
            $total += self::itemEffectiveSeconds($item);
        }

        return $total;
    }

    /**
     * Loop count used in runtime math and by the Player (at least 1).
     */
    public static function effectiveLoopCount(?int $loopCount): int
    {
        return max(1, (int) ($loopCount ?? PlaylistDefaults::loopCount()));
    }
}
