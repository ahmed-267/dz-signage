export type BrandKitShared = {
    colors: string[];
    fonts: {
        heading: string;
        body: string;
        heading_stack: string;
        body_stack: string;
    };
    logo_url: string | null;
    logo_media_asset_id: number | null;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    background_color: string;
    text_color: string;
    name: string | null;
    tagline: string | null;
} | null;

export type BrandKitForm = {
    id: number;
    name: string | null;
    tagline: string | null;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    background_color: string;
    text_color: string;
    heading_font: string;
    body_font: string;
    logo_media_asset_id: number | null;
    secondary_logo_media_asset_id: number | null;
    logo_url: string | null;
    secondary_logo_url: string | null;
};

export type BrandKitMediaOption = {
    id: number;
    name: string;
    type: string;
    preview_url: string | null;
};
