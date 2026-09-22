import { useState, type ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { csrfHeaders } from '@/lib/csrf';
import { getWidgetDefinition, resolveWidgetType } from '@/lib/widgets/registry';
import {
    embedKindLabel,
    liveMediaStatusLabel,
    resolveEmbedUrl,
    youtubeNeedsLiveLookup,
} from '@/lib/widgets/embed-resolve';
import type {
    AlertWidgetConfig,
    CalendarWidgetConfig,
    ClockWidgetConfig,
    CountdownWidgetConfig,
    EmbedWidgetConfig,
    InfoCardWidgetConfig,
    NewsWidgetConfig,
    WeatherWidgetConfig,
    WidgetType,
} from '@/lib/widgets/types';
import type { LayoutElement, LayoutElementProps } from '@/types/layout-schema';

const COMMON_TIMEZONES = [
    'UTC',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Madrid',
    'Europe/Rome',
    'Europe/Amsterdam',
    'Europe/Warsaw',
    'Europe/Athens',
    'Europe/Moscow',
    'Africa/Algiers',
    'Africa/Cairo',
    'Africa/Johannesburg',
    'Asia/Dubai',
    'Asia/Karachi',
    'Asia/Kolkata',
    'Asia/Bangkok',
    'Asia/Singapore',
    'Asia/Shanghai',
    'Asia/Tokyo',
    'Australia/Sydney',
    'Pacific/Auckland',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Toronto',
    'America/Sao_Paulo',
    'America/Mexico_City',
] as const;

const selectClassName =
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export type WidgetMediaAssetOption = {
    id: string | number;
    name: string;
    type?: string;
    url?: string | null;
};

type WidgetPropertiesProps = {
    element: LayoutElement;
    canEdit: boolean;
    onChange: (propsPatch: LayoutElementProps) => void;
    mediaAssets?: WidgetMediaAssetOption[];
    liveInteract?: boolean;
    onLiveInteractChange?: (next: boolean) => void;
};

function asConfigRecord(value: unknown): Record<string, unknown> {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }
    return {};
}

