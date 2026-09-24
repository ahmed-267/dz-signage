import type { LayoutSchema } from '@/types/layout-schema';
import type { ScreenDesignMediaRef } from '@/types/screen-design';

export type PlaylistStatus = 'draft' | 'published' | 'archived';

export type PlaylistTransition = 'none' | 'fade' | 'slide_left' | 'slide_right';

export type PlaylistTransitionSpeed = 'fast' | 'normal' | 'slow';

/** Layout schema as delivered by the backend — normalized before rendering. */
export type PlaylistSchema = LayoutSchema | Record<string, unknown> | null;

/** `props.mediaAssetId` → asset, keyed by id. */
export type PlaylistMediaMap = Record<string | number, ScreenDesignMediaRef>;

/** `App\Support\Playlists\PlaylistDefaults::forFrontend()`. */
export type PlaylistConfig = {
    default_duration_seconds: number;
    min_duration_seconds: number;
    max_duration_seconds: number;
    default_loop_count: number;
    min_loop_count: number;
    max_loop_count: number;
    default_transition: PlaylistTransition;
    default_transition_speed: PlaylistTransitionSpeed;
    /** Mirrors `PlaylistTransitionSpeed::milliseconds()` — visual only. */
    transition_speed_ms: Record<PlaylistTransitionSpeed, number>;
    transitions: { value: PlaylistTransition; label: string }[];
    transition_speeds: { value: PlaylistTransitionSpeed; label: string }[];
};

export type PlaylistItem = {
    id: number;
    position: number;
    screen_design_id: number;
    screen_design_version_id: number | null;
    /** Screen Design name. */
    name: string | null;
    orientation: string | null;
    canvas_width: number | null;
    canvas_height: number | null;
    design_version_number: number | null;
    duration_seconds: number;
    /** How many times this item plays before the next item. */
    loop_count: number;
    /** duration_seconds × loop_count (active items only). */
    effective_duration_seconds: number;
    transition: PlaylistTransition;
    transition_label: string;
    transition_speed: PlaylistTransitionSpeed;
    transition_speed_label: string;
    is_active: boolean;
    schema: PlaylistSchema;
};

/** Published Screen Design offered by the editor's design picker. */
export type PlaylistDesignOption = {
    id: number;
    name: string;
    orientation: string;
    orientation_label: string;
    canvas_width: number;
    canvas_height: number;
    published_version_id: number | null;
    published_version_number: number | null;
    schema: PlaylistSchema;
    updated_at: string | null;
};

export type PublishedDesignPage = {
    data: PlaylistDesignOption[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        q: string;
        orientation: string;
    };
    media_map: PlaylistMediaMap;
};

/** First (or first active) item, used for the library card preview. */
export type PlaylistPreviewThumb = {
    screen_design_id: number;
    name: string | null;
    canvas_width: number | null;
    canvas_height: number | null;
    schema: PlaylistSchema;
    duration_seconds?: number;
};

/** Ordered design thumbs for the horizontal playlist row sequence. */
export type PlaylistPreviewItem = PlaylistPreviewThumb & {
    duration_seconds: number;
    loop_count?: number;
    effective_duration_seconds?: number;
};

export type PlaylistListItem = {
    id: number;
    name: string;
    description: string | null;
    orientation: string | null;
    orientation_label: string | null;
    status: PlaylistStatus;
    status_label: string;
    item_count: number;
    active_item_count: number;
    total_duration_seconds: number;
    /** Cards always reflect the latest (draft) version runtime. */
    version_scope?: 'latest' | 'published';
    latest_version_id?: number | null;
    assigned_tv_count: number;
    schedule_count: number;
    published_version_id: number | null;
    published_at: string | null;
    latest_version_number: number | null;
    has_unpublished_changes: boolean;
    created_by_name: string | null;
    preview_item: PlaylistPreviewThumb | null;
    preview_items: PlaylistPreviewItem[];
    updated_at: string | null;
    created_at: string | null;
};

export type PlaylistFilters = {
    q: string;
    status: string;
    orientation: string;
    sort: string;
};

export type PaginatedPlaylists = {
    data: PlaylistListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
};

export type PlaylistScreenOption = {
    id: number;
    name: string;
    orientation: string | null;
    operational_status: string;
};

export type PlaylistsIndexProps = {
    playlists: PaginatedPlaylists;
    filters: PlaylistFilters;
    config: PlaylistConfig;
    can_manage: boolean;
    can_publish_to_screens?: boolean;
    screens?: PlaylistScreenOption[];
    /** Resolves `props.mediaAssetId` for card previews. */
    media_map?: PlaylistMediaMap;
};

export type PlaylistEditorPayload = {
    id: number;
    name: string;
    description: string | null;
    orientation: string | null;
    orientation_label: string | null;
    status: PlaylistStatus;
    status_label: string;
    published_version_id: number | null;
    published_at: string | null;
    latest_version_id: number | null;
    latest_version_number: number | null;
    latest_published: boolean;
    item_count: number;
    active_item_count: number;
    total_duration_seconds: number;
    version_scope?: 'latest' | 'published';
    items: PlaylistItem[];
    updated_at: string | null;
};

export type PlaylistEditProps = {
    playlist: PlaylistEditorPayload;
    config: PlaylistConfig;
    can_edit: boolean;
    can_publish_to_screens?: boolean;
    screens?: PlaylistScreenOption[];
    /** Published Screen Designs only — playlists may not reference drafts. */
    published_designs: PublishedDesignPage;
    media_map?: PlaylistMediaMap;
};

/** `GET /app/playlists/{playlist}/preview` — JSON, active items only. */
export type PlaylistPreviewPayload = {
    id: number;
    name: string;
    orientation: string | null;
    status: PlaylistStatus;
    playlist_version_id: number | null;
    version_number: number | null;
    version_scope?: 'latest' | 'published';
    total_duration_seconds: number;
    items: PlaylistItem[];
    media_map: PlaylistMediaMap;
};

/** Item payload for `PATCH /app/playlists/{playlist}` — order is position. */
export type PlaylistItemInput = {
    screen_design_id: number;
    screen_design_version_id: number | null;
    duration_seconds: number;
    loop_count: number;
    transition: PlaylistTransition;
    transition_speed: PlaylistTransitionSpeed;
    is_active: boolean;
};
