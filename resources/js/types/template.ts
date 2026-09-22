import type { LayoutSchema } from '@/types/layout-schema';

export type TemplateTab = 'all' | 'dz' | 'favourites';

export type TemplateListItem = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    category_label: string;
    industry: string | null;
    orientation: string;
    orientation_label: string;
    theme: string;
    theme_label: string;
    status: string;
    status_label: string;
    canvas_width: number;
    canvas_height: number;
    is_platform: boolean;
    is_favourited: boolean;
    thumbnail_path: string | null;
    schema: LayoutSchema | Record<string, unknown> | null;
    created_by_name: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type TemplateFilters = {
    tab: string;
    q: string;
    industry: string;
    category: string;
    orientation: string;
    theme: string;
};

export type TemplateCounts = {
    all: number;
    dz: number;
    favourites: number;
};

export type TemplateOption = {
    value: string;
    label: string;
};

export type TemplateThemeOption = TemplateOption & {
    background: string;
};

export type PaginatedTemplates = {
    data: TemplateListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};

export type TemplatesIndexProps = {
    templates: PaginatedTemplates;
    filters: TemplateFilters;
    counts: TemplateCounts;
    /** When true, managers can Use Template → create a Screen Design */
    use_template_available: boolean;
    categories: TemplateOption[];
    orientations: TemplateOption[];
    themes: TemplateThemeOption[];
    industries: TemplateOption[];
};

export type AdminTemplateRow = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    category_label: string;
    industry: string | null;
    orientation: string;
    theme: string;
    theme_label?: string;
    status: string;
    status_label: string;
    canvas_width?: number;
    canvas_height?: number;
    schema?: Record<string, unknown> | null;
    created_by_name: string | null;
    updated_at: string | null;
};

export type AdminTemplatesIndexProps = {
    templates: {
        data: AdminTemplateRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    media_map?: Record<
        string,
        {
            id: number;
            name: string;
            type: string;
            url: string | null;
        }
    >;
    filters?: {
        per_page?: number;
    };
    categories: TemplateOption[];
    orientations: TemplateOption[];
    themes: TemplateOption[];
    industries: TemplateOption[];
};

export type AdminTemplateEditProps = {
    template: {
        id: number;
        name: string;
        description: string | null;
        category: string;
        industry: string | null;
        orientation: string;
        theme: string;
        status: string;
        canvas_width: number;
        canvas_height: number;
        published_version_id: number | null;
        latest_version_number: number | null;
        latest_published: boolean;
        schema: LayoutSchema | Record<string, unknown> | null;
    };
    categories: TemplateOption[];
    themes: TemplateOption[];
    industries: TemplateOption[];
};
