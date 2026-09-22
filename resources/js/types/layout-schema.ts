/**
 * Shared layout schema v1 — used by Template Builder, Screen Design Editor,
 * Preview, and TV Player. Do not invent a second format for Screen Designs.
 */

export const LAYOUT_SCHEMA_VERSION = 1 as const;

export type LayoutOrientation = 'landscape' | 'portrait';

export type LayoutElementType =
    | 'text'
    | 'image'
    | 'video'
    | 'logo'
    | 'panel'
    | 'shape'
    | 'widget';

/** Semantic Brand Kit bindings for Use Template + Apply Brand Kit. */
export type BrandBinding =
    | 'brand.business_name'
    | 'brand.tagline'
    | 'brand.logo'
    | 'brand.primary_color'
    | 'brand.secondary_color'
    | 'brand.accent_color'
    | 'brand.background_color'
    | 'brand.text_color'
    | 'brand.heading_font'
    | 'brand.body_font';

export const BRAND_BINDINGS: readonly BrandBinding[] = [
    'brand.business_name',
    'brand.tagline',
    'brand.logo',
    'brand.primary_color',
    'brand.secondary_color',
    'brand.accent_color',
    'brand.background_color',
    'brand.text_color',
    'brand.heading_font',
    'brand.body_font',
] as const;

export type LayoutBackground = {
    type: 'color' | 'image';
    /** Color hex/rgb when type is color; optional fallback when type is image. */
    value: string;
    /** Media asset id when type is image (resolved via mediaMap). */
    mediaAssetId?: string | number;
    /** Optional Brand Kit colour binding for canvas background. */
    brandBinding?: BrandBinding;
};

export type LayoutCanvas = {
    width: number;
    height: number;
    orientation: LayoutOrientation;
    background: LayoutBackground;
};

/**
 * Common element props (v1). All optional; stored on LayoutElement.props.
 *
 * - mediaAssetId — workspace MediaAsset id (images, videos, logos)
 * - text — display text (falls back to element.name)
 * - fontFamily, fontSize, fontWeight, color, textAlign, lineHeight, letterSpacing
 * - opacity — 0–1
 * - objectFit — cover | contain | fill (image/video/logo)
 * - borderRadius — px
 * - muted, loop — video playback hints
 * - fill — panel/shape background color
 * - shape — rect | circle (shape elements)
 * - visible — false hides the element (default true)
 * - brandBinding — optional semantic Brand Kit field (Use Template / Apply Brand Kit)
 */
export type LayoutElementProps = {
    mediaAssetId?: string | number;
    text?: string;
    fontFamily?: string;
    fontSize?: number;
    fontWeight?: number | string;
    color?: string;
    textAlign?: 'left' | 'center' | 'right';
    lineHeight?: number | string;
    letterSpacing?: number | string;
    opacity?: number;
    objectFit?: 'cover' | 'contain' | 'fill';
    borderRadius?: number;
    muted?: boolean;
    loop?: boolean;
    fill?: string;
    shape?: 'rect' | 'circle';
    visible?: boolean;
    /** Semantic Brand Kit field applied on Use Template / Apply Brand Kit. */
    brandBinding?: BrandBinding;
    /** Widget kind: clock | countdown | weather | news | calendar | alert | info_card | embed */
    widgetType?: string;
    /** Legacy display name (e.g. "Clock") — resolved via registry mapping. */
    widgetName?: string;
    /** Widget-specific configuration object. */
    config?: Record<string, unknown>;
    [key: string]: unknown;
};

export type LayoutElement = {
    id: string;
    type: LayoutElementType;
    name: string;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation?: number;
    zIndex?: number;
    locked?: boolean;
    editable?: boolean;
    props?: LayoutElementProps;
};

/** Resolve mediaAssetId → asset URL / metadata for canvas content. */
export type LayoutMediaMapEntry = {
    url?: string | null;
    type?: string;
    text_content?: string | null;
};

export type LayoutMediaMap = Record<string | number, LayoutMediaMapEntry>;

export type LayoutSchema = {
    schemaVersion: number;
    canvas: LayoutCanvas;
    theme: string;
    elements: LayoutElement[];
};

export function isLayoutSchema(value: unknown): value is LayoutSchema {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const schema = value as Partial<LayoutSchema>;

    return (
        typeof schema.schemaVersion === 'number' &&
        !!schema.canvas &&
        typeof schema.canvas.width === 'number' &&
        typeof schema.canvas.height === 'number' &&
        Array.isArray(schema.elements)
    );
}

const LEGACY_PROP_KEYS = [
    'text',
    'fill',
    'color',
    'fontSize',
    'fontWeight',
    'fontFamily',
    'textAlign',
    'lineHeight',
    'letterSpacing',
    'opacity',
    'borderRadius',
    'objectFit',
    'muted',
    'loop',
    'mediaAssetId',
    'placeholder',
    'brandBinding',
] as const;

/**
 * Upgrade legacy flat element fields into props so card/preview/editor match.
 */
export function normalizeLayoutSchema(schema: LayoutSchema): LayoutSchema {
    return {
        ...schema,
        elements: schema.elements.map((element, index) => {
            const raw = element as LayoutElement & Record<string, unknown>;
            const props: LayoutElementProps = { ...element.props };

            for (const key of LEGACY_PROP_KEYS) {
                if (
                    Object.prototype.hasOwnProperty.call(raw, key) &&
                    props[key] === undefined
                ) {
                    props[key] = raw[key] as never;
                }
            }

            const name =
                typeof element.name === 'string' && element.name.length > 0
                    ? element.name
                    : typeof props.text === 'string' && props.text.length > 0
                      ? props.text
                      : `${element.type} ${index + 1}`;

            return {
                ...element,
                name,
                zIndex: element.zIndex ?? index + 1,
                props,
            };
        }),
    };
}

export function blankLayoutSchema(
    orientation: LayoutOrientation,
    theme: string,
    background: string,
): LayoutSchema {
    const landscape = orientation === 'landscape';

    return {
        schemaVersion: LAYOUT_SCHEMA_VERSION,
        canvas: {
            width: landscape ? 1920 : 1080,
            height: landscape ? 1080 : 1920,
            orientation,
            background: { type: 'color', value: background },
        },
        theme,
        elements: [],
    };
}

export function createElementId(): string {
    return `el-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}
