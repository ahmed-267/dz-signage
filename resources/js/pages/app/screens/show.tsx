import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Power, PowerOff, Unplug, Upload } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import {
    contentSyncBadgeVariant,
    healthBadgeVariant,
} from '@/components/screens/screen-state-badges';
import { TvContentPreviewDialog } from '@/components/screens/tv-content-preview-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { formatDateTime, formatLastSeen } from '@/lib/format-relative-time';
import { ProductLabels } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { screens as screensIndex } from '@/routes/app';
import scheduleRoutes from '@/routes/app/schedules';
import screenRoutes from '@/routes/app/screens';
import type {
    PublishedDesignOption,
    ScreenScheduleSummary,
    ScreenShowProps,
} from '@/types/screen';

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function labelize(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ScreenShow({
    screen,
    recent_heartbeats: recentHeartbeats,
    published_designs: publishedDesigns,
    locations = [],
    can_manage: canManage,
    can_publish: canPublish,
    workspace_name: workspaceName,
}: ScreenShowProps) {
    const [renameOpen, setRenameOpen] = useState(false);
    const [renameValue, setRenameValue] = useState(screen.name);
    const [locationId, setLocationId] = useState(
        screen.location_id ? String(screen.location_id) : '',
    );
    const [publishOpen, setPublishOpen] = useState(false);
    const [publishDesignId, setPublishDesignId] = useState('');
    const [unpairOpen, setUnpairOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);

    const isActive = screen.operational_status === 'active';
    const device = screen.device;

    function handleRename() {
        if (!renameValue.trim()) {
            return;
        }
        setBusy(true);
        router.post(
            screenRoutes.rename.url(screen.id),
            { name: renameValue.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setRenameOpen(false);
                },
            },
        );
    }

    function handleLocationSave() {
        setBusy(true);
        router.post(
            screenRoutes.location.url(screen.id),
            {
                location_id: locationId ? Number(locationId) : null,
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    function handlePublish() {
        if (!publishDesignId) {
            return;
        }
        setBusy(true);
        router.post(
            screenRoutes.publish.url(screen.id),
            { screen_design_id: Number(publishDesignId) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setPublishOpen(false);
                    setPublishDesignId('');
                },
            },
        );
    }

    function handleStatus(next: 'active' | 'inactive') {
        setBusy(true);
        router.post(
            screenRoutes.status.url(screen.id),
            { operational_status: next },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    function handleUnpair() {
        setBusy(true);
        router.delete(screenRoutes.destroy.url(screen.id), {
            onFinish: () => {
                setBusy(false);
                setUnpairOpen(false);
            },
        });
    }

    return (
        <>
            <Head title={screen.name} />
            <div
                className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="screen-detail"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {screen.name}
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">
                            {workspaceName ?? 'Workspace'} screen
                            {screen.orientation
                                ? ` · ${labelize(screen.orientation)}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            data-test="screen-preview-now-showing"
                            onClick={() => setPreviewOpen(true)}
                        >
                            Preview
                        </Button>
                        {canPublish ? (
                            <Button
                                type="button"
                                data-test="screen-publish"
                                onClick={() => setPublishOpen(true)}
                            >
                                <Upload className="size-4" />
                                Change Content
                            </Button>
                        ) : null}
                        {canManage ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    data-test="screen-rename"
                                    onClick={() => {
                                        setRenameValue(screen.name);
                                        setRenameOpen(true);
                                    }}
                                >
                                    Rename
                                </Button>
                                {isActive ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        data-test="screen-deactivate"
                                        disabled={busy}
                                        onClick={() => handleStatus('inactive')}
                                    >
                                        {busy ? (
                                            <Spinner />
                                        ) : (
                                            <PowerOff className="size-4" />
                                        )}
                                        Deactivate
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        data-test="screen-activate"
                                        disabled={busy}
                                        onClick={() => handleStatus('active')}
                                    >
                                        {busy ? (
                                            <Spinner />
                                        ) : (
                                            <Power className="size-4" />
                                        )}
                                        Activate
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="destructive"
                                    data-test="screen-unpair"
                                    onClick={() => setUnpairOpen(true)}
                                >
                                    <Unplug className="size-4" />
                                    Unpair
                                </Button>
                            </>
                        ) : null}
                    </div>
                </div>

                {screen.orientation_mismatch ? (
                    <Alert
                        data-test="screen-orientation-mismatch"
                        className="border-warning/40 bg-warning/10"
                    >
                        <AlertTriangle className="text-warning" />
                        <AlertTitle>Orientation mismatch</AlertTitle>
                        <AlertDescription>
                            The device reports{' '}
                            {screen.reported_orientation ?? 'an unknown'}{' '}
                            orientation while the published design is{' '}
                            {screen.current_design_orientation ??
                                screen.orientation ??
                                'unset'}
                            . Rotate the display or publish a matching design.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Section title="Overview" testId="screen-overview">
                    <dl className="divide-border divide-y">
                        <DetailRow
                            label="Workspace"
                            value={
                                screen.workspace_name ??
                                workspaceName ??
                                'Workspace'
                            }
                        />
                        <DetailRow
                            label="Location"
                            value={
                                screen.location?.name ??
                                screen.location_name ??
                                'Unassigned'
                            }
                            testId="screen-location-name"
                        />
                        <DetailRow
                            label="Orientation"
                            value={
                                screen.orientation
                                    ? labelize(screen.orientation)
                                    : 'Not set'
                            }
                        />
                        <DetailRow
                            label="Added"
                            value={formatDateTime(screen.created_at)}
                        />
                    </dl>
                    {canManage && locations.length > 0 ? (
                        <div className="border-border flex flex-col gap-2 border-t px-4 py-3 sm:flex-row sm:items-end">
                            <div className="min-w-0 flex-1 space-y-1.5">
                                <Label htmlFor="screen-location">
                                    Assign location
                                </Label>
                                <select
                                    id="screen-location"
                                    className={selectClassName}
                                    value={locationId}
                                    onChange={(e) =>
                                        setLocationId(e.target.value)
                                    }
                                    data-test="screen-location-select"
                                >
                                    <option value="">Unassigned</option>
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.name}
                                            {location.city
                                                ? ` · ${location.city}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                data-test="screen-location-save"
                                onClick={handleLocationSave}
                            >
                                {busy ? <Spinner /> : null}
                                Save location
                            </Button>
                        </div>
                    ) : null}
                </Section>

                <Section title="State" testId="screen-state">
                    <div className="flex flex-wrap gap-2 px-4 pt-4">
                        <Badge
                            variant={isActive ? 'success' : 'neutral'}
                            data-test="screen-state-operational"
                        >
                            {isActive ? 'Active' : 'Inactive'}
                        </Badge>
                        <Badge
                            variant={
                                screen.pairing_state === 'connected'
                                    ? 'success'
                                    : 'warning'
                            }
                            data-test="screen-state-pairing"
                        >
                            {screen.pairing_state === 'connected'
                                ? 'Connected'
                                : 'Disconnected'}
                        </Badge>
                        <Badge
                            variant={
                                screen.network_state === 'online'
                                    ? 'info'
                                    : 'neutral'
                            }
                            data-test="screen-state-network"
                        >
                            {screen.network_state === 'online'
                                ? 'Online'
                                : 'Offline'}
                        </Badge>
                        <Badge
                            variant={healthBadgeVariant(screen.health)}
                            data-test="screen-state-health"
                        >
                            {screen.health_label}
                        </Badge>
                    </div>
                    <dl className="divide-border divide-y">
                        <DetailRow
                            label="Last seen"
                            value={formatLastSeen(
                                device?.last_seen_at ?? screen.last_seen_at,
                            )}
                            testId="screen-last-seen"
                        />
                        <DetailRow
                            label="Paired"
                            value={formatLastSeen(
                                device?.paired_at ?? screen.paired_at,
                            )}
                        />
                        <DetailRow
                            label="Playback"
                            value={
                                screen.playback_state
                                    ? labelize(String(screen.playback_state))
                                    : '—'
                            }
                        />
                    </dl>
                </Section>

                <Section title="Now Showing" testId="screen-content">
                    <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                        <p className="text-muted-foreground text-sm">
                            Resolver-backed view of what this TV should display.
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            data-test="screen-now-showing-preview"
                            onClick={() => setPreviewOpen(true)}
                        >
                            Preview
                        </Button>
                    </div>
                    <dl className="divide-border divide-y">
                        <div className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
                            <dt className="text-muted-foreground shrink-0">
                                Playing from
                            </dt>
                            <dd>
                                <Badge
                                    variant={
                                        screen.content_source === 'schedule'
                                            ? 'info'
                                            : screen.content_source ===
                                                'deployment'
                                              ? 'success'
                                              : 'neutral'
                                    }
                                    data-test="screen-content-source"
                                    data-source={screen.content_source}
                                >
                                    {screen.now_showing?.content_type_label ??
                                        screen.content_source_label}
                                </Badge>
                            </dd>
                        </div>
                        <DetailRow
                            label="Content"
                            value={
                                screen.now_showing?.content_name ??
                                screen.current_deployment?.design_name ??
                                screen.current_design_name ??
                                'None'
                            }
                            testId="screen-now-showing-name"
                        />
                        {screen.now_showing?.content_source === 'schedule' &&
                        screen.now_showing.schedule_name ? (
                            <DetailRow
                                label="Schedule"
                                value={`${screen.now_showing.schedule_name}${
                                    screen.now_showing.schedule_priority != null
                                        ? ` · priority ${screen.now_showing.schedule_priority}`
                                        : ''
                                }${
                                    screen.now_showing.window_ends_at_local
                                        ? ` · until ${screen.now_showing.window_ends_at_local}`
                                        : ''
                                }`}
                                testId="screen-now-showing-schedule"
                            />
                        ) : null}
                        <DetailRow
                            label="Version"
                            value={
                                screen.now_showing?.version_number != null
                                    ? `v${screen.now_showing.version_number}`
                                    : screen.current_deployment
                                            ?.version_number != null
                                      ? `v${screen.current_deployment.version_number}`
                                      : '—'
                            }
                        />
                        <DetailRow
                            label="Status"
                            value={
                                screen.now_showing?.ack_label ??
                                screen.content_sync_label
                            }
                            testId="screen-now-showing-status"
                        />
                        <DetailRow
                            label="Deployed"
                            value={formatDateTime(
                                screen.current_deployment?.deployed_at ??
                                    screen.deployed_at,
                            )}
                        />
                        <div className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
                            <dt className="text-muted-foreground shrink-0">
                                Sync state
                            </dt>
                            <dd>
                                <Badge
                                    variant={contentSyncBadgeVariant(
                                        screen.content_sync,
                                    )}
                                    data-test="screen-content-sync"
                                >
                                    {screen.content_sync_label}
                                </Badge>
                            </dd>
                        </div>
                    </dl>
                </Section>

                <Section title="Schedule" testId="screen-schedule">
                    {screen.current_schedule === null &&
                    screen.next_schedule === null ? (
                        <p
                            className="text-muted-foreground px-4 py-4 text-sm"
                            data-test="screen-schedule-empty"
                        >
                            No active schedule targets this screen. It keeps
                            playing whatever was published to it.
                        </p>
                    ) : (
                        <dl className="divide-border divide-y">
                            <ScheduleRow
                                label="Current schedule"
                                schedule={screen.current_schedule}
                                emptyValue="Not inside a schedule window"
                                testId="screen-current-schedule"
                            />
                            <ScheduleRow
                                label="Next schedule"
                                schedule={screen.next_schedule}
                                emptyValue="Nothing scheduled in the next two weeks"
                                testId="screen-next-schedule"
                            />
                        </dl>
                    )}
                </Section>

                <Section title="Device" testId="screen-device">
                    {device === null ? (
                        <p className="text-muted-foreground px-4 py-4 text-sm">
                            No device is paired with this screen.
                        </p>
                    ) : (
                        <dl className="divide-border divide-y">
                            <DetailRow
                                label="Device identifier"
                                value={device.device_identifier ?? '—'}
                            />
                            <DetailRow
                                label="Player version"
                                value={device.player_version ?? '—'}
                            />
                            <DetailRow
                                label="Viewport"
                                value={
                                    device.viewport_width &&
                                    device.viewport_height
                                        ? `${device.viewport_width} × ${device.viewport_height}`
                                        : '—'
                                }
                            />
                            <DetailRow
                                label="Reported orientation"
                                value={
                                    device.reported_orientation
                                        ? labelize(device.reported_orientation)
                                        : '—'
                                }
                            />
                            <DetailRow
                                label="Last error"
                                value={
                                    device.last_error_code
                                        ? labelize(device.last_error_code)
                                        : 'None'
                                }
                            />
                            <DetailRow
                                label="Offline ready"
                                value={
                                    device.offline?.cache_ready ? 'Yes' : 'No'
                                }
                            />
                            <DetailRow
                                label="Last content sync"
                                value={
                                    device.offline?.last_sync_at
                                        ? formatDateTime(
                                              device.offline.last_sync_at,
                                          )
                                        : '—'
                                }
                            />
                            <DetailRow
                                label="Cached package"
                                value={device.offline?.package_version ?? '—'}
                            />
                        </dl>
                    )}
                </Section>

                <Section title="Recent heartbeats" testId="screen-heartbeats">
                    {recentHeartbeats.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-4 text-sm">
                            No heartbeats recorded yet. A paired player reports
                            in automatically.
                        </p>
                    ) : (
                        <ul className="divide-border divide-y">
                            {recentHeartbeats.map((heartbeat) => (
                                <li
                                    key={heartbeat.id}
                                    className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {heartbeat.playback_state
                                                ? labelize(
                                                      heartbeat.playback_state,
                                                  )
                                                : 'Unknown state'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {heartbeat.player_version
                                                ? `Player ${heartbeat.player_version}`
                                                : 'Player version unknown'}
                                            {heartbeat.orientation
                                                ? ` · ${labelize(heartbeat.orientation)}`
                                                : ''}
                                            {heartbeat.error_code
                                                ? ` · ${labelize(heartbeat.error_code)}`
                                                : ''}
                                        </p>
                                    </div>
                                    <span className="text-muted-foreground shrink-0 text-xs">
                                        {formatDateTime(heartbeat.recorded_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                {screen.deployments.length > 0 ? (
                    <Section
                        title="Recent deployments"
                        testId="screen-deployments"
                    >
                        <ul className="divide-border divide-y">
                            {screen.deployments.map((deployment) => (
                                <li
                                    key={deployment.id}
                                    className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {deployment.design_name ??
                                                'Unknown design'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {deployment.version_number != null
                                                ? `v${deployment.version_number}`
                                                : '—'}{' '}
                                            · {labelize(deployment.status)}
                                        </p>
                                    </div>
                                    <span className="text-muted-foreground shrink-0 text-xs">
                                        {formatDateTime(deployment.deployed_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                ) : null}
            </div>

            <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Rename screen</DialogTitle>
                    </DialogHeader>
                    <Input
                        data-test="screen-rename-input"
                        value={renameValue}
                        onChange={(e) => setRenameValue(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                handleRename();
                            }
                        }}
                        aria-label="TV name"
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRenameOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="screen-rename-confirm"
                            disabled={busy}
                            onClick={handleRename}
                        >
                            {busy ? <Spinner /> : null}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={publishOpen}
                onOpenChange={(open) => {
                    setPublishOpen(open);
                    if (!open) {
                        setPublishDesignId('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Publish Content</DialogTitle>
                        <DialogDescription>
                            Choose a published design for this screen.
                        </DialogDescription>
                    </DialogHeader>
                    <PublishDesignSelect
                        designs={publishedDesigns}
                        value={publishDesignId}
                        onChange={setPublishDesignId}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPublishOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="screen-publish-confirm"
                            disabled={busy || !publishDesignId}
                            onClick={handlePublish}
                        >
                            {busy ? <Spinner /> : null}
                            Publish
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={unpairOpen} onOpenChange={setUnpairOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Unpair “{screen.name}”?</DialogTitle>
                        <DialogDescription>
                            The device will need a new pairing code to
                            reconnect.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setUnpairOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="screen-unpair-confirm"
                            disabled={busy}
                            onClick={handleUnpair}
                        >
                            {busy ? <Spinner /> : null}
                            Unpair
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <TvContentPreviewDialog
                screenId={previewOpen ? screen.id : null}
                screenName={screen.name}
                onClose={() => setPreviewOpen(false)}
            />
        </>
    );
}

function Section({
    title,
    testId,
    children,
}: {
    title: string;
    testId: string;
    children: ReactNode;
}) {
    return (
        <section data-test={testId}>
            <h2 className="font-display mb-3 text-lg font-semibold">{title}</h2>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                {children}
            </div>
        </section>
    );
}

function DetailRow({
    label,
    value,
    testId,
}: {
    label: string;
    value: string;
    testId?: string;
}) {
    return (
        <div className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="text-right font-medium" data-test={testId}>
                {value}
            </dd>
        </div>
    );
}

/**
 * One schedule occurrence. `ScheduleEvaluator` already resolved precedence and
 * converted the window into the schedule's own timezone.
 */
function ScheduleRow({
    label,
    schedule,
    emptyValue,
    testId,
}: {
    label: string;
    schedule: ScreenScheduleSummary | null;
    emptyValue: string;
    testId: string;
}) {
    return (
        <div className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="min-w-0 text-right" data-test={testId}>
                {schedule === null ? (
                    <span className="text-muted-foreground">{emptyValue}</span>
                ) : (
                    <>
                        <Link
                            href={scheduleRoutes.edit.url(schedule.id)}
                            className="font-medium hover:underline"
                            data-test={`${testId}-link`}
                        >
                            {schedule.name}
                        </Link>
                        <span className="text-muted-foreground block text-xs">
                            {schedule.playlist_name ?? 'No playlist'} · priority{' '}
                            {schedule.priority}
                        </span>
                        <span className="text-muted-foreground block font-mono text-xs">
                            {schedule.starts_at_local} →{' '}
                            {schedule.ends_at_local} ({schedule.timezone})
                        </span>
                    </>
                )}
            </dd>
        </div>
    );
}

function PublishDesignSelect({
    designs,
    value,
    onChange,
}: {
    designs: PublishedDesignOption[];
    value: string;
    onChange: (value: string) => void;
}) {
    if (designs.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                No published Screens yet. Publish a Screen first.
            </p>
        );
    }

    return (
        <div className="space-y-1.5">
            <Label htmlFor="screen-publish-design">Design</Label>
            <select
                id="screen-publish-design"
                className={selectClassName}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                data-test="screen-publish-design"
            >
                <option value="">Select a design…</option>
                {designs.map((design) => (
                    <option key={design.id} value={design.id}>
                        {design.name} ({design.orientation})
                    </option>
                ))}
            </select>
        </div>
    );
}

ScreenShow.layout = (props: ScreenShowProps) => ({
    breadcrumbs: [
        { title: ProductLabels.pairedNav, href: screensIndex.url() },
        {
            title: props.screen.name,
            href: screenRoutes.show.url(props.screen.id),
        },
    ],
});
