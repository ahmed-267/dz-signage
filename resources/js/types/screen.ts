import type { ScreenLicenceSummary } from '@/types/billing';

export type ScreenOperationalStatus = 'active' | 'inactive';

export type ScreenPairingState = 'connected' | 'disconnected';

export type ScreenNetworkState = 'online' | 'offline';

export type ScreenHealth = 'healthy' | 'attention' | 'offline';

export type ScreenContentSync = 'up_to_date' | 'out_of_sync' | 'unknown';

export type PlayerPlaybackState =
    | 'ready'
    | 'no_content'
    | 'rendering'
    | 'inactive'
    | 'error'
    | 'pairing';

export type ScreenOrientation = 'landscape' | 'portrait';

export type PublishedDesignOption = {
    id: number;
    name: string;
    orientation: string;
};

export type ScreenListItem = {
    id: number;
    name: string;
    orientation: string | null;
    location_id?: number | null;
    location_name?: string | null;
    operational_status: ScreenOperationalStatus;
    operational_status_label: string;
    pairing_state: ScreenPairingState;
    network_state: ScreenNetworkState;
    health: ScreenHealth;
    health_label: string;
    content_sync: ScreenContentSync;
    content_sync_label: string;
    orientation_mismatch: boolean;
    current_design_name: string | null;
    current_design_orientation: string | null;
    current_version_number: number | null;
    deployed_at: string | null;
    paired_at: string | null;
    last_seen_at: string | null;
    player_version: string | null;
    viewport_width: number | null;
    viewport_height: number | null;
    reported_orientation: string | null;
    playback_state: string | null;
    updated_at: string | null;
    created_at: string | null;
    content_source?: ScreenContentSource;
    content_source_label?: string;
    content_type?: string | null;
    content_name?: string | null;
    /** Resolver-backed Now Showing payload (Schedule → Deployment → none). */
    now_showing?: ScreenNowShowing | null;
};

export type ScreenNowShowing = {
    content_source: ScreenContentSource;
    content_source_label: string;
    content_name: string | null;
    content_type: 'schedule' | 'playlist' | 'screen_design' | null;
    content_type_label: string | null;
    version_number: number | null;
    playlist_item_count: number | null;
    schedule_id: number | null;
    schedule_name: string | null;
    schedule_priority: number | null;
    window_ends_at: string | null;
    window_ends_at_local: string | null;
    deployment_id: number | null;
    deployed_at: string | null;
    sync_state: ScreenContentSync;
    sync_state_label: string;
    ack_state: string;
    ack_label: string;
    next_schedule: {
        id: number;
        name: string;
        starts_at_local: string;
    } | null;
};

export type ScreenDeviceDetail = {
    id: number;
    device_identifier: string | null;
    device_name: string | null;
    paired_at: string | null;
    last_seen_at: string | null;
    revoked_at: string | null;
    player_version: string | null;
    viewport_width: number | null;
    viewport_height: number | null;
    reported_orientation: string | null;
    playback_state: string | null;
    last_error_code: string | null;
    reported_deployment_id: number | null;
    platform_meta: Record<string, unknown> | null;
    offline?: {
        cache_ready: boolean;
        package_version: string | null;
        last_sync_at: string | null;
        last_offline_at: string | null;
        sync_error: string | null;
        reported_at: string | null;
    } | null;
};

export type ScreenDeploymentSummary = {
    id: number;
    status: string;
    design_name: string | null;
    design_orientation?: string | null;
    version_number: number | null;
    deployed_at: string | null;
};

export type ScreenHeartbeatRow = {
    id: number;
    recorded_at: string | null;
    playback_state: string | null;
    error_code: string | null;
    deployment_id: number | null;
    viewport_width?: number | null;
    viewport_height?: number | null;
    orientation: string | null;
    player_version: string | null;
};

/**
 * What is driving the screen right now. `ScreenContentResolver` ranks a
 * matching Schedule over the always-on Deployment.
 */
export type ScreenContentSource = 'schedule' | 'deployment' | 'none';

/** A schedule occurrence on this screen, as shaped by `ScreenController`. */
export type ScreenScheduleSummary = {
    id: number;
    name: string;
    playlist_name: string | null;
    priority: number;
    timezone: string;
    starts_at: string;
    ends_at: string;
    /** `YYYY-MM-DD HH:MM` in the schedule's own timezone. */
    starts_at_local: string;
    ends_at_local: string;
};

export type ScreenDetail = ScreenListItem & {
    workspace_name: string | null;
    location?: {
        id: number;
        name: string;
        city: string | null;
        timezone: string;
    } | null;
    device: ScreenDeviceDetail | null;
    current_deployment: ScreenDeploymentSummary | null;
    deployments: ScreenDeploymentSummary[];
    content_source: ScreenContentSource;
    content_source_label: string;
    /** The schedule playing now, or null when a Deployment is driving. */
    current_schedule: ScreenScheduleSummary | null;
    /** The next occurrence across every active schedule on this screen. */
    next_schedule: ScreenScheduleSummary | null;
};