export function WidgetProperties({
    element,
    canEdit,
    onChange,
    mediaAssets = [],
    liveInteract = false,
    onLiveInteractChange,
}: WidgetPropertiesProps) {
    const type = resolveWidgetType(element.props);
    const def = type ? getWidgetDefinition(type) : null;
    const config = {
        ...(def?.defaultConfig() as Record<string, unknown>),
        ...asConfigRecord(element.props?.config),
    };

    if (!type || !def) {
        return (
            <p className="text-muted-foreground text-xs">
                Unknown or legacy widget.
            </p>
        );
    }

    const patchConfig = (patch: Record<string, unknown>) => {
        onChange({
            widgetType: type,
            config: { ...config, ...patch },
        });
    };

    const patchStyle = (patch: LayoutElementProps) => {
        onChange(patch);
    };

    return (
        <div className="space-y-3" data-test="widget-properties">
            <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                {def.label} widget
            </p>

            {type === 'clock' ? (
                <ClockFields
                    config={config as ClockWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'countdown' ? (
                <CountdownFields
                    config={config as CountdownWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'weather' ? (
                <WeatherFields
                    config={config as WeatherWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'news' ? (
                <NewsFields
                    config={config as NewsWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'calendar' ? (
                <CalendarFields
                    config={config as CalendarWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'alert' ? (
                <AlertFields
                    config={config as AlertWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'info_card' ? (
                <InfoCardFields
                    config={config as InfoCardWidgetConfig}
                    disabled={!canEdit}
                    mediaAssets={mediaAssets}
                    onChange={patchConfig}
                />
            ) : null}
            {type === 'embed' ? (
                <EmbedFields
                    config={config as EmbedWidgetConfig}
                    disabled={!canEdit}
                    onChange={patchConfig}
                    interact={liveInteract}
                    onInteractChange={onLiveInteractChange}
                />
            ) : null}

            <div className="space-y-2 border-t pt-3">
                <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                    Style
                </p>
                <Field label="Text color">
                    <Input
                        type="color"
                        disabled={!canEdit}
                        value={
                            typeof element.props?.color === 'string'
                                ? element.props.color
                                : '#ffffff'
                        }
                        onChange={(e) => patchStyle({ color: e.target.value })}
                    />
                </Field>
                <Field label="Fill">
                    <Input
                        disabled={!canEdit}
                        value={
                            typeof element.props?.fill === 'string'
                                ? element.props.fill
                                : ''
                        }
                        placeholder="rgba(...) or hex"
                        onChange={(e) => patchStyle({ fill: e.target.value })}
                    />
                </Field>
                <Field label="Font size">
                    <Input
                        type="number"
                        disabled={!canEdit}
                        value={
                            typeof element.props?.fontSize === 'number'
                                ? element.props.fontSize
                                : 28
                        }
                        onChange={(e) =>
                            patchStyle({ fontSize: Number(e.target.value) })
                        }
                    />
                </Field>
                <Field label="Align">
                    <select
                        className={selectClassName}
                        disabled={!canEdit}
                        value={element.props?.textAlign ?? 'center'}
                        onChange={(e) =>
                            patchStyle({
                                textAlign: e.target.value as
                                    | 'left'
                                    | 'center'
                                    | 'right',
                            })
                        }
                    >
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                    </select>
                </Field>
            </div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <Label className="text-xs">{label}</Label>
            {children}
        </div>
    );
}

function CheckboxRow({
    label,
    checked,
    disabled,
    onChange,
}: {
    label: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-xs">
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}

function TimezoneSelect({
    value,
    disabled,
    onChange,
}: {
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}) {
    const zones = COMMON_TIMEZONES.includes(
        value as (typeof COMMON_TIMEZONES)[number],
    )
        ? COMMON_TIMEZONES
        : ([value, ...COMMON_TIMEZONES] as string[]);

    return (
        <select
            className={selectClassName}
            disabled={disabled}
            value={value || 'UTC'}
            data-test="widget-prop-timezone"
            onChange={(e) => onChange(e.target.value)}
        >
            {zones.map((zone) => (
                <option key={zone} value={zone}>
                    {zone}
                </option>
            ))}
        </select>
    );
}

function ClockFields({
    config,
    disabled,
    onChange,
}: {
    config: ClockWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<ClockWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Timezone">
                <TimezoneSelect
                    value={config.timezone}
                    disabled={disabled}
                    onChange={(timezone) => onChange({ timezone })}
                />
            </Field>
            <Field label="Hour format">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.hourFormat}
                    onChange={(e) =>
                        onChange({
                            hourFormat: e.target.value as '12' | '24',
                        })
                    }
                >
                    <option value="24">24-hour</option>
                    <option value="12">12-hour</option>
                </select>
            </Field>
            <Field label="Date format">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.dateFormat}
                    onChange={(e) =>
                        onChange({
                            dateFormat: e.target.value as 'short' | 'long',
                        })
                    }
                >
                    <option value="long">Long</option>
                    <option value="short">Short</option>
                </select>
            </Field>
            <CheckboxRow
                label="Show seconds"
                checked={config.showSeconds}
                disabled={disabled}
                onChange={(showSeconds) => onChange({ showSeconds })}
            />
            <CheckboxRow
                label="Show date"
                checked={config.showDate}
                disabled={disabled}
                onChange={(showDate) => onChange({ showDate })}
            />
            <CheckboxRow
                label="Show weekday"
                checked={config.showWeekday}
                disabled={disabled}
                onChange={(showWeekday) => onChange({ showWeekday })}
            />
        </div>
    );
}

function CountdownFields({
    config,
    disabled,
    onChange,
}: {
    config: CountdownWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<CountdownWidgetConfig>) => void;
}) {
    const localValue = (() => {
        const ms = Date.parse(config.targetAt);
        if (!Number.isFinite(ms)) {
            return '';
        }
        const d = new Date(ms);
        const pad = (n: number) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    })();

    return (
        <div className="space-y-2">
            <Field label="Title">
                <Input
                    disabled={disabled}
                    value={config.title}
                    onChange={(e) => onChange({ title: e.target.value })}
                />
            </Field>
            <Field label="Target">
                <Input
                    type="datetime-local"
                    disabled={disabled}
                    value={localValue}
                    data-test="widget-prop-target-at"
                    onChange={(e) => {
                        const next = e.target.value
                            ? new Date(e.target.value).toISOString()
                            : config.targetAt;
                        onChange({ targetAt: next });
                    }}
                />
            </Field>
            <Field label="Timezone">
                <TimezoneSelect
                    value={config.timezone}
                    disabled={disabled}
                    onChange={(timezone) => onChange({ timezone })}
                />
            </Field>
            <Field label="Completion message">
                <Input
                    disabled={disabled}
                    value={config.completionMessage}
                    onChange={(e) =>
                        onChange({ completionMessage: e.target.value })
                    }
                />
            </Field>
            <CheckboxRow
                label="Days"
                checked={config.showDays}
                disabled={disabled}
                onChange={(showDays) => onChange({ showDays })}
            />
            <CheckboxRow
                label="Hours"
                checked={config.showHours}
                disabled={disabled}
                onChange={(showHours) => onChange({ showHours })}
            />
            <CheckboxRow
                label="Minutes"
                checked={config.showMinutes}
                disabled={disabled}
                onChange={(showMinutes) => onChange({ showMinutes })}
            />
            <CheckboxRow
                label="Seconds"
                checked={config.showSeconds}
                disabled={disabled}
                onChange={(showSeconds) => onChange({ showSeconds })}
            />
        </div>
    );
}

function WeatherFields({
    config,
    disabled,
    onChange,
}: {
    config: WeatherWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<WeatherWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Location">
                <Input
                    disabled={disabled}
                    value={config.location}
                    data-test="widget-prop-location"
                    onChange={(e) => onChange({ location: e.target.value })}
                />
            </Field>
            <Field label="Units">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.units}
                    onChange={(e) =>
                        onChange({ units: e.target.value as 'c' | 'f' })
                    }
                >
                    <option value="c">Celsius</option>
                    <option value="f">Fahrenheit</option>
                </select>
            </Field>
            <Field label="Layout">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.layout}
                    onChange={(e) =>
                        onChange({
                            layout: e.target.value as 'stack' | 'inline',
                        })
                    }
                >
                    <option value="stack">Stack</option>
                    <option value="inline">Inline</option>
                </select>
            </Field>
            <CheckboxRow
                label="Show temperature"
                checked={config.showTemp}
                disabled={disabled}
                onChange={(showTemp) => onChange({ showTemp })}
            />
            <CheckboxRow
                label="Show condition"
                checked={config.showCondition}
                disabled={disabled}
                onChange={(showCondition) => onChange({ showCondition })}
            />
            <CheckboxRow
                label="Show high / low"
                checked={config.showHighLow}
                disabled={disabled}
                onChange={(showHighLow) => onChange({ showHighLow })}
            />
        </div>
    );
}

function NewsFields({
    config,
    disabled,
    onChange,
}: {
    config: NewsWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<NewsWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Heading">
                <Input
                    disabled={disabled}
                    value={config.heading}
                    onChange={(e) => onChange({ heading: e.target.value })}
                />
            </Field>
            <Field label="Feed URL">
                <Input
                    disabled={disabled}
                    value={config.feedUrl}
                    placeholder="https://…"
                    data-test="widget-prop-feed-url"
                    onChange={(e) => onChange({ feedUrl: e.target.value })}
                />
            </Field>
            <Field label="Max items">
                <Input
                    type="number"
                    min={1}
                    max={20}
                    disabled={disabled}
                    value={config.maxItems}
                    onChange={(e) =>
                        onChange({ maxItems: Number(e.target.value) || 1 })
                    }
                />
            </Field>
            <Field label="Rotate seconds">
                <Input
                    type="number"
                    min={2}
                    disabled={disabled}
                    value={config.rotateSeconds}
                    onChange={(e) =>
                        onChange({
                            rotateSeconds: Number(e.target.value) || 8,
                        })
                    }
                />
            </Field>
            <Field label="Layout">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.layout}
                    onChange={(e) =>
                        onChange({
                            layout: e.target.value as
                                | 'rotate'
                                | 'ticker'
                                | 'list',
                        })
                    }
                >
                    <option value="rotate">Rotate</option>
                    <option value="ticker">Ticker</option>
                    <option value="list">List</option>
                </select>
            </Field>
            <CheckboxRow
                label="Show source"
                checked={config.showSource}
                disabled={disabled}
                onChange={(showSource) => onChange({ showSource })}
            />
            <CheckboxRow
                label="Show timestamp"
                checked={config.showTimestamp}
                disabled={disabled}
                onChange={(showTimestamp) => onChange({ showTimestamp })}
            />
        </div>
    );
}

function CalendarFields({
    config,
    disabled,
    onChange,
}: {
    config: CalendarWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<CalendarWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Title">
                <Input
                    disabled={disabled}
                    value={config.title}
                    onChange={(e) => onChange({ title: e.target.value })}
                />
            </Field>
            <Field label="Feed URL">
                <Input
                    disabled={disabled}
                    value={config.feedUrl}
                    placeholder="https://…"
                    data-test="widget-prop-calendar-feed-url"
                    onChange={(e) => onChange({ feedUrl: e.target.value })}
                />
            </Field>
            <Field label="Timezone">
                <TimezoneSelect
                    value={config.timezone}
                    disabled={disabled}
                    onChange={(timezone) => onChange({ timezone })}
                />
            </Field>
            <Field label="Max events">
                <Input
                    type="number"
                    min={1}
                    max={20}
                    disabled={disabled}
                    value={config.maxEvents}
                    onChange={(e) =>
                        onChange({ maxEvents: Number(e.target.value) || 1 })
                    }
                />
            </Field>
            <Field label="Layout">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.layout}
                    onChange={(e) =>
                        onChange({
                            layout: e.target.value as 'list' | 'compact',
                        })
                    }
                >
                    <option value="list">List</option>
                    <option value="compact">Compact</option>
                </select>
            </Field>
            <CheckboxRow
                label="Show date"
                checked={config.showDate}
                disabled={disabled}
                onChange={(showDate) => onChange({ showDate })}
            />
            <CheckboxRow
                label="Show time"
                checked={config.showTime}
                disabled={disabled}
                onChange={(showTime) => onChange({ showTime })}
            />
            <CheckboxRow
                label="Show location"
                checked={config.showLocation}
                disabled={disabled}
                onChange={(showLocation) => onChange({ showLocation })}
            />
        </div>
    );
}

function AlertFields({
    config,
    disabled,
    onChange,
}: {
    config: AlertWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<AlertWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Title">
                <Input
                    disabled={disabled}
                    value={config.title}
                    onChange={(e) => onChange({ title: e.target.value })}
                />
            </Field>
            <Field label="Message">
                <textarea
                    className={`${selectClassName} min-h-16 py-2`}
                    disabled={disabled}
                    value={config.message}
                    onChange={(e) => onChange({ message: e.target.value })}
                />
            </Field>
            <Field label="Severity">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.severity}
                    onChange={(e) =>
                        onChange({
                            severity: e.target
                                .value as AlertWidgetConfig['severity'],
                            icon: e.target.value as AlertWidgetConfig['icon'],
                        })
                    }
                >
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="success">Success</option>
                </select>
            </Field>
            <Field label="Alignment">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.alignment}
                    onChange={(e) =>
                        onChange({
                            alignment: e.target.value as
                                | 'left'
                                | 'center'
                                | 'right',
                        })
                    }
                >
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                </select>
            </Field>
        </div>
    );
}

function InfoCardFields({
    config,
    disabled,
    mediaAssets,
    onChange,
}: {
    config: InfoCardWidgetConfig;
    disabled: boolean;
    mediaAssets: WidgetMediaAssetOption[];
    onChange: (patch: Partial<InfoCardWidgetConfig>) => void;
}) {
    return (
        <div className="space-y-2">
            <Field label="Heading">
                <Input
                    disabled={disabled}
                    value={config.heading}
                    onChange={(e) => onChange({ heading: e.target.value })}
                />
            </Field>
            <Field label="Subheading">
                <Input
                    disabled={disabled}
                    value={config.subheading}
                    onChange={(e) => onChange({ subheading: e.target.value })}
                />
            </Field>
            <Field label="Value">
                <Input
                    disabled={disabled}
                    value={config.value}
                    onChange={(e) => onChange({ value: e.target.value })}
                />
            </Field>
            <Field label="Body">
                <textarea
                    className={`${selectClassName} min-h-16 py-2`}
                    disabled={disabled}
                    value={config.body}
                    onChange={(e) => onChange({ body: e.target.value })}
                />
            </Field>
            <Field label="Footer">
                <Input
                    disabled={disabled}
                    value={config.footer}
                    onChange={(e) => onChange({ footer: e.target.value })}
                />
            </Field>
            <Field label="Layout">
                <select
                    className={selectClassName}
                    disabled={disabled}
                    value={config.layout}
                    onChange={(e) =>
                        onChange({
                            layout: e.target.value as 'stack' | 'split',
                        })
                    }
                >
                    <option value="stack">Stack</option>
                    <option value="split">Split</option>
                </select>
            </Field>
            {mediaAssets.length > 0 ? (
                <Field label="Media">
                    <select
                        className={selectClassName}
                        disabled={disabled}
                        value={
                            config.mediaAssetId == null
                                ? ''
                                : String(config.mediaAssetId)
                        }
                        onChange={(e) => {
                            const value = e.target.value;
                            onChange({
                                mediaAssetId: value === '' ? null : value,
                            });
                        }}
                    >
                        <option value="">None</option>
                        {mediaAssets.map((asset) => (
                            <option key={asset.id} value={String(asset.id)}>
                                {asset.name}
                            </option>
                        ))}
                    </select>
                </Field>
            ) : (
                <p className="text-muted-foreground text-[11px]">
                    Select media from the Media tab to attach an image.
                </p>
            )}
        </div>
    );
}

function EmbedFields({
    config,
    disabled,
    onChange,
    interact,
    onInteractChange,
}: {
    config: EmbedWidgetConfig;
    disabled: boolean;
    onChange: (patch: Partial<EmbedWidgetConfig>) => void;
    interact?: boolean;
    onInteractChange?: (next: boolean) => void;
}) {
    const resolved = resolveEmbedUrl(config.url ?? '', {
        kind: config.kind ?? config.provider,
        autoplay: config.autoplay ?? true,
        muted: config.muted ?? true,
        loop: config.loop ?? true,
        controls: config.controls ?? true,
        volume: config.volume ?? 70,
    });
    const [testState, setTestState] = useState<
        'idle' | 'testing' | 'ok' | 'fail'
    >('idle');
    const [testMessage, setTestMessage] = useState<string | null>(null);

    const status = liveMediaStatusLabel(resolved);
    const isPlayable =
        resolved.kind === 'youtube' ||
        resolved.kind === 'vimeo' ||
        resolved.kind === 'hls' ||
        resolved.kind === 'video';

    const runTest = async () => {
        if (config.url.trim() === '') {
            return;
        }
        setTestState('testing');
        setTestMessage(null);
        try {
            const response = await fetch('/app/widgets/data', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ embed: { url: config.url } }),
            });
            const body = (await response.json().catch(() => null)) as {
                data?: {
                    embed?: {
                        kind?: string;
                        message?: string;
                        play_url?: string;
                    };
                };
                errors?: { url?: string[] };
                message?: string;
            } | null;
            if (!response.ok) {
                setTestState('fail');
                setTestMessage(
                    body?.errors?.url?.[0] ??
                        body?.message ??
                        'This URL could not be embedded.',
                );
                return;
            }
            const embed = body?.data?.embed;
            if (
                !embed?.play_url ||
                embed.kind === 'blocked' ||
                embed.kind === 'drm' ||
                embed.kind === 'unsupported' ||
                embed.kind === 'dash'
            ) {
                setTestState('fail');
                setTestMessage(
                    embed?.message ?? 'This source is not playable.',
                );
                return;
            }
            setTestState('ok');
            setTestMessage('Supported stream — ready to place on the Design.');
            if (embed.kind) {
                onChange({
                    kind: embed.kind as EmbedWidgetConfig['kind'],
                    source_url: config.url,
                });
            }
        } catch {
            setTestState('fail');
            setTestMessage('Could not reach the source checker.');
        }
    };

    return (
        <div className="space-y-2">
            <Field label="Source URL">
                <Input
                    disabled={disabled}
                    value={config.url}
                    placeholder="https://youtube.com/watch?v=… or .m3u8 / .mp4"
                    data-test="embed-url-input"
                    onChange={(e) => {
                        setTestState('idle');
                        onChange({
                            url: e.target.value,
                            source_url: e.target.value,
                            kind: undefined,
                        });
                    }}
                />
            </Field>
            {config.url.trim() !== '' ? (
                <div
                    className="border-border bg-muted/40 space-y-1.5 rounded-md border px-2.5 py-2 text-xs"
                    data-test="embed-resolved-kind"
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="font-medium">
                            Detected:{' '}
                            {resolved.needsLookup ||
                            youtubeNeedsLiveLookup(config.url) ||
                            /\/live\//.test(config.url)
                                ? 'YouTube Live'
                                : embedKindLabel(resolved.kind)}
                        </span>
                        <span
                            className={
                                status === 'supported'
                                    ? 'text-emerald-600'
                                    : status === 'restricted'
                                      ? 'text-amber-600'
                                      : status === 'resolving'
                                        ? 'text-muted-foreground'
                                        : 'text-destructive'
                            }
                            data-test="embed-source-status"
                        >
                            {status === 'supported'
                                ? '✓ Supported'
                                : status === 'restricted'
                                  ? '⚠ Embedding restricted'
                                  : status === 'resolving'
                                    ? '… Resolving'
                                    : '✕ Stream unavailable'}
                        </span>
                    </div>
                    {resolved.message ? (
                        <p className="text-muted-foreground">
                            {resolved.message}
                        </p>
                    ) : null}
                    <button
                        type="button"
                        disabled={disabled || testState === 'testing'}
                        className="bg-secondary hover:bg-accent rounded-md px-2 py-1 text-[11px] font-medium disabled:opacity-50"
                        data-test="embed-test-source"
                        onClick={() => void runTest()}
                    >
                        {testState === 'testing' ? 'Testing…' : 'Test Source'}
                    </button>
                    {testMessage ? (
                        <p
                            className={
                                testState === 'ok'
                                    ? 'text-emerald-600'
                                    : 'text-destructive'
                            }
                            data-test="embed-test-result"
                        >
                            {testMessage}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {isPlayable ? (
                <div className="space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                disabled={disabled}
                                checked={config.autoplay ?? true}
                                onChange={(e) =>
                                    onChange({ autoplay: e.target.checked })
                                }
                            />
                            Autoplay
                        </label>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                disabled={disabled}
                                checked={config.muted ?? true}
                                onChange={(e) =>
                                    onChange({ muted: e.target.checked })
                                }
                            />
                            Muted
                        </label>
                        {!resolved.isLive ? (
                            <label className="flex items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    disabled={disabled}
                                    checked={config.loop ?? true}
                                    onChange={(e) =>
                                        onChange({ loop: e.target.checked })
                                    }
                                />
                                Loop
                            </label>
                        ) : null}
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                disabled={disabled}
                                checked={config.controls ?? true}
                                onChange={(e) =>
                                    onChange({ controls: e.target.checked })
                                }
                            />
                            Show controls
                        </label>
                    </div>
                    <Field label={`Volume (${config.volume ?? 70})`}>
                        <Input
                            type="range"
                            min={0}
                            max={100}
                            disabled={disabled}
                            value={config.volume ?? 70}
                            data-test="embed-volume"
                            onChange={(e) =>
                                onChange({ volume: Number(e.target.value) })
                            }
                        />
                    </Field>
                    {onInteractChange ? (
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                disabled={disabled}
                                checked={Boolean(interact)}
                                onChange={(e) =>
                                    onInteractChange(e.target.checked)
                                }
                                data-test="embed-interact-mode"
                            />
                            Interact with video (disable drag)
                        </label>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

/** Exported for editors that need type labels. */
export type { WidgetType };
