export type MediaTypeValue =
    | 'image'
    | 'video'
    | 'text'
    | 'logo'
    | 'document'
    | 'link';

export type MediaSortValue =
    | 'newest'
    | 'oldest'
    | 'name_asc'
    | 'name_desc'
    | 'largest'
    | 'smallest';

export type MediaListItem = {
    id: number;
    type: MediaTypeValue;
    type_label: string;
    name: string;
    original_filename: string | null;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number | null;
    width: number | null;
    height: number | null;
    dimensions: string | null;
    duration_seconds: number | null;
    text_content: string | null;
    url: string | null;
    preview_url: string | null;
    download_url: string | null;
    is_file_based: boolean;
    is_editable_content: boolean;
    created_by_name: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type MediaFilters = {
    q: string;
    type: 'all' | MediaTypeValue;
    sort: MediaSortValue;
    view: 'grid' | 'list';
};

export type MediaCounts = {
    all: number;
    image: number;
    video: number;
    text: number;
    logo: number;
    document: number;
    link: number;
};
