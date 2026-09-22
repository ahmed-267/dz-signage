import type {
    PlaylistMediaMap,
    PlaylistSchema,
    PlaylistTransition,
    PlaylistTransitionSpeed,
} from '@/types/playlist';

/** Stored statuses. `ended` is derived server-side and only used for display. */
export type ScheduleStoredStatus = 'draft' | 'active' | 'paused' | 'archived';

export type ScheduleDisplayStatus = ScheduleStoredStatus | 'ended';

/** ISO-8601 weekday: 1 = Monday … 7 = Sunday. */
export type ScheduleDay = 1 | 2 | 3 | 4 | 5 | 6 | 7;

/** PHP arrays keyed 1…7 arrive as JSON objects. */
export type ScheduleDayLabels = Record<string, string>;

/** `App\Support\Schedules\ScheduleDefaults::forFrontend()`. */
export type ScheduleConfig = {
    default_priority: number;
    min_priority: number;
    max_priority: number;
    /** `HH:MM:00`. */
    default_start_time: string;
    default_end_time: string;
    default_days_of_week: number[];
    day_labels: ScheduleDayLabels;
    day_short_labels: ScheduleDayLabels;
    weekdays: number[];
    weekends: number[];
};

/** One resolved occurrence of a schedule. */
export type ScheduleWindow = {
    starts_at: string;
    ends_at: string;
    /** `YYYY-MM-DD HH:MM` in the schedule's own timezone. */
    starts_at_local: string;
    ends_at_local: string;
    timezone: string;
};

export type ScheduleScreenRef = {
    id: number;
    name: string;
    orientation: string | null;
    operational_status: string;
    location_id?: number | null;
    location_name?: string | null;
    network_state?: 'online' | 'offline';
};

export type ScheduleListItem = {
    id: number;
    name: string;
    description: string | null;
    status: ScheduleDisplayStatus;
    status_label: string;
    stored_status: ScheduleStoredStatus;
    has_ended: boolean;
    /** Active and inside a window right now. */
    is_live: boolean;
    priority: number;
    playlist_id: number | null;
    playlist_name: string | null;
    playlist_orientation: string | null;
    playlist_version_id: number | null;
    playlist_version_number: number | null;
    timezone: string;
    start_date: string | null;
    end_date: string | null;
    /** `HH:MM:SS`. */
    start_time: string;
    end_time: string;
    days_of_week: number[];
    day_labels: string[];
    crosses_midnight: boolean;
    duration_minutes: number;
    screen_count: number;
    screens: ScheduleScreenRef[];
    current_window: ScheduleWindow | null;
    next_window: ScheduleWindow | null;
    activated_at: string | null;
    created_by_name: string | null;
    updated_at: string | null;
    created_at: string | null;
    /** Active overlaps with other schedules (warning only). */
    conflict_count?: number;
};

export type ScheduleDetail = ScheduleListItem & {
    screen_ids: number[];
    upcoming: ScheduleWindow[];
};

/**
 * An active schedule that overlaps this one on shared screens. Overlaps are
 * warnings — `other_wins` says which side higher priority favours.
 */
export type ScheduleConflict = {
    id: number;
    name: string;
    status: ScheduleDisplayStatus;
    status_label: string;
    priority: number;
    playlist_name: string | null;
    timezone: string;
    start_time: string;
    end_time: string;
    days_of_week: number[];
    day_labels: string[];
    screen_ids: number[];
    screen_names: string[];
    other_wins: boolean;
    /** True when both schedules share the same priority number. */
    same_priority?: boolean;
};

/** Published playlists only — schedules may not play drafts. */
export type SchedulePlaylistOption = {
    id: number;
    name: string;
    orientation: string | null;
    orientation_label: string | null;
    published_version_id: number | null;
    published_version_number: number | null;
    item_count: number;
    /** Active-item published runtime in seconds. */
    total_duration_seconds?: number;
    /** Latest draft runtime when it differs from published. */
    draft_total_duration_seconds?: number | null;
    version_scope?: 'published';
    published_at: string | null;
    /** First active design thumb — used for the compact Content-step preview. */
    preview_item?: {
        screen_design_id: number;
        name: string | null;
        canvas_width: number | null;
        canvas_height: number | null;
        schema: PlaylistSchema;
    } | null;
};