export type ScreenStateFilter =
    | 'all'
    | 'online'
    | 'offline'
    | 'active'
    | 'inactive'
    | 'connected'
    | 'disconnected';

export type ScreenSort = 'name' | 'seen' | 'created' | 'updated';

export type ScreenIndexFilters = {
    q: string;
    filter: string;
    sort: string;
    direction: 'asc' | 'desc';
    location?: number | null;
};

export type ScreenLocationOption = {
    id: number;
    name: string;
    city: string | null;
};

export type ScreenCounts = {
    all: number;
    online: number;
    offline: number;
    active: number;
    inactive: number;
    connected: number;
    disconnected: number;
};

export type ScreensIndexProps = {
    screens: ScreenListItem[];
    filters: ScreenIndexFilters;
    counts: ScreenCounts;
    published_designs: PublishedDesignOption[];
    published_playlists?: PublishedDesignOption[];
    locations?: ScreenLocationOption[];
    require_location?: boolean;
    can_manage: boolean;
    can_publish: boolean;
    /** Null unless the viewer can see billing and licences apply. */
    licences: ScreenLicenceSummary | null;
    workspace_name: string;
    /** Absolute Player URL for pairing instructions (browser / TV). */
    player_url: string;
    heartbeat_interval_seconds: number;
    online_threshold_seconds: number;
};

export type ScreenShowProps = {
    screen: ScreenDetail;
    recent_heartbeats: ScreenHeartbeatRow[];
    published_designs: PublishedDesignOption[];
    published_playlists?: PublishedDesignOption[];
    locations?: ScreenLocationOption[];
    can_manage: boolean;
    can_publish: boolean;
    workspace_name: string | null;
    workspace_timezone?: string | null;
};

export type ScreenPairProps = {
    pairing: {
        public_id: string;
        status: 'pending' | 'expired' | 'claimed';
        expires_at: string | null;
    };
    locations?: ScreenLocationOption[];
    require_location?: boolean;
    workspace_name: string | null;
    can_manage: boolean;
};

export type AdminScreenRow = {
    id: number;
    name: string;
    workspace_name: string | null;
    operational_status: string;
    pairing_state: string;
    network_state: string;
    health: ScreenHealth;
    health_label: string;
    current_design_name: string | null;
    paired_at: string | null;
    last_seen_at: string | null;
    created_at: string | null;
};

export type AdminScreenFilter = ScreenStateFilter | 'attention';

export type AdminScreensIndexProps = {
    screens: {
        data: AdminScreenRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    filters: {
        q: string;
        filter: string;
        sort: string;
        direction: 'asc' | 'desc';
        per_page?: number;
    };
};

export type AdminScreenDetail = {
    id: number;
    name: string;
    workspace_name: string | null;
    orientation: string | null;
    operational_status: string;
    pairing_state: string;
    network_state: string;
    health: ScreenHealth;
    health_label: string;
    content_sync: ScreenContentSync;
    content_sync_label: string;
    orientation_mismatch: boolean;
    current_design_name: string | null;
    current_version_number: number | null;
    deployed_at: string | null;
    paired_at: string | null;
    last_seen_at: string | null;
    created_at: string | null;
    device: {
        device_identifier: string | null;
        player_version: string | null;
        viewport_width: number | null;
        viewport_height: number | null;
        reported_orientation: string | null;
        playback_state: string | null;
        last_error_code: string | null;
        reported_deployment_id: number | null;
    } | null;
    recent_heartbeats: ScreenHeartbeatRow[];
};

export type AdminScreenShowProps = {
    screen: AdminScreenDetail;
};

export type AdminScreenHealthRow = {
    id: number;
    name: string;
    workspace_name: string | null;
    location_name?: string | null;
    operational_status: string;
    pairing_state: string;
    network_state: string;
    health: ScreenHealth;
    health_label: string;
    content_sync?: string;
    content_sync_label?: string;
    current_design_name: string | null;
    player_version: string | null;
    last_seen_at: string | null;
    heartbeat_age_seconds?: number | null;
    playback_state: string | null;
    last_error_code: string | null;
    offline_cache_ready?: boolean;
    offline_sync_error?: string | null;
    last_content_sync_at?: string | null;
};

export type AdminScreenHealthCounts = {
    total: number;
    online: number;
    offline: number;
    inactive: number;
    attention: number;
    connected: number;
    offline_ready: number;
};

export type AdminScreenHealthProps = {
    counts: AdminScreenHealthCounts;
    screens: AdminScreenHealthRow[];
    filters: {
        filter: string;
    };
    health_window_seconds?: number;
    heartbeat_interval_seconds?: number;
};
