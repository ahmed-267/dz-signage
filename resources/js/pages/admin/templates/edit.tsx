import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Eye,
    Image as ImageIcon,
    Layers,
    Redo2,
    Square,
    Trash2,
    Type,
    Undo2,
    Video,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    ELEMENT_CATALOG,
    LayoutRenderer,
    WIDGET_CATALOG,
    type ElementGeometryPatch,
} from '@/components/rendering/layout-renderer';
import { WidgetProperties } from '@/components/widgets/widget-properties';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    createWidgetElementProps,
    widgetCatalogGrouped,
} from '@/lib/widgets/registry';
import type { WidgetType } from '@/lib/widgets/types';
import { cn } from '@/lib/utils';
import { templates as templatesIndex } from '@/routes/admin';
import templateRoutes from '@/routes/admin/templates';
import {
    blankLayoutSchema,
    createElementId,
    isLayoutSchema,
    type LayoutElement,
    type LayoutElementType,
    type LayoutOrientation,
    type LayoutSchema,
} from '@/types/layout-schema';
import type { AdminTemplateEditProps } from '@/types/template';

const THEME_BACKGROUNDS: Record<string, string> = {
    minimal: '#F8F9FA',
    modern: '#EEF2FF',
    bold: '#111827',
    corporate: '#F1F5F9',
    elegant: '#FAF7F2',
    clean: '#FFFFFF',
    dark: '#0F172A',
    light: '#FCFCFC',
    colourful: '#FFF7ED',
    islamic: '#ECFDF5',
    blank: '#FFFFFF',
};

