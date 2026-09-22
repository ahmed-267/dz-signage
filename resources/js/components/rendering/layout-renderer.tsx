import {
    AlertTriangle,
    Calendar,
    Clock,
    Cloud,
    Cpu,
    Image as ImageIcon,
    LayoutTemplate,
    Radio,
    Rss,
    Square,
    Timer,
    Type,
    Video,
    type LucideIcon,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useState,
    type CSSProperties,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import { WidgetRenderer } from '@/components/widgets/widget-renderer';
import { resolveWidgetType, WIDGET_DEFINITIONS } from '@/lib/widgets/registry';
import type { WidgetRuntimeMode, WidgetType } from '@/lib/widgets/types';
import { cn } from '@/lib/utils';
import type {
    LayoutElement,
    LayoutElementType,
    LayoutMediaMap,
    LayoutMediaMapEntry,
    LayoutSchema,
} from '@/types/layout-schema';

export type { LayoutMediaMap, LayoutMediaMapEntry };

const TYPE_STYLES: Record<LayoutElementType, string> = {
    text: 'border-blue-500/60 bg-blue-500/25 text-blue-100',
    image: 'border-emerald-500/60 bg-emerald-500/25 text-emerald-100',
    video: 'border-violet-500/60 bg-violet-500/25 text-violet-100',
    logo: 'border-amber-500/60 bg-amber-500/25 text-amber-100',
    panel: 'border-slate-400/50 bg-slate-500/20 text-slate-100',
    shape: 'border-cyan-500/60 bg-cyan-500/20 text-cyan-100',
    widget: 'border-orange-500/60 bg-orange-500/25 text-orange-100',
};

const MIN_ELEMENT_SIZE = 24;
const MIN_SCALE = 0.05;
const MAX_SCALE = 1;

type ResizeHandle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w';

const RESIZE_HANDLES: {
    id: ResizeHandle;
    cursor: string;
    className: string;
}[] = [
    { id: 'nw', cursor: 'nwse-resize', className: '-left-1.5 -top-1.5' },
    {
        id: 'n',
        cursor: 'ns-resize',
        className: 'left-1/2 -top-1.5 -translate-x-1/2',
    },
    { id: 'ne', cursor: 'nesw-resize', className: '-right-1.5 -top-1.5' },
    {
        id: 'e',
        cursor: 'ew-resize',
        className: '-right-1.5 top-1/2 -translate-y-1/2',
    },
    { id: 'se', cursor: 'nwse-resize', className: '-right-1.5 -bottom-1.5' },
    {
        id: 's',
        cursor: 'ns-resize',
        className: 'left-1/2 -bottom-1.5 -translate-x-1/2',
    },
    { id: 'sw', cursor: 'nesw-resize', className: '-left-1.5 -bottom-1.5' },
    {
        id: 'w',
        cursor: 'ew-resize',
        className: '-left-1.5 top-1/2 -translate-y-1/2',
    },
];

function ElementIcon({
    type,
    className,
}: {
    type: LayoutElementType;
    className?: string;
}) {
    const props = { className: cn('size-4 shrink-0', className) };
    switch (type) {
        case 'text':
            return <Type {...props} />;
        case 'image':
        case 'logo':
            return <ImageIcon {...props} />;
        case 'video':
            return <Video {...props} />;
        case 'panel':
        case 'shape':
            return <Square {...props} />;
        case 'widget':
            return <Cpu {...props} />;
        default:
            return <Square {...props} />;
    }
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function computeScale(
    canvasWidth: number,
    canvasHeight: number,
    fitWidth?: number,
    fitHeight?: number,
): number {
    const scales: number[] = [];

    if (fitWidth != null && fitWidth > 0) {
        scales.push(fitWidth / canvasWidth);
    }
    if (fitHeight != null && fitHeight > 0) {
        scales.push(fitHeight / canvasHeight);
    }

    if (scales.length === 0) {
        return 1;
    }

    return clamp(Math.min(...scales), MIN_SCALE, MAX_SCALE);
}

function clampElementToCanvas(
    x: number,
    y: number,
    width: number,
    height: number,
    canvasWidth: number,
    canvasHeight: number,
): { x: number; y: number; width: number; height: number } {
    const w = clamp(width, MIN_ELEMENT_SIZE, canvasWidth);
    const h = clamp(height, MIN_ELEMENT_SIZE, canvasHeight);
    return {
        x: clamp(x, 0, Math.max(0, canvasWidth - w)),
        y: clamp(y, 0, Math.max(0, canvasHeight - h)),
        width: w,
        height: h,
    };
}

function resolveMedia(
    mediaMap: LayoutMediaMap | undefined,
    mediaAssetId: unknown,
): LayoutMediaMapEntry | null {
    if (mediaMap == null || mediaAssetId == null || mediaAssetId === '') {
        return null;
    }

    if (typeof mediaAssetId === 'string' || typeof mediaAssetId === 'number') {
        return mediaMap[mediaAssetId] ?? mediaMap[String(mediaAssetId)] ?? null;
    }

    return null;
}

function canvasBackgroundStyle(
    background: LayoutSchema['canvas']['background'] | undefined,
    mediaMap?: LayoutMediaMap,
): CSSProperties {
    const fallback = '#101114';

    if (background?.type === 'image' && background.mediaAssetId != null) {
        const media = resolveMedia(mediaMap, background.mediaAssetId);
        if (media?.url) {
            return {
                backgroundColor: background.value || fallback,
                backgroundImage: `url(${media.url})`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
            };
        }
    }

    return {
        backgroundColor:
            background?.type === 'color'
                ? background.value || fallback
                : (background?.value ?? fallback),
    };
}

function paintsOutsideScale(element: LayoutElement): boolean {
    if (element.type === 'video') {
        return true;
    }

    if (element.type !== 'widget') {
        return false;
    }

    return resolveWidgetType(element.props ?? {}) === 'embed';
}

function ElementContent({
    element,
    mediaMap,
    widgetMode,
    widgetData,
    isOnline,
    liveInteract = false,
}: {
    element: LayoutElement;
    mediaMap?: LayoutMediaMap;
    widgetMode: WidgetRuntimeMode;
    widgetData?: Record<string, unknown>;
    isOnline?: boolean;
    liveInteract?: boolean;
}) {
    const props = element.props ?? {};
    const opacity =
        typeof props.opacity === 'number'
            ? Math.min(1, Math.max(0, props.opacity))
            : 1;
    const borderRadius =
        typeof props.borderRadius === 'number' ? props.borderRadius : undefined;
    const objectFit =
        props.objectFit === 'contain' ||
        props.objectFit === 'fill' ||
        props.objectFit === 'cover'
            ? props.objectFit
            : 'cover';
    const media = resolveMedia(mediaMap, props.mediaAssetId);

    if (element.type === 'text') {
        const text =
            typeof props.text === 'string' && props.text.length > 0
                ? props.text
                : element.name;
        return (
            <div
                className="flex h-full w-full items-center overflow-hidden px-1"
                style={{
                    opacity,
                    color:
                        typeof props.color === 'string'
                            ? props.color
                            : '#FFFFFF',
                    fontFamily:
                        typeof props.fontFamily === 'string'
                            ? props.fontFamily
                            : 'Outfit, system-ui, sans-serif',
                    fontSize:
                        typeof props.fontSize === 'number'
                            ? props.fontSize
                            : 32,
                    fontWeight: props.fontWeight ?? 600,
                    textAlign: props.textAlign ?? 'left',
                    lineHeight: props.lineHeight ?? 1.2,
                    letterSpacing: props.letterSpacing,
                    justifyContent:
                        props.textAlign === 'center'
                            ? 'center'
                            : props.textAlign === 'right'
                              ? 'flex-end'
                              : 'flex-start',
                }}
            >
                <span className="w-full break-words whitespace-pre-wrap">
                    {text}
                </span>
            </div>
        );
    }

    if (
        element.type === 'image' ||
        element.type === 'logo' ||
        element.type === 'video'
    ) {
        if (media?.url) {
            if (element.type === 'video') {
                return (
                    <video
                        src={media.url}
                        className="h-full w-full"
                        style={{
                            opacity,
                            objectFit,
                            borderRadius,
                        }}
                        muted={props.muted !== false}
                        loop={props.loop !== false}
                        autoPlay
                        playsInline
                    />
                );
            }

            return (
                <img
                    src={media.url}
                    alt={element.name}
                    className="h-full w-full"
                    style={{
                        opacity,
                        objectFit,
                        borderRadius,
                    }}
                    draggable={false}
                />
            );
        }

        return (
            <div
                className={cn(
                    'flex h-full w-full flex-col items-center justify-center gap-1.5 overflow-hidden border border-dashed px-3 text-center',
                    element.type === 'logo'
                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-100'
                        : element.type === 'video'
                          ? 'border-violet-500/40 bg-violet-500/10 text-violet-100'
                          : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
                )}
                style={{ opacity, borderRadius: borderRadius ?? 12 }}
            >
                <ElementIcon type={element.type} />
                <span className="line-clamp-2 text-[11px] leading-tight font-medium opacity-90">
                    {element.type === 'logo'
                        ? 'Logo'
                        : element.type === 'video'
                          ? 'Replace video'
                          : typeof props.placeholderLabel === 'string'
                            ? props.placeholderLabel
                            : 'Replace image'}
                </span>
            </div>
        );
    }

    if (element.type === 'panel' || element.type === 'shape') {
        const isCircle = element.type === 'shape' && props.shape === 'circle';
        const fill =
            typeof props.fill === 'string' ? props.fill : 'rgba(30,35,51,0.85)';

        return (
            <div
                className="h-full w-full"
                style={{
                    opacity,
                    backgroundColor: fill,
                    borderRadius: isCircle
                        ? '9999px'
                        : (borderRadius ?? (element.type === 'panel' ? 0 : 8)),
                }}
            />
        );
    }

    if (element.type === 'widget') {
        return (
            <WidgetRenderer
                element={element}
                mode={widgetMode}
                mediaMap={mediaMap}
                widgetData={widgetData}
                isOnline={isOnline}
                interact={liveInteract}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex h-full w-full flex-col items-center justify-center gap-1 overflow-hidden border-2 px-2 text-center',
                TYPE_STYLES.panel,
            )}
            style={{ opacity }}
        >
            <ElementIcon type={element.type} />
            <span className="line-clamp-2 text-[10px] leading-tight font-medium">
                {element.name}
            </span>
        </div>
    );
}

export type ElementGeometryPatch = {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
};

type LayoutRendererProps = {
    schema: LayoutSchema;
    className?: string;
    /** Fit canvas into this max width (px). */
    fitWidth?: number;
    /** Fit canvas into this max height (px). */
    fitHeight?: number;
    mode?: 'preview' | 'editor';
    /** Widget runtime; defaults to editor/preview from mode, or player when set. */
    runtime?: WidgetRuntimeMode;
    selectedId?: string | null;
    onSelect?: (id: string | null) => void;
    onElementChange?: (id: string, partial: ElementGeometryPatch) => void;
    interactive?: boolean;
    /** When set, the matching live embed receives pointer events for provider controls. */
    liveInteractId?: string | null;
    /** Resolve mediaAssetId → asset URL for canvas content. */
    mediaMap?: LayoutMediaMap;
    /** Cached / live widget payloads keyed by PHP-compatible keys. */
    widgetData?: Record<string, unknown>;
    isOnline?: boolean;
};

/**
 * Shared canvas renderer for Templates, Screen Designs, and Player.
 * Positions use logical canvas coordinates; the view scales to fit.
 * Canvas content colors are inline — app chrome theme must not recolor them.
 */
export function LayoutRenderer({
    schema,
    className,
    fitWidth,
    fitHeight,
    mode = 'preview',
    runtime,
    selectedId = null,
    onSelect,
    onElementChange,
    interactive = false,
    liveInteractId = null,
    mediaMap,
    widgetData,
    isOnline = true,
}: LayoutRendererProps) {
    const { width, height, background } = schema.canvas;
    const scale = computeScale(width, height, fitWidth, fitHeight);
    const displayWidth = width * scale;
    const displayHeight = height * scale;
    const isEditor = mode === 'editor' && interactive;
    const widgetMode: WidgetRuntimeMode =
        runtime ?? (mode === 'editor' ? 'editor' : 'preview');

    const sorted = [...schema.elements]
        .filter((el) => el.props?.visible !== false)
        .sort((a, b) => (a.zIndex ?? 0) - (b.zIndex ?? 0));

    return (
        <div
            className={cn('relative mx-auto overflow-hidden', className)}
            style={{ width: displayWidth, height: displayHeight }}
            role="img"
            aria-label="Layout preview"
            onClick={() => {
                if (isEditor) {
                    onSelect?.(null);
                }
            }}
        >
            <div
                className="pointer-events-none absolute top-0 left-0 origin-top-left"
                style={{
                    width,
                    height,
                    transform: `scale(${scale})`,
                    zIndex: 0,
                    ...canvasBackgroundStyle(background, mediaMap),
                }}
            />
            {sorted.length === 0 ? (
                <div
                    className="absolute inset-0 flex items-center justify-center text-sm"
                    style={{ color: 'rgba(255,255,255,0.35)' }}
                >
                    Empty canvas
                </div>
            ) : null}
            {sorted.map((element) => (
                <LayoutElementView
                    key={element.id}
                    element={element}
                    selected={selectedId === element.id}
                    interactive={isEditor}
                    liveInteract={liveInteractId === element.id}
                    scale={scale}
                    canvasWidth={width}
                    canvasHeight={height}
                    mediaMap={mediaMap}
                    widgetMode={widgetMode}
                    widgetData={widgetData}
                    isOnline={isOnline}
                    onSelect={onSelect}
                    onElementChange={onElementChange}
                />
            ))}
        </div>
    );
}

type DragState =
    | {
          kind: 'move';
          pointerId: number;
          startClientX: number;
          startClientY: number;
          originX: number;
          originY: number;
          width: number;
          height: number;
      }
    | {
          kind: 'resize';
          pointerId: number;
          handle: ResizeHandle;
          startClientX: number;
          startClientY: number;
          originX: number;
          originY: number;
          originWidth: number;
          originHeight: number;
      };

function LayoutElementView({
    element,
    selected,
    interactive,
    liveInteract = false,
    scale,
    canvasWidth,
    canvasHeight,
    mediaMap,
    widgetMode,
    widgetData,
    isOnline,
    onSelect,
    onElementChange,
}: {
    element: LayoutElement;
    selected: boolean;
    interactive: boolean;
    liveInteract?: boolean;
    scale: number;
    canvasWidth: number;
    canvasHeight: number;
    mediaMap?: LayoutMediaMap;
    widgetMode: WidgetRuntimeMode;
    widgetData?: Record<string, unknown>;
    isOnline?: boolean;
    onSelect?: (id: string | null) => void;
    onElementChange?: (id: string, partial: ElementGeometryPatch) => void;
}) {
    const [drag, setDrag] = useState<DragState | null>(null);
    const [localGeom, setLocalGeom] = useState<{
        x: number;
        y: number;
        width: number;
        height: number;
    } | null>(null);

    const geom = localGeom ?? {
        x: element.x,
        y: element.y,
        width: element.width,
        height: element.height,
    };

    useEffect(() => {
        if (!drag) {
            setLocalGeom(null);
        }
    }, [drag, element.x, element.y, element.width, element.height]);

    const applyGeometry = useCallback(
        (next: { x: number; y: number; width: number; height: number }) => {
            const clamped = clampElementToCanvas(
                next.x,
                next.y,
                next.width,
                next.height,
                canvasWidth,
                canvasHeight,
            );
            setLocalGeom(clamped);
            onElementChange?.(element.id, clamped);
            return clamped;
        },
        [canvasHeight, canvasWidth, element.id, onElementChange],
    );

    const endDrag = useCallback(() => {
        setDrag(null);
        setLocalGeom(null);
    }, []);

    const onPointerMove = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }

            event.preventDefault();
            const logicalDx = (event.clientX - drag.startClientX) / scale;
            const logicalDy = (event.clientY - drag.startClientY) / scale;

            if (drag.kind === 'move') {
                applyGeometry({
                    x: drag.originX + logicalDx,
                    y: drag.originY + logicalDy,
                    width: drag.width,
                    height: drag.height,
                });
                return;
            }

            let {
                originX: x,
                originY: y,
                originWidth: w,
                originHeight: h,
            } = drag;
            const handle = drag.handle;

            if (handle.includes('e')) {
                w = drag.originWidth + logicalDx;
            }
            if (handle.includes('w')) {
                w = drag.originWidth - logicalDx;
                x = drag.originX + logicalDx;
            }
            if (handle.includes('s')) {
                h = drag.originHeight + logicalDy;
            }
            if (handle.includes('n')) {
                h = drag.originHeight - logicalDy;
                y = drag.originY + logicalDy;
            }

            if (w < MIN_ELEMENT_SIZE) {
                if (handle.includes('w')) {
                    x = drag.originX + drag.originWidth - MIN_ELEMENT_SIZE;
                }
                w = MIN_ELEMENT_SIZE;
            }
            if (h < MIN_ELEMENT_SIZE) {
                if (handle.includes('n')) {
                    y = drag.originY + drag.originHeight - MIN_ELEMENT_SIZE;
                }
                h = MIN_ELEMENT_SIZE;
            }

            applyGeometry({ x, y, width: w, height: h });
        },
        [applyGeometry, drag, scale],
    );

    const onPointerUp = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }
            try {
                event.currentTarget.releasePointerCapture(event.pointerId);
            } catch {
                // ignore if already released
            }
            endDrag();
        },
        [drag, endDrag],
    );

    const startMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (!interactive || element.locked || liveInteract) {
            return;
        }
        event.stopPropagation();
        event.preventDefault();
        onSelect?.(element.id);
        event.currentTarget.setPointerCapture(event.pointerId);
        setDrag({
            kind: 'move',
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            originX: element.x,
            originY: element.y,
            width: element.width,
            height: element.height,
        });
        setLocalGeom({
            x: element.x,
            y: element.y,
            width: element.width,
            height: element.height,
        });
    };

    const startResize =
        (handle: ResizeHandle) =>
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (!interactive || element.locked) {
                return;
            }
            event.stopPropagation();
            event.preventDefault();
            onSelect?.(element.id);
            event.currentTarget.setPointerCapture(event.pointerId);
            setDrag({
                kind: 'resize',
                pointerId: event.pointerId,
                handle,
                startClientX: event.clientX,
                startClientY: event.clientY,
                originX: element.x,
                originY: element.y,
                originWidth: element.width,
                originHeight: element.height,
            });
            setLocalGeom({
                x: element.x,
                y: element.y,
                width: element.width,
                height: element.height,
            });
        };

    const live = paintsOutsideScale(element);
    const style: CSSProperties = {
        left: geom.x * scale,
        top: geom.y * scale,
        width: geom.width * scale,
        height: geom.height * scale,
        transform:
            !live && element.rotation
                ? `rotate(${element.rotation}deg)`
                : undefined,
        zIndex: element.zIndex ?? 1,
        cursor: interactive && !element.locked ? 'move' : 'default',
        touchAction: interactive ? 'none' : undefined,
    };

    const content = live ? (
        <ElementContent
            element={element}
            mediaMap={mediaMap}
            widgetMode={widgetMode}
            widgetData={widgetData}
            isOnline={isOnline}
            liveInteract={liveInteract}
        />
    ) : (
        <div
            className="origin-top-left"
            style={{
                width: geom.width,
                height: geom.height,
                transform: `scale(${scale})`,
            }}
        >
            <ElementContent
                element={element}
                mediaMap={mediaMap}
                widgetMode={widgetMode}
                widgetData={widgetData}
                isOnline={isOnline}
                liveInteract={liveInteract}
            />
        </div>
    );

    if (!interactive) {
        return (
            <div
                data-test={`layout-element-${element.id}`}
                className="absolute overflow-hidden"
                style={style}
                aria-label={element.name}
            >
                <div className="h-full w-full overflow-hidden">{content}</div>
            </div>
        );
    }

    return (
        <div
            role="button"
            tabIndex={0}
            aria-pressed={selected}
            aria-label={element.name}
            data-test={`layout-element-${element.id}`}
            data-element-type={element.type}
            onClick={(event) => {
                event.stopPropagation();
                if (!element.locked) {
                    onSelect?.(element.id);
                }
            }}
            onPointerDown={startMove}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
            className={cn(
                'absolute overflow-visible outline-none',
                selected &&
                    'ring-2 ring-[#22D3EE] ring-offset-0 ring-offset-transparent',
                element.locked && 'opacity-80',
            )}
            style={style}
        >
            <div
                className={cn(
                    'h-full w-full overflow-hidden',
                    !liveInteract && 'pointer-events-none',
                    liveInteract && 'pointer-events-auto',
                )}
            >
                {content}
            </div>

            {selected && !element.locked
                ? RESIZE_HANDLES.map((handle) => (
                      <div
                          key={handle.id}
                          role="presentation"
                          data-test={`layout-resize-${handle.id}`}
                          onPointerDown={startResize(handle.id)}
                          onPointerMove={onPointerMove}
                          onPointerUp={onPointerUp}
                          onPointerCancel={onPointerUp}
                          className={cn(
                              'absolute size-3 rounded-sm border-2 border-[#22D3EE] bg-white',
                              handle.className,
                          )}
                          style={{ cursor: handle.cursor, touchAction: 'none' }}
                      />
                  ))
                : null}
        </div>
    );
}

