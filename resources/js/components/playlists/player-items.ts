import type { PlaylistPlayerItem } from '@/components/playlists/playlist-preview-player';
import type { LayoutMediaMap } from '@/components/rendering/layout-renderer';
import {
    isLayoutSchema,
    normalizeLayoutSchema,
    type LayoutSchema,
} from '@/types/layout-schema';
import type {
    PlaylistItem,
    PlaylistMediaMap,
    PlaylistSchema,
} from '@/types/playlist';

export function resolveItemSchema(schema: PlaylistSchema): LayoutSchema | null {
    return isLayoutSchema(schema) ? normalizeLayoutSchema(schema) : null;
}

/**
 * Playlist items → player items. Items without a renderable schema are
 * dropped: the Player must never show a broken frame.
 */
export function toPlayerItems(items: PlaylistItem[]): PlaylistPlayerItem[] {
    const playerItems: PlaylistPlayerItem[] = [];

    for (const item of items) {
        const schema = resolveItemSchema(item.schema);
        if (!schema) {
            continue;
        }

        playerItems.push({
            key: String(item.id),
            name: item.name ?? `Item ${item.position}`,
            schema,
            duration_seconds: item.duration_seconds,
            loop_count: Math.max(1, item.loop_count ?? 1),
            transition: item.transition,
            transition_speed: item.transition_speed,
            is_active: item.is_active,
        });
    }

    return playerItems;
}

/**
 * Media maps arrive keyed by string ids from JSON. `props.mediaAssetId` is
 * numeric, so both key types are registered.
 */
export function toLayoutMediaMap(
    map: PlaylistMediaMap | undefined,
): LayoutMediaMap {
    if (!map) {
        return {};
    }

    const out: LayoutMediaMap = {};
    for (const [key, value] of Object.entries(map)) {
        out[key] = value;
        const numeric = Number(key);
        if (!Number.isNaN(numeric)) {
            out[numeric] = value;
        }
    }
    return out;
}