const HISTORY_LIMIT = 50;

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const textareaClassName = cn(
    'border-input flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
    'placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);

function resolveSchema(
    template: AdminTemplateEditProps['template'],
): LayoutSchema {
    if (isLayoutSchema(template.schema)) {
        return structuredClone(template.schema);
    }

    return blankLayoutSchema(
        (template.orientation === 'portrait'
            ? 'portrait'
            : 'landscape') as LayoutOrientation,
        template.theme,
        THEME_BACKGROUNDS[template.theme] ?? THEME_BACKGROUNDS.blank,
    );
}

function ElementTypeIcon({ type }: { type: LayoutElementType }) {
    const props = { className: 'size-4 shrink-0' };
    switch (type) {
        case 'text':
            return <Type {...props} />;
        case 'image':
        case 'logo':
            return <ImageIcon {...props} />;
        case 'video':
            return <Video {...props} />;
        default:
            return <Square {...props} />;
    }
}

export default function AdminTemplateEdit({
    template,
    categories,
    themes,
    industries,
}: AdminTemplateEditProps) {
    const [schema, setSchema] = useState<LayoutSchema>(() =>
        resolveSchema(template),
    );
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [leftTab, setLeftTab] = useState<'elements' | 'widgets'>('elements');
    const [saving, setSaving] = useState(false);
    const [undoStack, setUndoStack] = useState<LayoutSchema[]>([]);
    const [redoStack, setRedoStack] = useState<LayoutSchema[]>([]);

    const schemaRef = useRef(schema);
    const pendingUndoRef = useRef<LayoutSchema | null>(null);
    const canvasHostRef = useRef<HTMLDivElement>(null);
    const [canvasFit, setCanvasFit] = useState({ width: 720, height: 480 });

    schemaRef.current = schema;

    const form = useForm({
        name: template.name,
        description: template.description ?? '',
        category: template.category,
        industry: template.industry ?? '',
        theme: template.theme,
    });

    const selected = useMemo(
        () => schema.elements.find((el) => el.id === selectedId) ?? null,
        [schema.elements, selectedId],
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
    }, []);

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
            setUndoStack((undo) => [
                ...undo,
                structuredClone(schemaRef.current),
            ]);
            setSchema(structuredClone(next));
            return prev.slice(0, -1);
        });
    }, [flushPendingUndo]);

    const addElement = useCallback(
        (
            type: LayoutElementType,
            label: string,
            width: number,
            height: number,
            widgetType?: WidgetType,
        ) => {
            const current = schemaRef.current;
            const offset = current.elements.length * 24;
            const element: LayoutElement = {
                id: createElementId(),
                type,
                name: label,
                x: Math.min(
                    80 + offset,
                    Math.max(0, current.canvas.width - width),
                ),
                y: Math.min(
                    80 + offset,
                    Math.max(0, current.canvas.height - height),
                ),
                width,
                height,
                zIndex: current.elements.length + 1,
                locked: false,
                editable: true,
                props: widgetType
                    ? createWidgetElementProps(widgetType)
                    : undefined,
            };
            pushHistory({
                ...current,
                elements: [...current.elements, element],
            });
            setSelectedId(element.id);
        },
        [pushHistory],
    );

    const updateElementProps = useCallback(
        (id: string, propsPatch: LayoutElement['props']) => {
            pushHistory({
                ...schemaRef.current,
                elements: schemaRef.current.elements.map((el) => {
                    if (el.id !== id) {
                        return el;
                    }

                    const nextProps: LayoutElement['props'] = {
                        ...el.props,
                        ...propsPatch,
                    };

                    for (const key of Object.keys(propsPatch ?? {})) {
                        if (
                            propsPatch &&
                            propsPatch[key as keyof typeof propsPatch] ===
                                undefined
                        ) {
                            delete nextProps[key];
                        }
                    }

                    return {
                        ...el,
                        props: nextProps,
                    };
                }),
            });
        },
        [pushHistory],
    );

    function handleSave() {
        flushPendingUndo();
        setSaving(true);
        router.patch(
            templateRoutes.update.url(template.id),
            {
                name: form.data.name,
                description: form.data.description || null,
                category: form.data.category,
                industry: form.data.industry || null,
                theme: form.data.theme,
                schema,
            } as never,
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    function handlePublish() {
        router.post(
            templateRoutes.publish.url(template.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }

    function handleDelete() {
        router.delete(templateRoutes.destroy.url(template.id));
    }

    const isPortrait = schema.canvas.orientation === 'portrait';

    return (
        <>
            <Head title={`Edit · ${template.name}`} />
            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mb-2 -ml-2"
                            asChild
                        >
                            <Link href={templatesIndex.url()}>
                                ← Platform templates
                            </Link>
                        </Button>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {form.data.name || template.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge variant="neutral" className="capitalize">
                                {template.orientation}
                            </Badge>
                            <Badge
                                variant={
                                    template.status === 'published'
                                        ? 'success'
                                        : 'warning'
                                }
                            >
                                {template.status}
                            </Badge>
                            {template.latest_version_number ? (
                                <Badge variant="info">
                                    v{template.latest_version_number}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={undoStack.length === 0}
                            onClick={undo}
                            aria-label="Undo"
                            data-test="template-builder-undo"
                        >
                            <Undo2 className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled={redoStack.length === 0}
                            onClick={redo}
                            aria-label="Redo"
                            data-test="template-builder-redo"
                        >
                            <Redo2 className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewOpen(true)}
                        >
                            <Eye className="size-4" />
                            Preview
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={saving}
                            onClick={handleSave}
                            data-test="template-builder-save"
                        >
                            {saving ? <Spinner /> : null}
                            Save draft
                        </Button>
                        <Button
                            type="button"
                            onClick={handlePublish}
                            data-test="template-builder-publish"
                        >
                            Publish
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setDeleteOpen(true)}
                        >
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                    <div className="border-border bg-card space-y-4 rounded-xl border p-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                className={textareaClassName}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                            <InputError message={form.errors.description} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="theme">Theme</Label>
                            <select
                                id="theme"
                                className={selectClassName}
                                value={form.data.theme}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    form.setData('theme', value);
                                    pushHistory({
                                        ...schemaRef.current,
                                        theme: value,
                                        canvas: {
                                            ...schemaRef.current.canvas,
                                            background: {
                                                type: 'color',
                                                value:
                                                    THEME_BACKGROUNDS[value] ??
                                                    schemaRef.current.canvas
                                                        .background.value,
                                            },
                                        },
                                    });
                                }}
                            >
                                {themes.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="category">Category</Label>
                            <select
                                id="category"
                                className={selectClassName}
                                value={form.data.category}
                                onChange={(e) =>
                                    form.setData('category', e.target.value)
                                }
                            >
                                {categories.map((c) => (
                                    <option key={c.value} value={c.value}>
                                        {c.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="industry">Industry</Label>
                            <select
                                id="industry"
                                className={selectClassName}
                                value={form.data.industry}
                                onChange={(e) =>
                                    form.setData('industry', e.target.value)
                                }
                            >
                                <option value="">None</option>
                                {industries.map((i) => (
                                    <option key={i.value} value={i.value}>
                                        {i.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="border-border border-t pt-4">
                            <div className="mb-2 flex border-b">
                                {(
                                    [
                                        { id: 'elements', label: 'Elements' },
                                        { id: 'widgets', label: 'Widgets' },
                                    ] as const
                                ).map((tab) => (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        onClick={() => setLeftTab(tab.id)}
                                        className={cn(
                                            'flex-1 border-b-2 py-2 text-xs font-medium',
                                            leftTab === tab.id
                                                ? 'border-primary text-primary'
                                                : 'text-muted-foreground border-transparent',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                            <div className="max-h-64 space-y-1 overflow-y-auto">
                                {leftTab === 'elements'
                                    ? ELEMENT_CATALOG.map((item) => (
                                          <button
                                              key={item.type}
                                              type="button"
                                              data-test={`template-add-${item.type}`}
                                              onClick={() =>
                                                  addElement(
                                                      item.type,
                                                      item.label,
                                                      item.width,
                                                      item.height,
                                                  )
                                              }
                                              className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
                                          >
                                              <ElementTypeIcon
                                                  type={item.type}
                                              />
                                              {item.label}
                                          </button>
                                      ))
                                    : widgetCatalogGrouped().map((group) => (
                                          <div
                                              key={group.category}
                                              className="space-y-1"
                                          >
                                              <p className="text-muted-foreground px-1 pt-1 text-[10px] font-semibold tracking-wide uppercase">
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
                                                      catalog?.icon ?? Square;
                                                  return (
                                                      <button
                                                          key={widget.type}
                                                          type="button"
                                                          data-test={`template-add-widget-${widget.type}`}
                                                          onClick={() =>
                                                              addElement(
                                                                  'widget',
                                                                  widget.label,
                                                                  widget.defaultWidth,
                                                                  widget.defaultHeight,
                                                                  widget.type,
                                                              )
                                                          }
                                                          className="bg-secondary hover:bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
                                                      >
                                                          <Icon className="size-4" />
                                                          {widget.label}
                                                      </button>
                                                  );
                                              })}
                                          </div>
                                      ))}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div
                            ref={canvasHostRef}
                            className="border-border bg-muted/30 flex min-h-[420px] items-center justify-center overflow-hidden rounded-xl border p-4"
                            data-test="template-builder-canvas"
                        >
                            <LayoutRenderer
                                schema={schema}
                                mode="editor"
                                fitWidth={canvasFit.width}
                                fitHeight={canvasFit.height}
                                interactive
                                selectedId={selectedId}
                                onSelect={setSelectedId}
                                onElementChange={handleElementChange}
                            />
                        </div>

                        {selected ? (
                            <div className="border-border bg-card space-y-3 rounded-xl border p-4">
                                <div className="flex items-center justify-between">
                                    <p className="flex items-center gap-2 text-sm font-medium">
                                        <Layers className="size-4" />
                                        Selected element
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => {
                                            pushHistory({
                                                ...schemaRef.current,
                                                elements:
                                                    schemaRef.current.elements.filter(
                                                        (el) =>
                                                            el.id !==
                                                            selected.id,
                                                    ),
                                            });
                                            setSelectedId(null);
                                        }}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-1 sm:col-span-2">
                                        <Label>Name</Label>
                                        <Input
                                            value={selected.name}
                                            onChange={(e) =>
                                                updateElement(selected.id, {
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    {(
                                        ['x', 'y', 'width', 'height'] as const
                                    ).map((key) => (
                                        <div key={key} className="space-y-1">
                                            <Label className="capitalize">
                                                {key}
                                            </Label>
                                            <Input
                                                type="number"
                                                data-test={`template-prop-${key}`}
                                                value={selected[key]}
                                                onChange={(e) =>
                                                    updateElement(selected.id, {
                                                        [key]: Number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                                {selected.type === 'text' ? (
                                    <div className="space-y-1">
                                        <Label>Content source</Label>
                                        <select
                                            className={selectClassName}
                                            data-test="template-prop-brand-content"
                                            value={
                                                selected.props?.brandBinding ===
                                                'brand.business_name'
                                                    ? 'brand.business_name'
                                                    : selected.props
                                                            ?.brandBinding ===
                                                        'brand.tagline'
                                                      ? 'brand.tagline'
                                                      : 'static'
                                            }
                                            onChange={(e) => {
                                                const value = e.target.value;
                                                if (value === 'static') {
                                                    const {
                                                        brandBinding: _removed,
                                                        ...rest
                                                    } = selected.props ?? {};
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            ...rest,
                                                            brandBinding:
                                                                undefined,
                                                        },
                                                    );
                                                    return;
                                                }
                                                updateElementProps(
                                                    selected.id,
                                                    {
                                                        brandBinding: value as
                                                            | 'brand.business_name'
                                                            | 'brand.tagline',
                                                    },
                                                );
                                            }}
                                        >
                                            <option value="static">
                                                Static
                                            </option>
                                            <option value="brand.business_name">
                                                Brand name
                                            </option>
                                            <option value="brand.tagline">
                                                Brand tagline
                                            </option>
                                        </select>
                                    </div>
                                ) : null}
                                {selected.type === 'logo' ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            data-test="template-prop-brand-logo"
                                            checked={
                                                selected.props?.brandBinding ===
                                                'brand.logo'
                                            }
                                            onChange={(e) => {
                                                if (e.target.checked) {
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            brandBinding:
                                                                'brand.logo',
                                                        },
                                                    );
                                                    return;
                                                }
                                                const {
                                                    brandBinding: _removed,
                                                    ...rest
                                                } = selected.props ?? {};
                                                updateElementProps(
                                                    selected.id,
                                                    {
                                                        ...rest,
                                                        brandBinding: undefined,
                                                    },
                                                );
                                            }}
                                        />
                                        Brand logo
                                    </label>
                                ) : null}
                                {selected.type === 'panel' ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            data-test="template-prop-brand-primary"
                                            checked={
                                                selected.props?.brandBinding ===
                                                'brand.primary_color'
                                            }
                                            onChange={(e) => {
                                                if (e.target.checked) {
                                                    updateElementProps(
                                                        selected.id,
                                                        {
                                                            brandBinding:
                                                                'brand.primary_color',
                                                        },
                                                    );
                                                    return;
                                                }
                                                const {
                                                    brandBinding: _removed,
                                                    ...rest
                                                } = selected.props ?? {};
                                                updateElementProps(
                                                    selected.id,
                                                    {
                                                        ...rest,
                                                        brandBinding: undefined,
                                                    },
                                                );
                                            }}
                                        />
                                        Use brand primary colour
                                    </label>
                                ) : null}
                                {selected.type === 'widget' ? (
                                    <WidgetProperties
                                        element={selected}
                                        canEdit
                                        onChange={(propsPatch) =>
                                            updateElementProps(
                                                selected.id,
                                                propsPatch,
                                            )
                                        }
                                    />
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>

            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-[960px]">
                    <DialogHeader>
                        <DialogTitle>Preview · {form.data.name}</DialogTitle>
                        <DialogDescription>
                            Fit mode — scaled to available width and height
                        </DialogDescription>
                    </DialogHeader>
                    <div className="bg-muted/40 flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg p-4">
                        <LayoutRenderer
                            schema={schema}
                            mode="preview"
                            fitWidth={isPortrait ? 360 : 880}
                            fitHeight={isPortrait ? 640 : 495}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete platform template</DialogTitle>
                        <DialogDescription>
                            This removes “{template.name}” for all businesses
                            and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminTemplateEdit.layout = {
    breadcrumbs: [
        { title: 'Templates', href: '/admin/templates' },
        { title: 'Edit', href: '#' },
    ],
};
