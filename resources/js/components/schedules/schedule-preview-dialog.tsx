import { useEffect, useState, type ReactNode } from 'react';
import { toLayoutMediaMap } from '@/components/playlists/player-items';
import {
    PlaylistPreviewPlayer,
    type PlaylistPlayerItem,
} from '@/components/playlists/playlist-preview-player';
import type { LayoutMediaMap } from '@/components/rendering/layout-renderer';
import { ScheduleConflictBanner } from '@/components/schedules/schedule-conflict-banner';
import {
    describeDays,
    formatMinutes,
    formatTimeRange,
    formatWindow,
    statusBadgeVariant,
} from '@/components/schedules/schedule-format';
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
import { Spinner } from '@/components/ui/spinner';
import { formatDuration } from '@/lib/format-duration';
import { ProductLabels } from '@/lib/product-labels';
import { isLayoutSchema, normalizeLayoutSchema } from '@/types/layout-schema';
import scheduleRoutes from '@/routes/app/schedules';
import type {
    ScheduleDayLabels,
    SchedulePreviewItem,
    SchedulePreviewPayload,
} from '@/types/schedule';

type SchedulePreviewDialogProps = {
    scheduleId: number | null;
    scheduleName?: string;
    shortLabels: ScheduleDayLabels;
    onClose: () => void;
};

type LoadState =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | {
          status: 'ready';
          payload: SchedulePreviewPayload;
          items: PlaylistPlayerItem[];
          mediaMap: LayoutMediaMap;
      };

/**
 * Preview items come from the pinned published playlist version, so they have
 * no playlist item id and are always active — the endpoint already filtered
 * inactive ones out.
 */
function toPlayerItems(items: SchedulePreviewItem[]): PlaylistPlayerItem[] {
    const playerItems: PlaylistPlayerItem[] = [];

    for (const item of items) {
        if (!isLayoutSchema(item.schema)) {
            continue;
        }

        playerItems.push({
            key: `${item.screen_design_id}-${item.position}`,
            name: item.name ?? `Item ${item.position}`,
            schema: normalizeLayoutSchema(item.schema),
            duration_seconds: item.duration_seconds,
            loop_count: Math.max(1, item.loop_count ?? 1),
            transition: item.transition,
            transition_speed: item.transition_speed,
            is_active: true,
        });
    }

    return playerItems;
}

/**
 * What this schedule plays, when it plays next, and which live schedules it
 * overlaps. The preview endpoint returns JSON rather than an Inertia page, so
 * it is fetched on open.
 */
