export type PublishingAckState =
    | 'none'
    | 'live'
    | 'updating'
    | 'waiting'
    | 'publishing'
    | 'scheduled';

export type PublishingLiveRow = {
    screen_id: number;
    screen_name: string;
    orientation: string | null;
    operational_status: string;
    pairing_state: string;
    network_state: string;
    content_source: string;
    content_name: string | null;
    content_type: string | null;
    version_number: number | null;
    schedule_id: number | null;
    schedule_name: string | null;
    deployment_id: number | null;
    deployed_at: string | null;
    sync_state: string;
    ack_state: PublishingAckState;
    ack_label: string;
    window_ends_at: string | null;
    next_schedule: {
        id: number;
        name: string;
        starts_at: string;
        starts_at_local: string;
    } | null;
};

export type PublishingHistoryRow = {
    id: number;
    screen_id: number;
    screen_name: string | null;
    content_type: string;
    content_type_label: string;
    content_name: string | null;
    version_number: number | null;
    status: string;
    status_label: string;
    deployed_by_name: string | null;
    deployed_at: string | null;
    superseded_at: string | null;
    can_republish: boolean;
};

export type PublishingScreenOption = {
    id: number;
    name: string;
    orientation: string | null;
    operational_status: string;
    pairing_state: string;
    network_state: string;
    current_content: string | null;
    is_inactive: boolean;
};

export type PublishingContentOption = {
    id: number;
    name: string;
    orientation: string | null;
    version_number: number | null;
};

export type PublishingIndexProps = {
    live: PublishingLiveRow[];
    scheduled: PublishingLiveRow[];
    history: {
        data: PublishingHistoryRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: {
        q: string;
        status: string;
        type: string;
        screen: number | null;
        sort: string;
        direction: 'asc' | 'desc';
    };
    screens: PublishingScreenOption[];
    designs: PublishingContentOption[];
    playlists: PublishingContentOption[];
    can_publish: boolean;
    statuses: { value: string; label: string }[];
};
