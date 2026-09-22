import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    contentSyncBadgeVariant,
    healthBadgeVariant,
    ScreenStateBadges,
} from '@/components/screens/screen-state-badges';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatLastSeen } from '@/lib/format-relative-time';
import { screens as screensIndex } from '@/routes/admin';
import { show as showScreen } from '@/routes/admin/screens';
import type { AdminScreenShowProps } from '@/types/screen';

function labelize(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

/**
 * Platform support view — read-only. Screen changes stay in the customer app.
 */
export default function AdminScreenShow({ screen }: AdminScreenShowProps) {
    const device = screen.device;
    const viewport =
        device?.viewport_width != null && device?.viewport_height != null
            ? `${device.viewport_width} × ${device.viewport_height}`
            : '—';

    return (
        <>
            <Head title={screen.name} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-screen-detail"
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="mb-1">
                            <Badge variant="info">Super Admin</Badge>
                        </div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {screen.name}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {screen.workspace_name ?? 'No workspace'} ·{' '}
                            <span className="capitalize">
                                {screen.orientation ?? 'no orientation'}
                            </span>
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={screensIndex()}>Back to screens</Link>
                    </Button>
                </div>

                {screen.orientation_mismatch ? (
                    <Alert
                        data-test="admin-screen-orientation-mismatch"
                        className="border-warning/40 bg-warning/10"
                    >
                        <AlertTriangle className="text-warning" />
                        <AlertTitle>Orientation mismatch</AlertTitle>
                        <AlertDescription>
                            The device reports{' '}
                            {screen.device?.reported_orientation ?? 'unknown'}{' '}
                            while the deployed design expects{' '}
                            {screen.orientation ?? 'unknown'}.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                State
                            </CardTitle>
                            <CardDescription>
                                Operational, pairing and network are independent
                                axes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ScreenStateBadges
                                id={screen.id}
                                operationalStatus={screen.operational_status}
                                pairingState={screen.pairing_state}
                                networkState={screen.network_state}
                            />
                            <dl className="divide-border divide-y">
                                <DetailRow
                                    label="Health"
                                    value={
                                        <Badge
                                            variant={healthBadgeVariant(
                                                screen.health,
                                            )}
                                            data-test="admin-screen-health"
                                        >
                                            {screen.health_label}
                                        </Badge>
                                    }
                                />
                                <DetailRow
                                    label="Last seen"
                                    value={formatLastSeen(screen.last_seen_at)}
                                />
                                <DetailRow
                                    label="Paired"
                                    value={formatLastSeen(screen.paired_at)}
                                />
                                <DetailRow
                                    label="Created"
                                    value={formatDateTime(screen.created_at)}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Current content
                            </CardTitle>
                            <CardDescription>
                                Active deployment reported by the server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-border divide-y">
                                <DetailRow
                                    label="Design"
                                    value={screen.current_design_name ?? 'None'}
                                />
                                <DetailRow
                                    label="Version"
                                    value={
                                        screen.current_version_number != null
                                            ? `v${screen.current_version_number}`
                                            : '—'
                                    }
                                />
                                <DetailRow
                                    label="Deployed"
                                    value={formatDateTime(screen.deployed_at)}
                                />
                                <DetailRow
                                    label="Sync"
                                    value={
                                        <Badge
                                            variant={contentSyncBadgeVariant(
                                                screen.content_sync,
                                            )}
                                            data-test="admin-screen-content-sync"
                                        >
                                            {screen.content_sync_label}
                                        </Badge>
                                    }
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Device
                        </CardTitle>
                        <CardDescription>
                            Telemetry reported by the Player. Device credentials
                            are never shown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {device === null ? (
                            <p className="text-muted-foreground text-sm">
                                No device is paired to this screen.
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
                                <DetailRow label="Viewport" value={viewport} />
                                <DetailRow
                                    label="Reported orientation"
                                    value={
                                        device.reported_orientation
                                            ? labelize(
                                                  device.reported_orientation,
                                              )
                                            : '—'
                                    }
                                />
                                <DetailRow
                                    label="Playback"
                                    value={
                                        device.playback_state
                                            ? labelize(device.playback_state)
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
                                    label="Reported deployment"
                                    value={
                                        device.reported_deployment_id != null
                                            ? `#${device.reported_deployment_id}`
                                            : '—'
                                    }
                                />
                            </dl>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Recent heartbeats
                        </CardTitle>
                        <CardDescription>
                            Most recent Player check-ins for troubleshooting.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {screen.recent_heartbeats.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="admin-screen-heartbeats-empty"
                            >
                                No heartbeats recorded yet.
                            </p>
                        ) : (
                            <div data-test="admin-screen-heartbeats">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Recorded</TableHead>
                                            <TableHead>Playback</TableHead>
                                            <TableHead>Error</TableHead>
                                            <TableHead>Deployment</TableHead>
                                            <TableHead>Orientation</TableHead>
                                            <TableHead>Player</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {screen.recent_heartbeats.map(
                                            (heartbeat) => (
                                                <TableRow key={heartbeat.id}>
                                                    <TableCell>
                                                        {formatDateTime(
                                                            heartbeat.recorded_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {heartbeat.playback_state
                                                            ? labelize(
                                                                  heartbeat.playback_state,
                                                              )
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {heartbeat.error_code
                                                            ? labelize(
                                                                  heartbeat.error_code,
                                                              )
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {heartbeat.deployment_id !=
                                                        null
                                                            ? `#${heartbeat.deployment_id}`
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell className="capitalize">
                                                        {heartbeat.orientation ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {heartbeat.player_version ??
                                                            '—'}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminScreenShow.layout = (props: AdminScreenShowProps) => ({
    breadcrumbs: [
        {
            title: 'TVs',
            href: screensIndex(),
        },
        {
            title: props.screen.name,
            href: showScreen.url({ screen: props.screen.id }),
        },
    ],
});
