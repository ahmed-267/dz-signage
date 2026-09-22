import type { LayoutSchema } from '@/types/layout-schema';

export type ScreenDesignStatus = 'draft' | 'published' | 'archived';

export type ScreenDesignMediaRef = {
    id: number;
    name: string;
    type: string;
    url: string | null;
    text_content: string | null;
    mime_type: string | null;
    width: number | null;
    height: number | null;
};

export type ScreenDesignListItem = {
    id: number;
    name: string;
    orientation: string;
    orientation_label: string;
    status: ScreenDesignStatus;
    status_label: string;
    canvas_width: number;
    canvas_height: number;
    source_template_name: string | null;
    schema: LayoutSchema | Record<string, unknown> | null;
    created_by_name: string | null;
    updated_at: string | null;
    created_at: string | null;
};

export type ScreenDesignFilters = {
    q: string;
    orientation: string;
    status: string;
    sort: string;
};

export type PaginatedScreenDesigns = {
    data: ScreenDesignListItem[];
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

export type ScreenDesignsIndexProps = {
    designs: PaginatedScreenDesigns;
    filters: ScreenDesignFilters;
    can_manage: boolean;
    can_publish_to_screens?: boolean;
    screens?: {
        id: number;
        name: string;
        orientation: string | null;
        operational_status: string;
    }[];
};

export type ScreenDesignEditorPayload = {
    id: number;
    name: string;
    orientation: string;
    status: ScreenDesignStatus;
    canvas_width: number;
    canvas_height: number;
    source_template_id: number | null;
    source_template_name: string | null;
    published_version_id: number | null;
    latest_version_number: number | null;
    latest_published: boolean;
    schema: LayoutSchema | Record<string, unknown> | null;
    updated_at: string | null;
};

export type ScreenDesignEditProps = {
    design: ScreenDesignEditorPayload;
    can_edit: boolean;
    media_map: Record<string | number, ScreenDesignMediaRef>;
    media_picker: ScreenDesignMediaRef[];
};
