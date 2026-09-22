export type LocationManagerOption = {
    id: number;
    name: string;
    email: string | null;
};

export type LocationListItem = {
    id: number;
    name: string;
    city: string | null;
    region: string | null;
    postcode: string | null;
    country: string;
    timezone: string;
    address: string | null;
    archived_at: string | null;
    is_archived: boolean;
    screen_count: number;
    online_count: number;
    offline_count: number;
    manager_count: number;
    updated_at: string | null;
    created_at: string | null;
};

export type LocationDetail = LocationListItem & {
    address_line1: string | null;
    address_line2: string | null;
    notes: string | null;
    managers: LocationManagerOption[];
    manager_ids: number[];
    creator_name: string | null;
};

export type LocationScreenRow = {
    id: number;
    name: string;
    orientation: string | null;
    operational_status: string;
    operational_status_label: string;
    pairing_state: string;
    network_state: string;
    health: string;
    health_label: string;
    last_seen_at: string | null;
};

export type LocationIndexFilters = {
    q: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

export type LocationsIndexProps = {
    locations: {
        data: LocationListItem[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    filters: LocationIndexFilters;
    can_manage: boolean;
    workspace_timezone: string;
};

export type LocationFormProps = {
    defaults: {
        country: string;
        timezone: string;
    };
    timezones: string[];
    manager_options: LocationManagerOption[];
    can_assign_managers: boolean;
    workspace_timezone: string;
};

export type LocationCreateProps = LocationFormProps;

export type LocationEditProps = LocationFormProps & {
    location: LocationDetail;
};

export type LocationShowProps = {
    location: LocationDetail;
    screens: LocationScreenRow[];
    can_manage: boolean;
    can_assign_managers: boolean;
    can_delete: boolean;
};
