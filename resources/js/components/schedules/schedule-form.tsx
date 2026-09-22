import { router, useForm } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, MoonStar } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { ScheduleConflictBanner } from '@/components/schedules/schedule-conflict-banner';
import { ScheduleDayPicker } from '@/components/schedules/schedule-day-picker';
import {
    crossesMidnight,
    describeDays,
    formatTimeRange,
    formatWindow,
    toTimeInput,
} from '@/components/schedules/schedule-format';
import { LayoutRenderer } from '@/components/rendering/layout-renderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { csrfHeaders } from '@/lib/csrf';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import scheduleRoutes from '@/routes/app/schedules';
import { isLayoutSchema, normalizeLayoutSchema } from '@/types/layout-schema';
import type {
    ScheduleConfig,
    ScheduleConflict,
    ScheduleConflictsPayload,
    ScheduleDetail,
    ScheduleFormProps,
    ScheduleFormValues,
    SchedulePlaylistOption,
} from '@/types/schedule';

type ScheduleFormComponentProps = ScheduleFormProps & {
    /** Absent on create — the schedule does not exist yet. */
    schedule?: ScheduleDetail;
    canEdit: boolean;
};

type StepId = 'basics' | 'content' | 'timing' | 'tvs' | 'review';

const STEPS: { id: StepId; label: string }[] = [
    { id: 'basics', label: 'Basics' },
    { id: 'content', label: 'Content' },
    { id: 'timing', label: 'Timing' },
    { id: 'tvs', label: ProductLabels.displayPlural },
    { id: 'review', label: 'Review' },
];

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function initialValues(
    schedule: ScheduleDetail | undefined,
    config: ScheduleConfig,
    workspaceTimezone: string,
): ScheduleFormValues {
    if (schedule) {
        return {
            name: schedule.name,
            description: schedule.description ?? '',
            playlist_id: schedule.playlist_id
                ? String(schedule.playlist_id)
                : '',
            screen_ids: schedule.screen_ids,
            timezone: schedule.timezone,
            start_date: schedule.start_date ?? '',
            end_date: schedule.end_date ?? '',
            start_time: toTimeInput(schedule.start_time),
            end_time: toTimeInput(schedule.end_time),
            days_of_week: schedule.days_of_week,
            priority: schedule.priority,
        };
    }

    return {
        name: '',
        description: '',
        playlist_id: '',
        screen_ids: [],
        timezone: workspaceTimezone,
        start_date: '',
        end_date: '',
        start_time: toTimeInput(config.default_start_time),
        end_time: toTimeInput(config.default_end_time),
        days_of_week: config.default_days_of_week,
        priority: config.default_priority,
    };
}

function playlistPreviewSchema(playlist: SchedulePlaylistOption | undefined) {
    const schema = playlist?.preview_item?.schema;
    if (!schema || !isLayoutSchema(schema)) {
        return null;
    }

    return normalizeLayoutSchema(schema);
}

/**
 * Create and edit share this form. The server owns every business rule
 * (published playlists, pinned versions, window sanity, activation
 * readiness) — the hints here only help before a save round trip.
 */
