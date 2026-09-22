import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowDown,
    ArrowUp,
    Check,
    Circle,
    Eye,
    EyeOff,
    Image as ImageIcon,
    Layers,
    LayoutTemplate,
    Lock,
    LockOpen,
    Palette,
    Redo2,
    Save,
    Sparkles,
    Square,
    Type,
    Undo2,
    Upload,
    Video,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AiImageDialog from '@/components/ai/ai-image-dialog';
import AiTextDialog from '@/components/ai/ai-text-dialog';
import {
    LayoutRenderer,
    WIDGET_CATALOG,
    type ElementGeometryPatch,
    type LayoutMediaMap,
} from '@/components/rendering/layout-renderer';
import { WidgetProperties } from '@/components/widgets/widget-properties';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    createWidgetElementProps,
    resolveWidgetType,
    widgetCatalogGrouped,
} from '@/lib/widgets/registry';
import { cn } from '@/lib/utils';
import { screen_designs as screenDesignsIndex } from '@/routes/app';
import screenDesignRoutes from '@/routes/app/screen_designs';
import {
    blankLayoutSchema,
    createElementId,
    isLayoutSchema,
    type LayoutElement,
    type LayoutElementProps,
    type LayoutOrientation,
    type LayoutSchema,
} from '@/types/layout-schema';
import type {
    ScreenDesignEditProps,
    ScreenDesignMediaRef,
} from '@/types/screen-design';

const HISTORY_LIMIT = 50;

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const TEXT_PRESETS: {
    label: string;
    width: number;
    height: number;
    props: LayoutElementProps;
}[] = [
    {
        label: 'Heading',
        width: 900,
        height: 140,
        props: {
            text: 'Heading',
            fontSize: 72,
            fontWeight: 700,
            color: '#FFFFFF',
            textAlign: 'left',
            lineHeight: 1.1,
        },
    },
    {
        label: 'Subheading',
        width: 800,
        height: 100,
        props: {
            text: 'Subheading',
            fontSize: 48,
            fontWeight: 600,
            color: '#FFFFFF',
            textAlign: 'left',
            lineHeight: 1.2,
        },
    },
    {
        label: 'Body',
        width: 700,
        height: 160,
        props: {
            text: 'Body text goes here',
            fontSize: 28,
            fontWeight: 400,
            color: '#E5E7EB',
            textAlign: 'left',
            lineHeight: 1.4,
        },
    },
    {
        label: 'Price',
        width: 280,
        height: 80,
        props: {
            text: '£0.00',
            fontSize: 40,
            fontWeight: 700,
            color: '#22D3EE',
            textAlign: 'right',
            fontFamily: 'JetBrains Mono, monospace',
            lineHeight: 1.2,
        },
    },
    {
        label: 'Caption',
        width: 600,
        height: 60,
        props: {
            text: 'Caption',
            fontSize: 20,
            fontWeight: 400,
            color: 'rgba(255,255,255,0.7)',
            textAlign: 'left',
            lineHeight: 1.3,
            letterSpacing: '0.04em',
        },
    },
];

const LAYOUT_PRESETS: {
    label: string;
    type: 'panel' | 'shape';
    width: number;
    height: number;
    props: LayoutElementProps;
}[] = [
    {
        label: 'Panel',
        type: 'panel',
        width: 960,
        height: 540,
        props: { fill: 'rgba(17,19,24,0.85)', borderRadius: 0 },
    },
    {
        label: 'Rectangle',
        type: 'shape',
        width: 320,
        height: 200,
        props: { shape: 'rect', fill: '#1E2333', borderRadius: 8 },
    },
    {
        label: 'Circle',
        type: 'shape',
        width: 240,
        height: 240,
        props: { shape: 'circle', fill: '#22D3EE' },
    },
];

type LeftTab = 'text' | 'media' | 'layout' | 'layers' | 'widgets' | 'ai';

function resolveSchema(design: ScreenDesignEditProps['design']): LayoutSchema {
    if (isLayoutSchema(design.schema)) {
        return structuredClone(design.schema);
    }

    return blankLayoutSchema(
        (design.orientation === 'portrait'
            ? 'portrait'
            : 'landscape') as LayoutOrientation,
        'blank',
        '#FFFFFF',
    );
}

function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }
    const tag = target.tagName;
    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        target.isContentEditable
    );
}

function mediaTypeToElement(type: string): 'image' | 'video' | 'logo' {
    if (type === 'video') {
        return 'video';
    }
    if (type === 'logo') {
        return 'logo';
    }
    return 'image';
}

function toMediaMap(
    map: Record<string | number, ScreenDesignMediaRef>,
): LayoutMediaMap {
    const out: LayoutMediaMap = {};
    for (const [key, value] of Object.entries(map)) {
        out[key] = value;
        out[Number(key)] = value;
    }
    return out;
}