export type ScheduleScreenOption = ScheduleScreenRef;

export type ScheduleLocationOption = {
    id: number;
    name: string;
};

export type ScheduleStatusOption = {
    value: string;
    label: string;
};

export type ScheduleFilters = {
    q: string;
    status: string;
    screen: number | null;
    playlist: number | null;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

export type PaginatedSchedules = {
    data: ScheduleListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from?: number | null;
        to?: number | null;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
};

/** One schedule occurrence laid out on the week grid, in workspace time. */
export type ScheduleCalendarBlock = {
    schedule_id: number;
    name: string;
    playlist_name: string | null;
    priority: number;
    status: ScheduleDisplayStatus;
    status_label: string;
    timezone: string;
    start_minutes: number;
    /** May exceed 1440 when the occurrence runs into the next day. */
    end_minutes: number;
    crosses_midnight: boolean;
    starts_at: string;
    ends_at: string;
};

export type ScheduleCalendarDay = {
    date: string;
    iso_weekday: number;
    label: string;
    short_label: string;
    is_today: boolean;
    blocks: ScheduleCalendarBlock[];
};

export type ScheduleCalendar = {
    timezone: string;
    week_start: string;
    week_end: string;
    previous_week: string;
    next_week: string;
    days: ScheduleCalendarDay[];
};

export type SchedulesIndexProps = {
    schedules: PaginatedSchedules;
    filters: ScheduleFilters;
    calendar: ScheduleCalendar;
    statuses: ScheduleStatusOption[];
    screens: ScheduleScreenOption[];
    playlists: SchedulePlaylistOption[];
    config: ScheduleConfig;
    workspace_timezone: string;
    can_manage: boolean;
};

/** `ScheduleController::formProps()` — shared by create and edit. */
export type ScheduleFormProps = {
    playlists: SchedulePlaylistOption[];
    screens: ScheduleScreenOption[];
    /** Present on create/edit; empty when the workspace has no locations. */
    locations?: ScheduleLocationOption[];
    timezones: string[];
    workspace_timezone: string;
    day_labels: ScheduleDayLabels;
    day_short_labels: ScheduleDayLabels;
    config: ScheduleConfig;
    statuses: ScheduleStatusOption[];
};

export type ScheduleCreateProps = ScheduleFormProps;

export type ScheduleEditProps = ScheduleFormProps & {
    schedule: ScheduleDetail;
    can_edit: boolean;
};

/** Item in the pinned playlist version, as sent by the preview endpoint. */
export type SchedulePreviewItem = {
    position: number;
    screen_design_id: number;
    screen_design_version_id: number | null;
    name: string | null;
    canvas_width: number | null;
    canvas_height: number | null;
    duration_seconds: number;
    loop_count?: number;
    effective_duration_seconds?: number;
    transition: PlaylistTransition;
    transition_speed: PlaylistTransitionSpeed;
    schema: PlaylistSchema;
};

/** `GET /app/schedules/{schedule}/preview`. */
export type SchedulePreviewPayload = {
    schedule: ScheduleDetail;
    items: SchedulePreviewItem[];
    total_duration_seconds: number;
    media_map: PlaylistMediaMap;
    upcoming: ScheduleWindow[];
    conflicts: ScheduleConflict[];
};

/** `POST /app/schedules/{schedule}/conflicts`. */
export type ScheduleConflictsPayload = {
    conflicts: ScheduleConflict[];
};

/** Editable schedule fields, as accepted by store / update. */
export type ScheduleFormValues = {
    name: string;
    description: string;
    playlist_id: string;
    screen_ids: number[];
    timezone: string;
    start_date: string;
    end_date: string;
    /** `HH:MM` — the backend normalises to `HH:MM:00`. */
    start_time: string;
    end_time: string;
    days_of_week: number[];
    priority: number;
};