export const ELEMENT_CATALOG: {
    type: LayoutElementType;
    label: string;
    width: number;
    height: number;
}[] = [
    { type: 'text', label: 'Text Placeholder', width: 600, height: 120 },
    { type: 'image', label: 'Image Placeholder', width: 640, height: 360 },
    { type: 'video', label: 'Video Placeholder', width: 800, height: 450 },
    { type: 'logo', label: 'Logo Placeholder', width: 240, height: 120 },
    { type: 'panel', label: 'Panel / Background', width: 960, height: 540 },
    { type: 'shape', label: 'Shape', width: 200, height: 200 },
];

const WIDGET_ICON_MAP: Record<string, LucideIcon> = {
    Clock,
    Timer,
    Cloud,
    Rss,
    Calendar,
    AlertTriangle,
    LayoutTemplate,
    Radio,
};

export const WIDGET_CATALOG = WIDGET_DEFINITIONS.map((def) => ({
    type: def.type as WidgetType,
    label: def.label,
    icon: WIDGET_ICON_MAP[def.icon] ?? Cpu,
    width: def.defaultWidth,
    height: def.defaultHeight,
    defaultConfig: def.defaultConfig,
    category: def.category,
}));

/** @deprecated Prefer ElementIcon via type; kept for catalog consumers. */
export function ElementTypeIcon({
    type,
    className,
}: {
    type: LayoutElementType;
    className?: string;
}) {
    return <ElementIcon type={type} className={className} />;
}