export function ScheduleForm({
    schedule,
    canEdit,
    playlists,
    screens,
    locations = [],
    timezones,
    workspace_timezone: workspaceTimezone,
    day_labels: dayLabels,
    day_short_labels: dayShortLabels,
    config,
}: ScheduleFormComponentProps) {
    const isEdit = schedule !== undefined;
    const { data, setData, post, patch, processing, errors, isDirty } =
        useForm<ScheduleFormValues>(
            initialValues(schedule, config, workspaceTimezone),
        );

    const [stepIndex, setStepIndex] = useState(() =>
        isEdit ? STEPS.length - 1 : 0,
    );
    const [stepError, setStepError] = useState<string | null>(null);
    const [locationFilter, setLocationFilter] = useState<string>('all');
    const [conflicts, setConflicts] = useState<ScheduleConflict[]>([]);
    const [busy, setBusy] = useState(false);

    /**
     * Overlaps refresh from the draft payload (debounced) so create/edit both
     * see live warnings without requiring a save first.
     */
    const loadConflicts = useCallback(async () => {
        try {
            const response = await fetch(
                scheduleRoutes.preview_conflicts.url(),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...csrfHeaders(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        except_id: schedule?.id ?? null,
                        name: data.name,
                        timezone: data.timezone,
                        start_date: data.start_date || null,
                        end_date: data.end_date || null,
                        start_time: data.start_time,
                        end_time: data.end_time,
                        days_of_week: data.days_of_week,
                        priority: data.priority,
                        screen_ids: data.screen_ids,
                    }),
                },
            );

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as ScheduleConflictsPayload;
            setConflicts(payload.conflicts ?? []);
        } catch {
            // A failed overlap check must never block editing.
        }
    }, [
        schedule?.id,
        data.name,
        data.timezone,
        data.start_date,
        data.end_date,
        data.start_time,
        data.end_time,
        data.days_of_week,
        data.priority,
        data.screen_ids,
    ]);

    useEffect(() => {
        if (data.screen_ids.length === 0 || data.days_of_week.length === 0) {
            setConflicts([]);
            return;
        }

        const timer = window.setTimeout(() => {
            void loadConflicts();
        }, 350);

        return () => window.clearTimeout(timer);
    }, [loadConflicts, data.screen_ids.length, data.days_of_week.length]);

    const currentStep = STEPS[stepIndex]?.id ?? 'basics';
    const playlist = playlists.find(
        (option) => String(option.id) === data.playlist_id,
    );
    const previewSchema = playlistPreviewSchema(playlist);
    const isOvernight = crossesMidnight(data.start_time, data.end_time);
    const selectedScreens = screens.filter((screen) =>
        data.screen_ids.includes(screen.id),
    );
    const canSubmit = canEdit && data.name.trim() !== '' && !processing;

    const filteredScreens = useMemo(() => {
        if (locationFilter === 'all') {
            return screens;
        }

        if (locationFilter === 'none') {
            return screens.filter((screen) => screen.location_id == null);
        }

        const locationId = Number(locationFilter);
        return screens.filter((screen) => screen.location_id === locationId);
    }, [screens, locationFilter]);

    const hasLocations = locations.length > 0;

    function toggleScreen(id: number) {
        setData(
            'screen_ids',
            data.screen_ids.includes(id)
                ? data.screen_ids.filter((screenId) => screenId !== id)
                : [...data.screen_ids, id],
        );
    }

    function selectAllVisible() {
        const visibleIds = filteredScreens.map((screen) => screen.id);
        setData('screen_ids', [
            ...new Set([...data.screen_ids, ...visibleIds]),
        ]);
    }

    function clearVisible() {
        const visible = new Set(filteredScreens.map((screen) => screen.id));
        setData(
            'screen_ids',
            data.screen_ids.filter((id) => !visible.has(id)),
        );
    }

    function validateStep(index: number): string | null {
        const step = STEPS[index]?.id;
        if (!step) {
            return null;
        }

        switch (step) {
            case 'basics':
                if (data.name.trim() === '') {
                    return 'Enter a name to continue.';
                }
                return null;
            case 'content':
                if (data.playlist_id === '') {
                    return 'Select a published playlist to continue.';
                }
                return null;
            case 'timing':
                if (!data.start_time || !data.end_time) {
                    return 'Set a start and end time to continue.';
                }
                if (data.days_of_week.length === 0) {
                    return 'Select at least one day to continue.';
                }
                return null;
            case 'tvs':
                if (data.screen_ids.length === 0) {
                    return `Select at least one ${ProductLabels.displaySingular} to continue.`;
                }
                return null;
            default:
                return null;
        }
    }

    function goNext() {
        const message = validateStep(stepIndex);
        if (message) {
            setStepError(message);
            return;
        }
        setStepError(null);
        setStepIndex((index) => Math.min(index + 1, STEPS.length - 1));
    }

    function goBack() {
        setStepError(null);
        setStepIndex((index) => Math.max(index - 1, 0));
    }

    function goToStep(index: number) {
        if (index === stepIndex) {
            return;
        }

        if (index < stepIndex) {
            setStepError(null);
            setStepIndex(index);
            return;
        }

        for (let i = stepIndex; i < index; i++) {
            const message = validateStep(i);
            if (message) {
                setStepError(message);
                setStepIndex(i);
                return;
            }
        }

        setStepError(null);
        setStepIndex(index);
    }

    function handleSave() {
        if (!canSubmit) {
            return;
        }

        if (isEdit && schedule) {
            patch(scheduleRoutes.update.url(schedule.id), {
                preserveScroll: true,
            });
            return;
        }

        post(scheduleRoutes.store.url());
    }

    /**
     * Activate and Pause act on the stored schedule, so pending edits are
     * saved first — otherwise activation would validate stale timing.
     */
    function handleTransition(url: string) {
        if (!schedule) {
            return;
        }
        setBusy(true);
        patch(scheduleRoutes.update.url(schedule.id), {
            preserveScroll: true,
            onSuccess: () =>
                router.post(
                    url,
                    {},
                    {
                        preserveScroll: true,
                        onFinish: () => setBusy(false),
                    },
                ),
            onError: () => setBusy(false),
        });
    }

    const pending = processing || busy;
    const isLastStep = stepIndex === STEPS.length - 1;

    return (
        <div className="space-y-5" data-test="schedule-form">
            <StepIndicator
                stepIndex={stepIndex}
                onSelect={canEdit ? goToStep : undefined}
            />

            {currentStep === 'review' || conflicts.length > 0 ? (
                <div className="space-y-1">
                    <ScheduleConflictBanner
                        conflicts={conflicts}
                        shortLabels={dayShortLabels}
                        priority={data.priority}
                    />
                    {conflicts.length > 0 && isDirty ? (
                        <p className="text-muted-foreground text-xs">
                            Overlaps reflect the last save. Save again to
                            re-check.
                        </p>
                    ) : null}
                </div>
            ) : null}

            {stepError ? (
                <p
                    className="text-destructive text-sm"
                    role="alert"
                    data-test="schedule-step-error"
                >
                    {stepError}
                </p>
            ) : null}

            <section
                className="border-border bg-card space-y-4 rounded-xl border p-4 md:p-5"
                data-test={`schedule-section-${currentStep}`}
            >
                {currentStep === 'basics' ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-1.5 md:col-span-2">
                            <Label htmlFor="schedule-name">
                                {ProductLabels.scheduleName}
                            </Label>
                            <Input
                                id="schedule-name"
                                data-test="schedule-name"
                                value={data.name}
                                disabled={!canEdit}
                                aria-invalid={errors.name ? true : undefined}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Lunch Menu"
                            />
                            <FieldError message={errors.name} />
                        </div>
                        <div className="space-y-1.5 md:col-span-2">
                            <Label htmlFor="schedule-description">
                                Description (optional)
                            </Label>
                            <Input
                                id="schedule-description"
                                data-test="schedule-description"
                                value={data.description}
                                disabled={!canEdit}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            <FieldError message={errors.description} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="schedule-timezone">Timezone</Label>
                            <select
                                id="schedule-timezone"
                                className={selectClassName}
                                data-test="schedule-timezone"
                                value={data.timezone}
                                disabled={!canEdit}
                                aria-invalid={
                                    errors.timezone ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('timezone', e.target.value)
                                }
                            >
                                {timezones.map((timezone) => (
                                    <option key={timezone} value={timezone}>
                                        {timezone}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.timezone} />
                            <p className="text-muted-foreground text-xs">
                                Times use the business timezone (
                                {workspaceTimezone}).
                            </p>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="schedule-priority">Priority</Label>
                            <select
                                id="schedule-priority"
                                className={selectClassName}
                                data-test="schedule-priority"
                                value={data.priority}
                                disabled={!canEdit}
                                aria-invalid={
                                    errors.priority ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('priority', Number(e.target.value))
                                }
                            >
                                {Array.from(
                                    {
                                        length:
                                            config.max_priority -
                                            config.min_priority +
                                            1,
                                    },
                                    (_, index) => config.min_priority + index,
                                ).map((value) => (
                                    <option key={value} value={value}>
                                        {value}
                                        {value === config.default_priority
                                            ? ' (default)'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.priority} />
                            <p className="text-muted-foreground text-xs">
                                Default {config.default_priority} · Higher
                                number wins (10 = highest)
                            </p>
                        </div>
                    </div>
                ) : null}

                {currentStep === 'content' ? (
                    playlists.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No published playlists yet. Publish a playlist
                            before scheduling it.
                        </p>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Playlist</Label>
                                <div
                                    role="listbox"
                                    aria-label="Published playlists"
                                    className="max-h-80 space-y-2 overflow-y-auto pr-1"
                                    data-test="schedule-playlist-list"
                                >
                                    {playlists.map((option) => {
                                        const selected =
                                            data.playlist_id ===
                                            String(option.id);

                                        return (
                                            <button
                                                key={option.id}
                                                type="button"
                                                role="option"
                                                aria-selected={selected}
                                                disabled={!canEdit}
                                                data-test={`schedule-playlist-option-${option.id}`}
                                                onClick={() =>
                                                    setData(
                                                        'playlist_id',
                                                        String(option.id),
                                                    )
                                                }
                                                className={cn(
                                                    'hover:bg-muted/50 flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors',
                                                    selected
                                                        ? 'border-primary bg-primary/5 ring-primary/30 ring-1'
                                                        : 'border-border',
                                                    !canEdit &&
                                                        'cursor-not-allowed opacity-60',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                                                        selected
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-muted-foreground/40',
                                                    )}
                                                    aria-hidden
                                                >
                                                    {selected ? (
                                                        <Check className="size-2.5" />
                                                    ) : null}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {option.name}
                                                        {option.published_version_number !=
                                                        null
                                                            ? ` (v${option.published_version_number})`
                                                            : ''}
                                                    </span>
                                                    <span className="text-muted-foreground mt-0.5 block text-xs">
                                                        {option.orientation_label ??
                                                            'No orientation'}{' '}
                                                        · {option.item_count}{' '}
                                                        design
                                                        {option.item_count === 1
                                                            ? ''
                                                            : 's'}
                                                        {option.total_duration_seconds !=
                                                        null
                                                            ? ` · ${formatDuration(option.total_duration_seconds)} published`
                                                            : ''}
                                                        {option.draft_total_duration_seconds !=
                                                            null &&
                                                        option.draft_total_duration_seconds !==
                                                            option.total_duration_seconds
                                                            ? ` · ${formatDuration(option.draft_total_duration_seconds)} draft`
                                                            : ''}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {/* Hidden select keeps existing e2e helpers working. */}
                                <select
                                    id="schedule-playlist"
                                    className="sr-only"
                                    data-test="schedule-playlist"
                                    value={data.playlist_id}
                                    disabled={!canEdit}
                                    aria-hidden
                                    tabIndex={-1}
                                    onChange={(e) =>
                                        setData('playlist_id', e.target.value)
                                    }
                                >
                                    <option value="">Select a playlist…</option>
                                    {playlists.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.playlist_id} />
                            </div>
                            <div className="space-y-2">
                                <Label>Preview</Label>
                                {playlist && previewSchema ? (
                                    <div
                                        className={cn(
                                            'bg-muted/40 relative overflow-hidden rounded-lg border',
                                            playlist.orientation === 'portrait'
                                                ? 'aspect-[9/16] max-h-72'
                                                : 'aspect-video',
                                        )}
                                        data-test="schedule-playlist-preview"
                                    >
                                        <div className="pointer-events-none absolute inset-0 flex items-center justify-center p-3">
                                            <LayoutRenderer
                                                schema={previewSchema}
                                                mode="preview"
                                                fitWidth={
                                                    playlist.orientation ===
                                                    'portrait'
                                                        ? 140
                                                        : 280
                                                }
                                                fitHeight={
                                                    playlist.orientation ===
                                                    'portrait'
                                                        ? 260
                                                        : 160
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : playlist ? (
                                    <div
                                        className="bg-muted/30 text-muted-foreground flex aspect-video items-center justify-center rounded-lg border text-sm"
                                        data-test="schedule-playlist-hint"
                                    >
                                        {playlist.orientation_label ??
                                            'No orientation'}{' '}
                                        · {playlist.item_count} design
                                        {playlist.item_count === 1 ? '' : 's'}
                                        {playlist.total_duration_seconds != null
                                            ? ` · ${formatDuration(playlist.total_duration_seconds)} published`
                                            : ''}
                                        {playlist.draft_total_duration_seconds !=
                                            null &&
                                        playlist.draft_total_duration_seconds !==
                                            playlist.total_duration_seconds
                                            ? ` · ${formatDuration(playlist.draft_total_duration_seconds)} draft`
                                            : ''}
                                    </div>
                                ) : (
                                    <div className="bg-muted/30 text-muted-foreground flex aspect-video items-center justify-center rounded-lg border text-sm">
                                        Select a playlist to preview
                                    </div>
                                )}
                            </div>
                        </div>
                    )
                ) : null}

                {currentStep === 'timing' ? (
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="schedule-start-time">
                                    Start time
                                </Label>
                                <Input
                                    id="schedule-start-time"
                                    type="time"
                                    data-test="schedule-start-time"
                                    value={data.start_time}
                                    disabled={!canEdit}
                                    aria-invalid={
                                        errors.start_time ? true : undefined
                                    }
                                    onChange={(e) =>
                                        setData('start_time', e.target.value)
                                    }
                                />
                                <FieldError message={errors.start_time} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="schedule-end-time">
                                    End time
                                </Label>
                                <Input
                                    id="schedule-end-time"
                                    type="time"
                                    data-test="schedule-end-time"
                                    value={data.end_time}
                                    disabled={!canEdit}
                                    aria-invalid={
                                        errors.end_time ? true : undefined
                                    }
                                    onChange={(e) =>
                                        setData('end_time', e.target.value)
                                    }
                                />
                                <FieldError message={errors.end_time} />
                            </div>
                        </div>

                        {isOvernight ? (
                            <p
                                className="text-muted-foreground flex items-center gap-1.5 text-sm"
                                data-test="schedule-overnight-hint"
                            >
                                <MoonStar className="size-4" />
                                This window runs past midnight and ends the next
                                day.
                            </p>
                        ) : null}

                        <div className="space-y-1.5">
                            <Label>Days</Label>
                            <ScheduleDayPicker
                                value={data.days_of_week}
                                onChange={(days) =>
                                    setData('days_of_week', days)
                                }
                                labels={dayLabels}
                                shortLabels={dayShortLabels}
                                config={config}
                                disabled={!canEdit}
                            />
                            <FieldError message={errors.days_of_week} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="schedule-start-date">
                                    Start date (optional)
                                </Label>
                                <Input
                                    id="schedule-start-date"
                                    type="date"
                                    data-test="schedule-start-date"
                                    value={data.start_date}
                                    disabled={!canEdit}
                                    aria-invalid={
                                        errors.start_date ? true : undefined
                                    }
                                    onChange={(e) =>
                                        setData('start_date', e.target.value)
                                    }
                                />
                                <FieldError message={errors.start_date} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="schedule-end-date">
                                    End date (optional)
                                </Label>
                                <Input
                                    id="schedule-end-date"
                                    type="date"
                                    data-test="schedule-end-date"
                                    value={data.end_date}
                                    disabled={!canEdit}
                                    aria-invalid={
                                        errors.end_date ? true : undefined
                                    }
                                    onChange={(e) =>
                                        setData('end_date', e.target.value)
                                    }
                                />
                                <FieldError message={errors.end_date} />
                                <p className="text-muted-foreground text-xs">
                                    Leave empty to run indefinitely.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : null}

                {currentStep === 'tvs' ? (
                    screens.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No {ProductLabels.displayPlural.toLowerCase()} in
                            this business yet. Pair a{' '}
                            {ProductLabels.displaySingular} first.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-muted-foreground text-sm">
                                    {data.screen_ids.length} of {screens.length}{' '}
                                    selected
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {hasLocations ? (
                                        <select
                                            className={cn(
                                                selectClassName,
                                                'h-8 w-auto min-w-36',
                                            )}
                                            data-test="schedule-location-filter"
                                            value={locationFilter}
                                            disabled={!canEdit}
                                            onChange={(e) =>
                                                setLocationFilter(
                                                    e.target.value,
                                                )
                                            }
                                            aria-label="Filter by location"
                                        >
                                            <option value="all">
                                                All locations
                                            </option>
                                            <option value="none">
                                                No location
                                            </option>
                                            {locations.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={location.id}
                                                >
                                                    {location.name}
                                                </option>
                                            ))}
                                        </select>
                                    ) : null}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!canEdit}
                                        data-test="schedule-select-all-tvs"
                                        onClick={selectAllVisible}
                                    >
                                        Select all
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        disabled={!canEdit}
                                        data-test="schedule-clear-tvs"
                                        onClick={clearVisible}
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>
                            <div
                                role="group"
                                aria-label={ProductLabels.displayPlural}
                                className="grid max-h-80 gap-2 overflow-y-auto md:grid-cols-2"
                                data-test="schedule-screens"
                            >
                                {filteredScreens.map((screen) => {
                                    const online =
                                        screen.network_state === 'online';

                                    return (
                                        <label
                                            key={screen.id}
                                            className={cn(
                                                'hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-3',
                                                canEdit
                                                    ? 'cursor-pointer'
                                                    : 'cursor-not-allowed opacity-60',
                                            )}
                                        >
                                            <Checkbox
                                                checked={data.screen_ids.includes(
                                                    screen.id,
                                                )}
                                                disabled={!canEdit}
                                                data-test={`schedule-screen-${screen.id}`}
                                                aria-label={`Select ${screen.name}`}
                                                onCheckedChange={() =>
                                                    toggleScreen(screen.id)
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center gap-2">
                                                    <span className="block truncate text-sm font-medium">
                                                        {screen.name}
                                                    </span>
                                                    {screen.network_state ? (
                                                        <Badge
                                                            variant={
                                                                online
                                                                    ? 'info'
                                                                    : 'neutral'
                                                            }
                                                            className="shrink-0"
                                                        >
                                                            {online
                                                                ? 'Online'
                                                                : 'Offline'}
                                                        </Badge>
                                                    ) : null}
                                                </span>
                                                <span className="text-muted-foreground mt-0.5 block text-xs capitalize">
                                                    {screen.location_name ??
                                                        'No location'}{' '}
                                                    ·{' '}
                                                    {screen.orientation ??
                                                        'No orientation'}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {filteredScreens.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No{' '}
                                    {ProductLabels.displayPlural.toLowerCase()}{' '}
                                    match this location filter.
                                </p>
                            ) : null}
                            <FieldError message={errors.screen_ids} />
                        </div>
                    )
                ) : null}

                {currentStep === 'review' ? (
                    <dl
                        className="divide-border divide-y text-sm"
                        data-test="schedule-summary"
                    >
                        <ReviewRow
                            label={ProductLabels.scheduleName}
                            value={
                                <span data-test="schedule-summary-name">
                                    {data.name.trim() || 'Untitled'}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Playlist"
                            value={
                                <span data-test="schedule-summary-playlist">
                                    {playlist?.name ?? 'No playlist selected'}
                                    {playlist?.total_duration_seconds != null
                                        ? ` · ${formatDuration(playlist.total_duration_seconds)} published`
                                        : ''}
                                    {playlist?.draft_total_duration_seconds !=
                                        null &&
                                    playlist.draft_total_duration_seconds !==
                                        playlist.total_duration_seconds
                                        ? ` · ${formatDuration(playlist.draft_total_duration_seconds)} draft`
                                        : ''}
                                </span>
                            }
                        />
                        <ReviewRow
                            label={ProductLabels.displayPlural}
                            value={
                                <span data-test="schedule-summary-screens">
                                    {selectedScreens.length === 0
                                        ? `No ${ProductLabels.displayPlural.toLowerCase()} selected`
                                        : selectedScreens
                                              .map((screen) => screen.name)
                                              .join(', ')}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Time"
                            value={
                                <span
                                    className="font-mono"
                                    data-test="schedule-summary-time"
                                >
                                    {formatTimeRange(
                                        data.start_time,
                                        data.end_time,
                                    )}
                                    {isOvernight ? ' (next day)' : ''}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Days"
                            value={
                                <span data-test="schedule-summary-days">
                                    {describeDays(
                                        data.days_of_week,
                                        dayShortLabels,
                                    )}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Dates"
                            value={
                                <span data-test="schedule-summary-dates">
                                    {data.start_date || 'Today'} →{' '}
                                    {data.end_date || 'No end date'}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Timezone"
                            value={
                                <span data-test="schedule-summary-timezone">
                                    {data.timezone}
                                </span>
                            }
                        />
                        <ReviewRow
                            label="Priority"
                            value={
                                <span data-test="schedule-summary-priority">
                                    {data.priority}
                                </span>
                            }
                        />
                        {schedule ? (
                            <ReviewRow
                                label="Next run"
                                value={
                                    <span
                                        className="font-mono"
                                        data-test="schedule-summary-next"
                                    >
                                        {formatWindow(schedule.next_window)}
                                    </span>
                                }
                            />
                        ) : null}
                    </dl>
                ) : null}
            </section>

            <div className="border-border bg-card sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                <div className="flex items-center gap-2">
                    {schedule ? (
                        <Badge
                            variant="secondary"
                            data-test="schedule-form-status"
                        >
                            {schedule.status_label}
                        </Badge>
                    ) : (
                        <Badge variant="info">Draft</Badge>
                    )}
                    {isDirty ? (
                        <span
                            className="text-muted-foreground text-xs"
                            data-test="schedule-form-dirty"
                        >
                            Unsaved changes
                        </span>
                    ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                    {stepIndex > 0 ? (
                        <Button
                            type="button"
                            variant="outline"
                            data-test="schedule-step-back"
                            onClick={goBack}
                        >
                            <ChevronLeft className="size-4" />
                            Back
                        </Button>
                    ) : null}
                    {!isLastStep ? (
                        <Button
                            type="button"
                            data-test="schedule-step-next"
                            onClick={goNext}
                        >
                            Next
                            <ChevronRight className="size-4" />
                        </Button>
                    ) : null}
                    {isLastStep &&
                    schedule &&
                    schedule.stored_status === 'active' ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!canEdit || pending}
                            data-test="schedule-pause"
                            onClick={() =>
                                handleTransition(
                                    scheduleRoutes.pause.url(schedule.id),
                                )
                            }
                        >
                            Pause
                        </Button>
                    ) : null}
                    {isLastStep &&
                    schedule &&
                    schedule.stored_status !== 'active' &&
                    schedule.stored_status !== 'archived' ? (
                        <Button
                            type="button"
                            disabled={!canEdit || pending}
                            data-test="schedule-activate"
                            onClick={() =>
                                handleTransition(
                                    scheduleRoutes.activate.url(schedule.id),
                                )
                            }
                        >
                            {pending ? <Spinner /> : null}
                            Activate
                        </Button>
                    ) : null}
                    {isLastStep ? (
                        <Button
                            type="button"
                            disabled={!canSubmit || pending}
                            data-test="schedule-save"
                            onClick={handleSave}
                        >
                            {processing ? <Spinner /> : null}
                            {isEdit ? 'Save schedule' : 'Save draft'}
                        </Button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function StepIndicator({
    stepIndex,
    onSelect,
}: {
    stepIndex: number;
    onSelect?: (index: number) => void;
}) {
    return (
        <nav
            aria-label="Schedule steps"
            data-test="schedule-stepper"
            className="space-y-2"
        >
            {/* Mobile: compact progress */}
            <div className="flex items-center justify-between gap-3 md:hidden">
                <p
                    className="text-sm font-medium"
                    data-test="schedule-step-mobile-label"
                >
                    Step {stepIndex + 1} of {STEPS.length}:{' '}
                    {STEPS[stepIndex]?.label}
                </p>
                <div className="flex gap-1">
                    {STEPS.map((step, index) => (
                        <span
                            key={step.id}
                            className={cn(
                                'h-1.5 w-4 rounded-full',
                                index <= stepIndex ? 'bg-primary' : 'bg-muted',
                            )}
                            aria-hidden
                        />
                    ))}
                </div>
            </div>

            {/* Desktop: horizontal stepper */}
            <ol className="hidden md:grid md:grid-cols-5 md:gap-2">
                {STEPS.map((step, index) => {
                    const completed = index < stepIndex;
                    const active = index === stepIndex;
                    const clickable = onSelect !== undefined;

                    return (
                        <li key={step.id}>
                            <button
                                type="button"
                                disabled={!clickable}
                                data-test={`schedule-step-${step.id}`}
                                data-active={active ? 'true' : 'false'}
                                data-completed={completed ? 'true' : 'false'}
                                onClick={() => onSelect?.(index)}
                                className={cn(
                                    'flex w-full items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-sm transition-colors',
                                    active
                                        ? 'border-primary bg-primary/5'
                                        : completed
                                          ? 'border-border bg-card'
                                          : 'border-border/60 bg-card/50',
                                    clickable
                                        ? 'hover:bg-muted/50 cursor-pointer'
                                        : 'cursor-default',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        completed
                                            ? 'bg-primary text-primary-foreground'
                                            : active
                                              ? 'bg-secondary text-secondary-foreground'
                                              : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {completed ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'truncate font-medium',
                                        !active &&
                                            !completed &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {step.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function ReviewRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 py-2">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="min-w-0 text-right">{value}</dd>
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-destructive text-sm" role="alert">
            {message}
        </p>
    ) : null;
}