export function SchedulePreviewDialog({
    scheduleId,
    scheduleName,
    shortLabels,
    onClose,
}: SchedulePreviewDialogProps) {
    const [state, setState] = useState<LoadState>({ status: 'loading' });

    useEffect(() => {
        if (scheduleId === null) {
            return;
        }

        const controller = new AbortController();
        setState({ status: 'loading' });

        void (async () => {
            try {
                const response = await fetch(
                    scheduleRoutes.preview.url(scheduleId),
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Preview request failed.');
                }

                const payload =
                    (await response.json()) as SchedulePreviewPayload;

                setState({
                    status: 'ready',
                    payload,
                    items: toPlayerItems(payload.items ?? []),
                    mediaMap: toLayoutMediaMap(payload.media_map),
                });
            } catch (error) {
                if (controller.signal.aborted) {
                    return;
                }
                setState({
                    status: 'error',
                    message:
                        error instanceof Error
                            ? error.message
                            : 'Unable to load this schedule preview.',
                });
            }
        })();

        return () => controller.abort();
    }, [scheduleId]);

    const schedule = state.status === 'ready' ? state.payload.schedule : null;
    const title = schedule?.name ?? scheduleName ?? 'Schedule';

    return (
        <Dialog
            open={scheduleId !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="flex max-h-[90vh] flex-col overflow-y-auto sm:max-w-3xl"
                data-test="schedule-preview-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Preview · {title}</DialogTitle>
                    <DialogDescription>
                        {schedule
                            ? `${schedule.playlist_name ?? 'No playlist'} on ${schedule.screen_count} screen${schedule.screen_count === 1 ? '' : 's'}.`
                            : 'What this schedule plays and when it plays next.'}
                    </DialogDescription>
                </DialogHeader>

                {state.status === 'loading' ? (
                    <div className="flex h-48 items-center justify-center">
                        <Spinner className="size-6" />
                    </div>
                ) : null}

                {state.status === 'error' ? (
                    <p
                        className="text-destructive py-12 text-center text-sm"
                        data-test="schedule-preview-error"
                    >
                        {state.message}
                    </p>
                ) : null}

                {state.status === 'ready' && schedule ? (
                    <div className="space-y-4">
                        <dl
                            className="divide-border bg-card divide-y rounded-xl border"
                            data-test="schedule-preview-summary"
                        >
                            <SummaryRow
                                label="Status"
                                value={
                                    <Badge
                                        variant={statusBadgeVariant(
                                            schedule.status,
                                        )}
                                        data-test="schedule-preview-status"
                                    >
                                        {schedule.status_label}
                                    </Badge>
                                }
                            />
                            <SummaryRow
                                label="Playlist"
                                value={
                                    <span data-test="schedule-preview-playlist">
                                        {schedule.playlist_name ??
                                            'No playlist'}
                                        {schedule.playlist_version_number !=
                                        null
                                            ? ` · v${schedule.playlist_version_number}`
                                            : ''}
                                    </span>
                                }
                            />
                            <SummaryRow
                                label="Time"
                                value={
                                    <span
                                        className="font-mono"
                                        data-test="schedule-preview-time"
                                    >
                                        {formatTimeRange(
                                            schedule.start_time,
                                            schedule.end_time,
                                        )}
                                        {schedule.crosses_midnight
                                            ? ' (next day)'
                                            : ''}
                                        {' · '}
                                        {formatMinutes(
                                            schedule.duration_minutes,
                                        )}
                                    </span>
                                }
                            />
                            <SummaryRow
                                label="Days"
                                value={
                                    <span data-test="schedule-preview-days">
                                        {describeDays(
                                            schedule.days_of_week,
                                            shortLabels,
                                        )}
                                    </span>
                                }
                            />
                            <SummaryRow
                                label="Timezone"
                                value={schedule.timezone}
                            />
                            <SummaryRow
                                label="Priority"
                                value={
                                    <span data-test="schedule-preview-priority">
                                        {schedule.priority}
                                    </span>
                                }
                            />
                            <SummaryRow
                                label={ProductLabels.displayPlural}
                                value={
                                    <span data-test="schedule-preview-screens">
                                        {schedule.screens.length === 0
                                            ? 'No TVs selected'
                                            : schedule.screens
                                                  .map((screen) => screen.name)
                                                  .join(', ')}
                                    </span>
                                }
                            />
                            <SummaryRow
                                label="Playing now"
                                value={
                                    <span
                                        className="font-mono"
                                        data-test="schedule-preview-current"
                                    >
                                        {schedule.is_live
                                            ? formatWindow(
                                                  schedule.current_window,
                                              )
                                            : 'Not playing'}
                                    </span>
                                }
                            />
                        </dl>

                        <ScheduleConflictBanner
                            conflicts={state.payload.conflicts}
                            shortLabels={shortLabels}
                            priority={schedule.priority}
                        />

                        <div>
                            <h3 className="mb-1.5 text-sm font-medium">
                                Upcoming
                            </h3>
                            {state.payload.upcoming.length === 0 ? (
                                <p
                                    className="text-muted-foreground text-sm"
                                    data-test="schedule-preview-upcoming-empty"
                                >
                                    No upcoming windows in the next two weeks.
                                </p>
                            ) : (
                                <ul
                                    className="divide-border bg-card divide-y rounded-xl border"
                                    data-test="schedule-preview-upcoming"
                                >
                                    {state.payload.upcoming.map((window) => (
                                        <li
                                            key={window.starts_at}
                                            className="px-4 py-2.5 font-mono text-sm"
                                        >
                                            {formatWindow(window)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <h3 className="mb-1.5 text-sm font-medium">
                                Plays {state.items.length} design
                                {state.items.length === 1 ? '' : 's'} ·{' '}
                                {formatDuration(
                                    state.payload.total_duration_seconds,
                                )}{' '}
                                loop
                            </h3>
                            <PlaylistPreviewPlayer
                                items={state.items}
                                mediaMap={state.mediaMap}
                                autoPlay={false}
                                className="h-[min(45vh,360px)]"
                                emptyMessage="This schedule has no playable designs."
                            />
                        </div>
                    </div>
                ) : null}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function SummaryRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 px-4 py-2.5 text-sm">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="min-w-0 text-right">{value}</dd>
        </div>
    );
}