export default function ScreenDesignEdit({
    design,
    can_edit: canEdit,
    media_map: initialMediaMap,
    media_picker: mediaPicker,
}: ScreenDesignEditProps) {
    const { ai, brandKit } = usePage().props;
    const [schema, setSchema] = useState<LayoutSchema>(() =>
        resolveSchema(design),
    );
    const [name, setName] = useState(design.name);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [liveInteract, setLiveInteract] = useState(false);
    const [leftTab, setLeftTab] = useState<LeftTab>('text');
    const [saving, setSaving] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [replaceOpen, setReplaceOpen] = useState(false);
    const [aiTextOpen, setAiTextOpen] = useState(false);
    const [aiRewriteOpen, setAiRewriteOpen] = useState(false);
    const [aiImageOpen, setAiImageOpen] = useState(false);
    const [aiImageAspect, setAiImageAspect] = useState<
        'landscape' | 'portrait' | 'square'
    >('landscape');
    const [undoStack, setUndoStack] = useState<LayoutSchema[]>([]);
    const [redoStack, setRedoStack] = useState<LayoutSchema[]>([]);
    const [mediaMapState, setMediaMapState] = useState(() =>
        toMediaMap(initialMediaMap),
    );
    const [mediaPickerState, setMediaPickerState] = useState(mediaPicker);
    const [isMobile, setIsMobile] = useState(false);
    const [savedSnapshot, setSavedSnapshot] = useState(() =>
        JSON.stringify({ name: design.name, schema: resolveSchema(design) }),
    );

    const schemaRef = useRef(schema);
    const pendingUndoRef = useRef<LayoutSchema | null>(null);
    const canvasHostRef = useRef<HTMLDivElement>(null);
    const previewHostRef = useRef<HTMLDivElement>(null);
    const [canvasFit, setCanvasFit] = useState({ width: 720, height: 480 });
    const [previewFit, setPreviewFit] = useState({ width: 880, height: 520 });

    schemaRef.current = schema;

    const dirty = useMemo(
        () => JSON.stringify({ name, schema }) !== savedSnapshot,
        [name, schema, savedSnapshot],
    );

    const selected = useMemo(
        () => schema.elements.find((el) => el.id === selectedId) ?? null,
        [schema.elements, selectedId],
    );

    const layers = useMemo(
        () =>
            [...schema.elements].sort(
                (a, b) => (b.zIndex ?? 0) - (a.zIndex ?? 0),
            ),
        [schema.elements],
    );

    const flushPendingUndo = useCallback(() => {
        const baseline = pendingUndoRef.current;
        if (!baseline) {
            return;
        }
        pendingUndoRef.current = null;
        setUndoStack((prev) => [...prev.slice(-(HISTORY_LIMIT - 1)), baseline]);
        setRedoStack([]);
    }, []);

    useEffect(() => {
        const onPointerUp = () => flushPendingUndo();
        window.addEventListener('pointerup', onPointerUp);
        return () => window.removeEventListener('pointerup', onPointerUp);
    }, [flushPendingUndo]);

    useEffect(() => {
        const mq = window.matchMedia('(max-width: 767px)');
        const update = () => setIsMobile(mq.matches);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        const host = canvasHostRef.current;
        if (!host) {
            return;
        }

        const measure = () => {
            const rect = host.getBoundingClientRect();
            setCanvasFit({
                width: Math.max(120, rect.width - 32),
                height: Math.max(120, rect.height - 32),
            });
        };

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(host);
        return () => observer.disconnect();
    }, [isMobile]);

    useEffect(() => {
        if (!previewOpen) {
            return;
        }

        let frame = 0;
        const measure = () => {
            const host = previewHostRef.current;
            if (!host) {
                return;
            }
            const rect = host.getBoundingClientRect();
            setPreviewFit({
                width: Math.max(120, rect.width - 24),
                height: Math.max(120, rect.height - 24),
            });
        };

        measure();
        frame = window.requestAnimationFrame(() => {
            measure();
            frame = window.requestAnimationFrame(measure);
        });

        const host = previewHostRef.current;
        if (!host) {
            return () => window.cancelAnimationFrame(frame);
        }

        const observer = new ResizeObserver(measure);
        observer.observe(host);
        return () => {
            window.cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [previewOpen]);

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        const remove = router.on('before', (event) => {
            if (!dirty) {
                return;
            }

            // Save / publish / other editor mutations must not trigger the leave prompt.
            const visit = event.detail.visit;
            if (visit.method !== 'get') {
                return;
            }

            if (
                !window.confirm(
                    'You have unsaved changes. Leave this page anyway?',
                )
            ) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            remove();
        };
    }, [dirty]);

    const pushHistory = useCallback(
        (next: LayoutSchema) => {
            flushPendingUndo();
            setUndoStack((prev) => [
                ...prev.slice(-(HISTORY_LIMIT - 1)),
                structuredClone(schemaRef.current),
            ]);
            setRedoStack([]);
            setSchema(next);
        },
        [flushPendingUndo],
    );

    const updateElement = useCallback(
        (id: string, patch: Partial<LayoutElement>) => {
            pushHistory({
                ...schemaRef.current,
                elements: schemaRef.current.elements.map((el) =>
                    el.id === id ? { ...el, ...patch } : el,
                ),
            });
        },
        [pushHistory],
    );

    const updateElementProps = useCallback(
        (id: string, propsPatch: LayoutElementProps) => {
            const current = schemaRef.current.elements.find(
                (el) => el.id === id,
            );
            if (!current) {
                return;
            }
            updateElement(id, {
                props: { ...current.props, ...propsPatch },
            });
        },
        [updateElement],
    );

    const handleElementChange = useCallback(
        (id: string, partial: ElementGeometryPatch) => {
            setSchema((prev) => {
                if (!pendingUndoRef.current) {
                    pendingUndoRef.current = structuredClone(prev);
                }
                return {
                    ...prev,
                    elements: prev.elements.map((el) =>
                        el.id === id ? { ...el, ...partial } : el,
                    ),
                };
            });
        },
        [],
    );

    const undo = useCallback(() => {
        flushPendingUndo();
        setUndoStack((prev) => {
            if (prev.length === 0) {
                return prev;
            }
            const baseline = prev[prev.length - 1];
            setRedoStack((redo) => [
                ...redo,
                structuredClone(schemaRef.current),
            ]);
            setSchema(structuredClone(baseline));
            return prev.slice(0, -1);
        });
    }, [flushPendingUndo]);

    const redo = useCallback(() => {
        flushPendingUndo();
        setRedoStack((prev) => {
            if (prev.length === 0) {
                return prev;
            }
            const next = prev[prev.length - 1];
            setUndoStack((undoPrev) => [
                ...undoPrev,
                structuredClone(schemaRef.current),
            ]);
            setSchema(structuredClone(next));
            return prev.slice(0, -1);
        });
    }, [flushPendingUndo]);

    const addElement = useCallback(
        (element: Omit<LayoutElement, 'id' | 'zIndex'> & { id?: string }) => {
            if (!canEdit) {
                return;
            }
            const current = schemaRef.current;
            const offset = current.elements.length * 24;
            const width = element.width;
            const height = element.height;
            const next: LayoutElement = {
                ...element,
                id: element.id ?? createElementId(),
                x: Math.min(
                    element.x ?? 80 + offset,
                    Math.max(0, current.canvas.width - width),
                ),
                y: Math.min(
                    element.y ?? 80 + offset,
                    Math.max(0, current.canvas.height - height),
                ),
                zIndex: current.elements.length + 1,
                locked: element.locked ?? false,
                editable: element.editable ?? true,
            };
            pushHistory({
                ...current,
                elements: [...current.elements, next],
            });
            setSelectedId(next.id);
        },
        [canEdit, pushHistory],
    );

    const addTextPreset = (preset: (typeof TEXT_PRESETS)[number]) => {
        addElement({
            type: 'text',
            name: preset.label,
            x: 80,
            y: 80,
            width: preset.width,
            height: preset.height,
            props: { ...preset.props },
        });
    };

    const addMediaAsset = (asset: ScreenDesignMediaRef) => {
        const type = mediaTypeToElement(asset.type);
        const width =
            type === 'logo'
                ? 240
                : type === 'video'
                  ? 800
                  : Math.min(640, asset.width ?? 640);
        const height =
            type === 'logo'
                ? 120
                : type === 'video'
                  ? 450
                  : Math.min(360, asset.height ?? 360);

        setMediaMapState((prev) => ({
            ...prev,
            [asset.id]: asset,
            [String(asset.id)]: asset,
        }));

        addElement({
            type,
            name: asset.name,
            x: 80,
            y: 80,
            width,
            height,
            props: {
                mediaAssetId: asset.id,
                objectFit: 'cover',
                ...(type === 'video' ? { muted: true, loop: true } : {}),
            },
        });
    };

    const applyBrandKit = useCallback(() => {
        if (!canEdit || !brandKit) {
            return;
        }

        const confirmed = window.confirm(
            'Apply Brand Kit colours, fonts, and logo to this design? Existing elements are updated lightly — the canvas is not wiped.',
        );
        if (!confirmed) {
            return;
        }

        const current = schemaRef.current;
        const emptyBackgrounds = new Set([
            '#ffffff',
            '#fcfcfc',
            '#f8f9fa',
            '#faf7f2',
            '#f1f5f9',
            '#eef2ff',
            '',
        ]);
        const bgValue = (current.canvas.background?.value ?? '').toLowerCase();
        const shouldSetBackground =
            current.canvas.background?.type !== 'color' ||
            emptyBackgrounds.has(bgValue);

        const headingStack =
            brandKit.fonts.heading_stack ||
            `${brandKit.fonts.heading}, system-ui, sans-serif`;
        const bodyStack =
            brandKit.fonts.body_stack ||
            `${brandKit.fonts.body}, system-ui, sans-serif`;

        const colorForBinding = (binding: string): string | null => {
            switch (binding) {
                case 'brand.primary_color':
                    return brandKit.primary_color;
                case 'brand.secondary_color':
                    return brandKit.secondary_color;
                case 'brand.accent_color':
                    return brandKit.accent_color;
                case 'brand.background_color':
                    return brandKit.background_color;
                case 'brand.text_color':
                    return brandKit.text_color;
                default:
                    return null;
            }
        };

        let canvasBackground = current.canvas.background;
        const canvasBinding = current.canvas.background?.brandBinding;
        if (typeof canvasBinding === 'string') {
            const color = colorForBinding(canvasBinding);
            if (color) {
                canvasBackground = {
                    type: 'color',
                    value: color,
                    brandBinding: canvasBinding,
                };
            }
        } else if (shouldSetBackground) {
            canvasBackground = {
                type: 'color',
                value: brandKit.background_color,
            };
        }

        let appliedBoundLogo = false;

        const elements = current.elements.map((el) => {
            const binding =
                typeof el.props?.brandBinding === 'string'
                    ? el.props.brandBinding
                    : null;

            if (binding) {
                const props = { ...el.props };

                switch (binding) {
                    case 'brand.business_name':
                        if (brandKit.name) {
                            props.text = brandKit.name;
                        }
                        break;
                    case 'brand.tagline':
                        if (brandKit.tagline) {
                            props.text = brandKit.tagline;
                        }
                        break;
                    case 'brand.logo':
                        if (brandKit.logo_media_asset_id) {
                            props.mediaAssetId = brandKit.logo_media_asset_id;
                            delete props.placeholder;
                            appliedBoundLogo = true;
                        }
                        break;
                    case 'brand.primary_color':
                    case 'brand.secondary_color':
                    case 'brand.accent_color':
                    case 'brand.background_color':
                    case 'brand.text_color': {
                        const color = colorForBinding(binding);
                        if (color) {
                            if (el.type === 'text') {
                                props.color = color;
                            } else if (
                                el.type === 'panel' ||
                                el.type === 'shape'
                            ) {
                                props.fill = color;
                            }
                        }
                        break;
                    }
                    case 'brand.heading_font':
                        props.fontFamily = headingStack;
                        break;
                    case 'brand.body_font':
                        props.fontFamily = bodyStack;
                        break;
                }

                return { ...el, props };
            }

            // Blanket rewrite only for unbound text elements.
            if (el.type !== 'text') {
                return el;
            }

            const fontSize = Number(el.props?.fontSize ?? 32);
            const isHeading = fontSize >= 40;

            return {
                ...el,
                props: {
                    ...el.props,
                    fontFamily: isHeading ? headingStack : bodyStack,
                    color: isHeading
                        ? brandKit.primary_color
                        : brandKit.text_color,
                },
            };
        });

        const hasLogo =
            appliedBoundLogo ||
            elements.some(
                (el) =>
                    el.type === 'logo' ||
                    (el.type === 'image' &&
                        Number(el.props?.mediaAssetId) ===
                            Number(brandKit.logo_media_asset_id)),
            );

        let nextElements = elements;
        if (brandKit.logo_media_asset_id && !hasLogo) {
            const logoId = brandKit.logo_media_asset_id;
            const existing = mediaPickerState.find((m) => m.id === logoId);
            const logoRef: ScreenDesignMediaRef = existing ?? {
                id: logoId,
                name: brandKit.name ? `${brandKit.name} logo` : 'Brand logo',
                type: 'logo',
                url: brandKit.logo_url,
                text_content: null,
                mime_type: null,
                width: null,
                height: null,
            };

            setMediaMapState((prev) => ({
                ...prev,
                [logoId]: logoRef,
                [String(logoId)]: logoRef,
            }));
            setMediaPickerState((prev) =>
                prev.some((m) => m.id === logoId) ? prev : [logoRef, ...prev],
            );

            nextElements = [
                ...elements,
                {
                    id: createElementId(),
                    type: 'logo' as const,
                    name: logoRef.name,
                    x: 80,
                    y: 80,
                    width: 240,
                    height: 120,
                    zIndex: elements.length + 1,
                    locked: false,
                    editable: true,
                    props: {
                        mediaAssetId: logoId,
                        objectFit: 'contain' as const,
                    },
                },
            ];
        } else if (appliedBoundLogo && brandKit.logo_media_asset_id) {
            const logoId = brandKit.logo_media_asset_id;
            const existing = mediaPickerState.find((m) => m.id === logoId);
            const logoRef: ScreenDesignMediaRef = existing ?? {
                id: logoId,
                name: brandKit.name ? `${brandKit.name} logo` : 'Brand logo',
                type: 'logo',
                url: brandKit.logo_url,
                text_content: null,
                mime_type: null,
                width: null,
                height: null,
            };
            setMediaMapState((prev) => ({
                ...prev,
                [logoId]: logoRef,
                [String(logoId)]: logoRef,
            }));
            setMediaPickerState((prev) =>
                prev.some((m) => m.id === logoId) ? prev : [logoRef, ...prev],
            );
        }

        pushHistory({
            ...current,
            canvas: {
                ...current.canvas,
                background: canvasBackground,
            },
            elements: nextElements,
        });
    }, [brandKit, canEdit, mediaPickerState, pushHistory]);

    const replaceSelectedMedia = (asset: ScreenDesignMediaRef) => {
        if (!selected) {
            return;
        }

        setMediaMapState((prev) => ({
            ...prev,
            [asset.id]: asset,
            [String(asset.id)]: asset,
        }));

        if (selected.type === 'widget') {
            const widgetType = resolveWidgetType(selected.props);
            if (widgetType === 'info_card') {
                const existing =
                    selected.props?.config &&
                    typeof selected.props.config === 'object'
                        ? (selected.props.config as Record<string, unknown>)
                        : {};
                updateElementProps(selected.id, {
                    config: { ...existing, mediaAssetId: asset.id },
                });
                setReplaceOpen(false);
                return;
            }
        }

        if (!['image', 'video', 'logo'].includes(selected.type)) {
            return;
        }

        const nextType = mediaTypeToElement(asset.type);
        updateElement(selected.id, {
            type: nextType,
            name: asset.name,
            props: {
                ...selected.props,
                mediaAssetId: asset.id,
                ...(nextType === 'video'
                    ? {
                          muted: selected.props?.muted ?? true,
                          loop: selected.props?.loop ?? true,
                      }
                    : {}),
            },
        });
        setReplaceOpen(false);
    };

    const deleteSelected = useCallback(() => {
        if (!selectedId || !canEdit) {
            return;
        }
        pushHistory({
            ...schemaRef.current,
            elements: schemaRef.current.elements.filter(
                (el) => el.id !== selectedId,
            ),
        });
        setSelectedId(null);
    }, [canEdit, pushHistory, selectedId]);

    const duplicateSelected = useCallback(() => {
        if (!selectedId || !canEdit) {
            return;
        }
        const current = schemaRef.current;
        const source = current.elements.find((el) => el.id === selectedId);
        if (!source) {
            return;
        }
        const copy: LayoutElement = {
            ...structuredClone(source),
            id: createElementId(),
            name: `${source.name} copy`,
            x: Math.min(
                source.x + 24,
                Math.max(0, current.canvas.width - source.width),
            ),
            y: Math.min(
                source.y + 24,
                Math.max(0, current.canvas.height - source.height),
            ),
            zIndex: current.elements.length + 1,
        };
        pushHistory({
            ...current,
            elements: [...current.elements, copy],
        });
        setSelectedId(copy.id);
    }, [canEdit, pushHistory, selectedId]);

    const moveLayer = (id: string, direction: 'up' | 'down') => {
        const current = schemaRef.current;
        const sorted = [...current.elements].sort(
            (a, b) => (a.zIndex ?? 0) - (b.zIndex ?? 0),
        );
        const index = sorted.findIndex((el) => el.id === id);
        if (index < 0) {
            return;
        }
        const swapWith = direction === 'up' ? index + 1 : index - 1;
        if (swapWith < 0 || swapWith >= sorted.length) {
            return;
        }
        const a = sorted[index];
        const b = sorted[swapWith];
        const aZ = a.zIndex ?? index + 1;
        const bZ = b.zIndex ?? swapWith + 1;
        pushHistory({
            ...current,
            elements: current.elements.map((el) => {
                if (el.id === a.id) {
                    return { ...el, zIndex: bZ };
                }
                if (el.id === b.id) {
                    return { ...el, zIndex: aZ };
                }
                return el;
            }),
        });
    };

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (isTypingTarget(event.target) || !canEdit || isMobile) {
                return;
            }

            const mod = event.metaKey || event.ctrlKey;

            if (mod && event.key.toLowerCase() === 'z') {
                event.preventDefault();
                if (event.shiftKey) {
                    redo();
                } else {
                    undo();
                }
                return;
            }

            if (mod && event.key.toLowerCase() === 'd') {
                event.preventDefault();
                duplicateSelected();
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                deleteSelected();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [canEdit, deleteSelected, duplicateSelected, isMobile, redo, undo]);

    function handleSave() {
        if (!canEdit) {
            return;
        }
        flushPendingUndo();
        setSaving(true);
        router.patch(
            screenDesignRoutes.update.url(design.id),
            {
                name,
                schema,
            } as never,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavedSnapshot(JSON.stringify({ name, schema }));
                },
                onError: () => {
                    setSaving(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    function handlePublish() {
        if (!canEdit) {
            return;
        }
        flushPendingUndo();
        const runPublish = () => {
            setPublishing(true);
            router.post(
                screenDesignRoutes.publish.url(design.id),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => setPublishing(false),
                },
            );
        };

        if (dirty) {
            setSaving(true);
            router.patch(
                screenDesignRoutes.update.url(design.id),
                {
                    name,
                    schema,
                } as never,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSavedSnapshot(JSON.stringify({ name, schema }));
                        runPublish();
                    },
                    onFinish: () => setSaving(false),
                },
            );
            return;
        }

        runPublish();
    }

    const statusVariant =
        design.status === 'published'
            ? 'success'
            : design.status === 'draft'
              ? 'warning'
              : 'neutral';

    if (isMobile) {
        return (
            <>
                <Head title={`Edit · ${design.name}`} />
                <div
                    className="bg-background flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center"
                    data-test="screen-design-editor-mobile"
                >
                    <p className="font-display text-lg font-semibold">
                        {name || design.name}
                    </p>
                    <p className="text-muted-foreground max-w-sm text-sm">
                        The Screen Editor needs a larger display. Open this
                        design on a tablet or desktop to edit.
                    </p>
                    <Button type="button" asChild>
                        <Link href={screenDesignsIndex.url()}>
                            <ArrowLeft className="size-4" />
                            Back to library
                        </Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Edit · ${design.name}`} />
            <div
                className="bg-background flex h-screen flex-col overflow-hidden"
                data-test="screen-design-editor"
            >
                <div className="border-border bg-card flex h-12 shrink-0 items-center gap-3 border-b px-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="-ml-1"
                        asChild
                    >
                        <Link href={screenDesignsIndex.url()}>
                            <ArrowLeft className="size-4" />
                            <span className="hidden sm:inline">Back</span>
                        </Link>
                    </Button>
                    <div className="bg-border h-5 w-px" />
                    <Input
                        data-test="screen-design-editor-name"
                        value={name}
                        disabled={!canEdit}
                        onChange={(e) => setName(e.target.value)}
                        className="focus-visible:border-border h-8 max-w-56 border-transparent bg-transparent px-1 shadow-none focus-visible:ring-0"
                    />
                    <Badge variant={statusVariant} className="capitalize">
                        {design.status}
                    </Badge>
                    <span
                        className={cn(
                            'font-mono text-[10px]',
                            dirty
                                ? 'text-muted-foreground'
                                : 'text-emerald-400',
                        )}
                        data-test="screen-design-save-state"
                    >
                        {saving ? (
                            'Saving…'
                        ) : dirty ? (
                            'Unsaved'
                        ) : (
                            <span className="inline-flex items-center gap-1">
                                <Check className="size-3" /> Saved
                            </span>
                        )}
                    </span>
                    <div className="ml-auto flex flex-wrap items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={!canEdit || undoStack.length === 0}
                            onClick={undo}
                            aria-label="Undo"
                            data-test="screen-design-undo"
                        >
                            <Undo2 className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={!canEdit || redoStack.length === 0}
                            onClick={redo}
                            aria-label="Redo"
                            data-test="screen-design-redo"
                        >
                            <Redo2 className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setPreviewOpen(true)}
                            data-test="screen-design-preview"
                        >
                            <Eye className="size-3.5" />
                            Preview
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={!canEdit || !brandKit}
                            onClick={applyBrandKit}
                            title={
                                brandKit
                                    ? 'Apply Brand Kit colours, fonts, and logo'
                                    : 'Save a Brand Kit first'
                            }
                            data-test="screen-design-apply-brand-kit"
                        >
                            <Palette className="size-3.5" />
                            Apply Brand Kit
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={!canEdit || saving}
                            onClick={handleSave}
                            data-test="screen-design-save"
                        >
                            {saving ? (
                                <Spinner />
                            ) : (
                                <Save className="size-3.5" />
                            )}
                            Save
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!canEdit || publishing || saving}
                            onClick={handlePublish}
                            data-test="screen-design-publish"
                        >
                            {publishing ? (
                                <Spinner />
                            ) : (
                                <Upload className="size-3.5" />
                            )}
                            Publish Design
                        </Button>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 overflow-hidden">
                    <div className="border-border bg-card flex w-14 shrink-0 flex-col items-center gap-1 border-r py-2">
                        {(
                            [
                                { id: 'text', icon: Type, label: 'Text' },
                                {
                                    id: 'media',
                                    icon: ImageIcon,
                                    label: 'Media',
                                },
                                {
                                    id: 'layout',
                                    icon: Square,
                                    label: 'Layout',
                                },
                                {
                                    id: 'layers',
                                    icon: Layers,
                                    label: 'Layers',
                                },
                                {
                                    id: 'widgets',
                                    icon: LayoutTemplate,
                                    label: 'Widgets',
                                },
                                {
                                    id: 'ai',
                                    icon: Sparkles,
                                    label: 'AI',
                                },
                            ] as const
                        ).map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                title={tab.label}
                                onClick={() => setLeftTab(tab.id)}
                                data-test={`screen-design-tab-${tab.id}`}
                                className={cn(
                                    'flex size-10 flex-col items-center justify-center rounded-xl text-[8px] font-medium transition-colors',
                                    leftTab === tab.id
                                        ? 'bg-primary/15 text-primary'
                                        : 'text-muted-foreground hover:bg-secondary hover:text-foreground',
                                )}
                            >
                                <tab.icon className="size-4" />
                            </button>
                        ))}
                    </div>

                    <div className="border-border bg-card flex w-56 shrink-0 flex-col overflow-hidden border-r">
                        <div className="border-border border-b px-3 py-2.5">
                            <p className="text-xs font-semibold capitalize">
                                {leftTab === 'widgets' ? 'Widgets' : leftTab}
                            </p>
                        </div>
                        <div className="flex-1 space-y-1.5 overflow-y-auto p-2">
                            {leftTab === 'text'
                                ? TEXT_PRESETS.map((preset) => (
                                      <button
                                          key={preset.label}
                                          type="button"
                                          disabled={!canEdit}
                                          data-test={`screen-design-add-text-${preset.label.toLowerCase()}`}
                                          onClick={() => addTextPreset(preset)}
                                          className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                      >
                                          <Type className="size-3.5 shrink-0" />
                                          {preset.label}
                                      </button>
                                  ))
                                : null}

                            {leftTab === 'ai' ? (
                                <div className="space-y-1.5">
                                    <button
                                        type="button"
                                        disabled={!canEdit}
                                        data-test="screen-design-ai-write"
                                        onClick={() => setAiTextOpen(true)}
                                        className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                    >
                                        <Sparkles className="size-3.5 shrink-0" />
                                        Write with AI
                                    </button>
                                    <button
                                        type="button"
                                        disabled={
                                            !canEdit ||
                                            selected?.type !== 'text'
                                        }
                                        data-test="screen-design-ai-rewrite"
                                        onClick={() => setAiRewriteOpen(true)}
                                        className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                    >
                                        <Sparkles className="size-3.5 shrink-0" />
                                        Rewrite selected text
                                    </button>
                                    <button
                                        type="button"
                                        disabled={!canEdit}
                                        data-test="screen-design-ai-image"
                                        onClick={() => {
                                            if (
                                                selected &&
                                                (selected.type === 'image' ||
                                                    selected.type === 'logo')
                                            ) {
                                                const ratio =
                                                    selected.width /
                                                    Math.max(
                                                        1,
                                                        selected.height,
                                                    );
                                                setAiImageAspect(
                                                    ratio > 1.2
                                                        ? 'landscape'
                                                        : ratio < 0.8
                                                          ? 'portrait'
                                                          : 'square',
                                                );
                                            } else {
                                                setAiImageAspect(
                                                    schema.canvas
                                                        .orientation ===
                                                        'portrait'
                                                        ? 'portrait'
                                                        : 'landscape',
                                                );
                                            }
                                            setAiImageOpen(true);
                                        }}
                                        className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                    >
                                        <Sparkles className="size-3.5 shrink-0" />
                                        AI Image
                                    </button>
                                    <p className="text-muted-foreground px-1 pt-1 text-[10px] leading-relaxed">
                                        AI drafts stay editable. Nothing
                                        publishes until you publish.
                                    </p>
                                </div>
                            ) : null}

                            {leftTab === 'media' ? (
                                mediaPickerState.length === 0 ? (
                                    <p className="text-muted-foreground px-1 text-[11px]">
                                        No media in this business yet. Upload
                                        assets in Media first.
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {mediaPickerState.map((asset) => (
                                            <button
                                                key={asset.id}
                                                type="button"
                                                disabled={!canEdit}
                                                data-test={`screen-design-media-${asset.id}`}
                                                onClick={() => {
                                                    if (
                                                        selected?.type ===
                                                            'widget' &&
                                                        resolveWidgetType(
                                                            selected.props,
                                                        ) === 'info_card'
                                                    ) {
                                                        replaceSelectedMedia(
                                                            asset,
                                                        );
                                                        return;
                                                    }
                                                    addMediaAsset(asset);
                                                }}
                                                className="bg-muted hover:ring-primary/40 aspect-square overflow-hidden rounded-lg hover:ring-1 disabled:opacity-50"
                                                title={asset.name}
                                            >
                                                {asset.type === 'video' ? (
                                                    <div className="flex h-full w-full flex-col items-center justify-center gap-1">
                                                        <Video className="text-muted-foreground size-4" />
                                                        <span className="text-muted-foreground line-clamp-2 px-1 text-[9px]">
                                                            {asset.name}
                                                        </span>
                                                    </div>
                                                ) : asset.url ? (
                                                    <img
                                                        src={asset.url}
                                                        alt={asset.name}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="text-muted-foreground flex h-full items-center justify-center p-1 text-[9px]">
                                                        {asset.name}
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )
                            ) : null}

                            {leftTab === 'layout'
                                ? LAYOUT_PRESETS.map((preset) => (
                                      <button
                                          key={preset.label}
                                          type="button"
                                          disabled={!canEdit}
                                          data-test={`screen-design-add-layout-${preset.label.toLowerCase()}`}
                                          onClick={() =>
                                              addElement({
                                                  type: preset.type,
                                                  name: preset.label,
                                                  x: 80,
                                                  y: 80,
                                                  width: preset.width,
                                                  height: preset.height,
                                                  props: { ...preset.props },
                                              })
                                          }
                                          className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                      >
                                          {preset.label === 'Circle' ? (
                                              <Circle className="size-3.5" />
                                          ) : (
                                              <Square className="size-3.5" />
                                          )}
                                          {preset.label}
                                      </button>
                                  ))
                                : null}

                            {leftTab === 'layers'
                                ? layers.map((el) => {
                                      const hidden =
                                          el.props?.visible === false;
                                      return (
                                          <div
                                              key={el.id}
                                              className={cn(
                                                  'bg-secondary flex items-center gap-1 rounded-lg px-1.5 py-1',
                                                  selectedId === el.id &&
                                                      'ring-primary/40 ring-1',
                                              )}
                                          >
                                              <button
                                                  type="button"
                                                  className="min-w-0 flex-1 truncate px-1 text-left text-[11px]"
                                                  onClick={() =>
                                                      setSelectedId(el.id)
                                                  }
                                              >
                                                  {el.name}
                                              </button>
                                              <button
                                                  type="button"
                                                  title={
                                                      hidden ? 'Show' : 'Hide'
                                                  }
                                                  disabled={!canEdit}
                                                  onClick={() =>
                                                      updateElementProps(
                                                          el.id,
                                                          {
                                                              visible: hidden
                                                                  ? true
                                                                  : false,
                                                          },
                                                      )
                                                  }
                                                  className="text-muted-foreground hover:text-foreground p-1"
                                              >
                                                  {hidden ? (
                                                      <EyeOff className="size-3" />
                                                  ) : (
                                                      <Eye className="size-3" />
                                                  )}
                                              </button>
                                              <button
                                                  type="button"
                                                  title={
                                                      el.locked
                                                          ? 'Unlock'
                                                          : 'Lock'
                                                  }
                                                  disabled={!canEdit}
                                                  onClick={() =>
                                                      updateElement(el.id, {
                                                          locked: !el.locked,
                                                      })
                                                  }
                                                  className="text-muted-foreground hover:text-foreground p-1"
                                              >
                                                  {el.locked ? (
                                                      <Lock className="size-3" />
                                                  ) : (
                                                      <LockOpen className="size-3" />
                                                  )}
                                              </button>
                                              <button
                                                  type="button"
                                                  title="Bring forward"
                                                  disabled={!canEdit}
                                                  onClick={() =>
                                                      moveLayer(el.id, 'up')
                                                  }
                                                  className="text-muted-foreground hover:text-foreground p-1"
                                              >
                                                  <ArrowUp className="size-3" />
                                              </button>
                                              <button
                                                  type="button"
                                                  title="Send backward"
                                                  disabled={!canEdit}
                                                  onClick={() =>
                                                      moveLayer(el.id, 'down')
                                                  }
                                                  className="text-muted-foreground hover:text-foreground p-1"
                                              >
                                                  <ArrowDown className="size-3" />
                                              </button>
                                          </div>
                                      );
                                  })
                                : null}

                            {leftTab === 'widgets' ? (
                                <div className="space-y-2">
                                    {widgetCatalogGrouped().map((group) => (
                                        <div
                                            key={group.category}
                                            className="space-y-1"
                                        >
                                            <p className="text-muted-foreground px-1 text-[10px] font-semibold tracking-wide uppercase">
                                                {group.label}
                                            </p>
                                            {group.widgets.map((widget) => {
                                                const catalog =
                                                    WIDGET_CATALOG.find(
                                                        (w) =>
                                                            w.type ===
                                                            widget.type,
                                                    );
                                                const Icon =
                                                    catalog?.icon ??
                                                    LayoutTemplate;
                                                return (
                                                    <button
                                                        key={widget.type}
                                                        type="button"
                                                        disabled={!canEdit}
                                                        data-test={`screen-design-add-widget-${widget.type}`}
                                                        onClick={() =>
                                                            addElement({
                                                                type: 'widget',
                                                                name: widget.label,
                                                                x: 80,
                                                                y: 80,
                                                                width: widget.defaultWidth,
                                                                height: widget.defaultHeight,
                                                                props: createWidgetElementProps(
                                                                    widget.type,
                                                                ),
                                                            })
                                                        }
                                                        className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs disabled:opacity-50"
                                                    >
                                                        <Icon className="size-3.5 shrink-0" />
                                                        {widget.label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    </div>

                    <div
                        ref={canvasHostRef}
                        className="flex min-w-0 flex-1 items-center justify-center overflow-hidden bg-[#060810] p-4"
                        data-test="screen-design-canvas"
                    >
                        <LayoutRenderer
                            schema={schema}
                            mode="editor"
                            fitWidth={canvasFit.width}
                            fitHeight={canvasFit.height}
                            interactive={canEdit}
                            selectedId={selectedId}
                            liveInteractId={liveInteract ? selectedId : null}
                            onSelect={(id) => {
                                setSelectedId(id);
                                if (id !== selectedId) {
                                    setLiveInteract(false);
                                }
                            }}
                            onElementChange={handleElementChange}
                            mediaMap={mediaMapState}
                        />
                    </div>

                    <div className="border-border bg-card w-60 shrink-0 overflow-y-auto border-l">
                        <div className="border-border border-b px-4 py-3">
                            <p className="text-xs font-semibold">
                                {selected ? selected.name : 'Properties'}
                            </p>
                        </div>
                        {selected && canEdit ? (
                            <div className="space-y-4 p-4">
                                <div className="space-y-2">
                                    <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                        Geometry
                                    </p>
                                    <div className="grid grid-cols-2 gap-2">
                                        {(
                                            [
                                                'x',
                                                'y',
                                                'width',
                                                'height',
                                            ] as const
                                        ).map((key) => (
                                            <div
                                                key={key}
                                                className="space-y-1"
                                            >
                                                <Label className="capitalize">
                                                    {key}
                                                </Label>
                                                <Input
                                                    type="number"
                                                    data-test={`screen-design-prop-${key}`}
                                                    value={selected[key]}
                                                    onChange={(e) =>
                                                        updateElement(
                                                            selected.id,
                                                            {
                                                                [key]: Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {selected.type === 'text' ? (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                            Typography
                                        </p>
                                        <div className="space-y-1">
                                            <Label>Text</Label>
                                            <textarea
                                                data-test="screen-design-prop-text"
                                                className={cn(
                                                    selectClassName,
                                                    'min-h-16 py-2',
                                                )}
                                                value={
                                                    typeof selected.props
                                                        ?.text === 'string'
                                                        ? selected.props.text
                                                        : selected.name
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            text: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="mt-1 w-full"
                                                data-test="screen-design-prop-ai-rewrite"
                                                onClick={() =>
                                                    setAiRewriteOpen(true)
                                                }
                                            >
                                                <Sparkles className="size-3.5" />
                                                Rewrite with AI
                                            </Button>
                                        </div>
                                        <div className="space-y-1">
                                            <Label>Font family</Label>
                                            <select
                                                className={selectClassName}
                                                value={
                                                    (selected.props
                                                        ?.fontFamily as string) ??
                                                    'Outfit, system-ui, sans-serif'
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            fontFamily:
                                                                e.target.value,
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="Outfit, system-ui, sans-serif">
                                                    Outfit
                                                </option>
                                                <option value="Inter, system-ui, sans-serif">
                                                    Inter
                                                </option>
                                                <option value="JetBrains Mono, monospace">
                                                    JetBrains Mono
                                                </option>
                                            </select>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="space-y-1">
                                                <Label>Size</Label>
                                                <Input
                                                    type="number"
                                                    value={
                                                        Number(
                                                            selected.props
                                                                ?.fontSize,
                                                        ) || 32
                                                    }
                                                    onChange={(e) =>
                                                        updateElementProps(
                                                            selected.id,
                                                            {
                                                                fontSize:
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label>Weight</Label>
                                                <Input
                                                    type="number"
                                                    value={
                                                        Number(
                                                            selected.props
                                                                ?.fontWeight,
                                                        ) || 600
                                                    }
                                                    onChange={(e) =>
                                                        updateElementProps(
                                                            selected.id,
                                                            {
                                                                fontWeight:
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <Label>Color</Label>
                                            <Input
                                                value={
                                                    (selected.props
                                                        ?.color as string) ??
                                                    '#FFFFFF'
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            color: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label>Align</Label>
                                            <select
                                                className={selectClassName}
                                                value={
                                                    (selected.props
                                                        ?.textAlign as string) ??
                                                    'left'
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            textAlign: e.target
                                                                .value as LayoutElementProps['textAlign'],
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="left">
                                                    Left
                                                </option>
                                                <option value="center">
                                                    Center
                                                </option>
                                                <option value="right">
                                                    Right
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                ) : null}

                                {['image', 'logo'].includes(selected.type) ? (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                            Image
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            onClick={() => setReplaceOpen(true)}
                                        >
                                            Replace
                                        </Button>
                                        <div className="space-y-1">
                                            <Label>Fit</Label>
                                            <select
                                                className={selectClassName}
                                                value={
                                                    (selected.props
                                                        ?.objectFit as string) ??
                                                    'cover'
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            objectFit: e.target
                                                                .value as LayoutElementProps['objectFit'],
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="cover">
                                                    Cover
                                                </option>
                                                <option value="contain">
                                                    Contain
                                                </option>
                                                <option value="fill">
                                                    Fill
                                                </option>
                                            </select>
                                        </div>
                                        <div className="space-y-1">
                                            <Label>Border radius</Label>
                                            <Input
                                                type="number"
                                                value={
                                                    Number(
                                                        selected.props
                                                            ?.borderRadius,
                                                    ) || 0
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            borderRadius:
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {selected.type === 'video' ? (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                            Video
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            onClick={() => setReplaceOpen(true)}
                                        >
                                            Replace
                                        </Button>
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    selected.props?.muted !==
                                                    false
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            muted: e.target
                                                                .checked,
                                                        },
                                                    )
                                                }
                                            />
                                            Muted
                                        </label>
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    selected.props?.loop !==
                                                    false
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            loop: e.target
                                                                .checked,
                                                        },
                                                    )
                                                }
                                            />
                                            Loop
                                        </label>
                                    </div>
                                ) : null}

                                {selected.type === 'panel' ||
                                selected.type === 'shape' ? (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                                            Fill
                                        </p>
                                        <Input
                                            value={
                                                (selected.props
                                                    ?.fill as string) ??
                                                '#1E2333'
                                            }
                                            onChange={(e) =>
                                                updateElementProps(
                                                    selected.id,
                                                    {
                                                        fill: e.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        {selected.type === 'shape' ? (
                                            <div className="space-y-1">
                                                <Label>Shape</Label>
                                                <select
                                                    className={selectClassName}
                                                    value={
                                                        (selected.props
                                                            ?.shape as string) ??
                                                        'rect'
                                                    }
                                                    onChange={(e) =>
                                                        updateElementProps(
                                                            selected.id,
                                                            {
                                                                shape: e.target
                                                                    .value as LayoutElementProps['shape'],
                                                            },
                                                        )
                                                    }
                                                >
                                                    <option value="rect">
                                                        Rectangle
                                                    </option>
                                                    <option value="circle">
                                                        Circle
                                                    </option>
                                                </select>
                                            </div>
                                        ) : null}
                                        <div className="space-y-1">
                                            <Label>Border radius</Label>
                                            <Input
                                                type="number"
                                                value={
                                                    Number(
                                                        selected.props
                                                            ?.borderRadius,
                                                    ) || 0
                                                }
                                                onChange={(e) =>
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            borderRadius:
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : null}

                                {selected.type === 'widget' ? (
                                    <WidgetProperties
                                        element={selected}
                                        canEdit={canEdit}
                                        mediaAssets={mediaPicker}
                                        liveInteract={liveInteract}
                                        onLiveInteractChange={setLiveInteract}
                                        onChange={(propsPatch) =>
                                            updateElementProps(
                                                selected.id,
                                                propsPatch,
                                            )
                                        }
                                    />
                                ) : null}

                                <div className="space-y-1">
                                    <Label>Opacity</Label>
                                    <Input
                                        type="range"
                                        min={0}
                                        max={100}
                                        value={Math.round(
                                            (Number(selected.props?.opacity) ||
                                                1) * 100,
                                        )}
                                        onChange={(e) =>
                                            updateElementProps(selected.id, {
                                                opacity:
                                                    Number(e.target.value) /
                                                    100,
                                            })
                                        }
                                    />
                                </div>

                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    className="w-full"
                                    onClick={deleteSelected}
                                >
                                    Delete element
                                </Button>
                            </div>
                        ) : (
                            <div className="text-muted-foreground p-4 text-center text-xs">
                                {canEdit
                                    ? 'Select an element to edit its properties'
                                    : 'View only — you cannot edit this design'}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent
                    className="flex max-h-[90vh] flex-col sm:max-w-[960px]"
                    data-test="screen-design-preview-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>Preview · {name}</DialogTitle>
                        <DialogDescription>
                            Fit mode — scaled to available width and height
                        </DialogDescription>
                    </DialogHeader>
                    <div
                        ref={previewHostRef}
                        className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg bg-[#060810] p-4"
                        style={{
                            height: 'min(70vh, 640px)',
                            maxHeight: 'min(70vh, 640px)',
                        }}
                    >
                        <LayoutRenderer
                            schema={schema}
                            mode="preview"
                            fitWidth={previewFit.width}
                            fitHeight={previewFit.height}
                            mediaMap={mediaMapState}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={replaceOpen} onOpenChange={setReplaceOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Replace media</DialogTitle>
                        <DialogDescription>
                            Choose an asset from your Media library.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid max-h-80 grid-cols-3 gap-2 overflow-y-auto">
                        {mediaPickerState.map((asset) => (
                            <button
                                key={asset.id}
                                type="button"
                                onClick={() => replaceSelectedMedia(asset)}
                                className="bg-muted hover:ring-primary/40 aspect-square overflow-hidden rounded-lg hover:ring-1"
                            >
                                {asset.url && asset.type !== 'video' ? (
                                    <img
                                        src={asset.url}
                                        alt={asset.name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <span className="text-muted-foreground flex h-full items-center justify-center p-2 text-[10px]">
                                        {asset.name}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            <AiTextDialog
                open={aiTextOpen}
                onOpenChange={setAiTextOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                mode="write"
                onInsert={(text) => {
                    if (selected?.type === 'text') {
                        updateElementProps(selected.id, { text });
                        return;
                    }

                    addTextPreset({
                        label: 'AI Text',
                        width: 800,
                        height: 120,
                        props: {
                            text,
                            fontFamily: 'Outfit, system-ui, sans-serif',
                            fontSize: 64,
                            fontWeight: 700,
                            color: '#ffffff',
                            align: 'left',
                        },
                    });
                }}
            />

            <AiTextDialog
                open={aiRewriteOpen}
                onOpenChange={setAiRewriteOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                mode="rewrite"
                initialText={
                    selected?.type === 'text'
                        ? typeof selected.props?.text === 'string'
                            ? selected.props.text
                            : selected.name
                        : ''
                }
                onInsert={(text) => {
                    if (selected?.type === 'text') {
                        updateElementProps(selected.id, { text });
                    }
                }}
            />

            <AiImageDialog
                open={aiImageOpen}
                onOpenChange={setAiImageOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                defaultAspect={aiImageAspect}
                onSaved={(media) => {
                    const ref: ScreenDesignMediaRef = {
                        id: media.id,
                        name: media.name,
                        type: media.type,
                        url: media.url,
                        text_content: null,
                        mime_type: null,
                        width: media.width,
                        height: media.height,
                    };
                    setMediaPickerState((prev) => [
                        ref,
                        ...prev.filter((a) => a.id !== ref.id),
                    ]);
                    if (
                        selected &&
                        (selected.type === 'image' || selected.type === 'logo')
                    ) {
                        replaceSelectedMedia(ref);
                    } else {
                        addMediaAsset(ref);
                    }
                }}
            />
        </>
    );
}
