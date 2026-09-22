import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
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
import { workspaces as workspacesIndex } from '@/routes/admin';
import { show as showWorkspace } from '@/routes/admin/workspaces';

type Member = {
    id?: number;
    name?: string | null;
    email?: string | null;
    role?: string | null;
    joined_at?: string | null;
};

type Counts = {
    screens?: number;
    media?: number;
    playlists?: number;
    schedules?: number;
    screen_designs?: number;
    deployments?: number;
};

type PublishingRow = {
    id?: number;
    screen_name?: string | null;
    content_name?: string | null;
    status_label?: string | null;
    status?: string | null;
    deployed_at?: string | null;
};

type ActivityRow = {
    id?: number;
    action?: string | null;
    actor_name?: string | null;
    entity_type?: string | null;
    created_at?: string | null;
};

type Props = {
    workspace: {
        id: number;
        name: string;
        slug?: string | null;
        industry?: string | null;
        country?: string | null;
        timezone?: string | null;
        owner?: {
            id?: number;
            name?: string | null;
            email?: string | null;
        };
        members?: Member[];
        created_at?: string | null;
        counts?: Counts;
        screens_summary?: {
            total?: number;
            online?: number;
            offline?: number;
            attention?: number;
        };
        content_counts?: Counts;
        recent_publishing?: PublishingRow[];
        recent_activity?: ActivityRow[];
    };
    recent_deployments?: PublishingRow[];
    recent_audit?: ActivityRow[];
    can_delete?: boolean;
};

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleDateString();
    } catch {
        return value;
    }
}

function formatWhen(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

export default function AdminWorkspaceShow({
    workspace,
    recent_deployments,
    recent_audit,
    can_delete = false,
}: Props) {
    const members = workspace.members ?? [];
    const counts = workspace.counts ?? workspace.content_counts ?? {};
    const screens = workspace.screens_summary ?? {
        total: counts.screens,
    };
    const publishing = recent_deployments ?? workspace.recent_publishing ?? [];
    const activity = recent_audit ?? workspace.recent_activity ?? [];
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [confirmName, setConfirmName] = useState('');
    const [deleting, setDeleting] = useState(false);
    const nameMatches = confirmName === workspace.name;

    function removeBusiness() {
        setDeleting(true);
        router.delete(`/admin/workspaces/${workspace.id}`, {
            data: { confirm_name: confirmName },
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={workspace.name} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-workspace-detail"
            >
                <AdminPageHeader
                    title={workspace.name}
                    description={
                        workspace.slug ? `/${workspace.slug}` : undefined
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can_delete ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    data-test="admin-delete-business"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    Delete Business
                                </Button>
                            ) : null}
                            <Button variant="outline" asChild>
                                <Link href={workspacesIndex()}>
                                    Back to businesses
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Overview
                            </CardTitle>
                            <CardDescription>
                                Organisation metadata for this business.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Industry
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.industry ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Country
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.country ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Timezone
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.timezone ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Created
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(workspace.created_at)}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Owner
                            </CardTitle>
                            <CardDescription>
                                Primary owner membership.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="font-medium">
                                {workspace.owner?.name ?? '—'}
                            </div>
                            <div className="text-muted-foreground">
                                {workspace.owner?.email ?? '—'}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                TVs summary
                            </CardTitle>
                            <CardDescription>
                                Screen inventory for this business.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                {(
                                    [
                                        [
                                            'Total',
                                            screens.total ?? counts.screens,
                                        ],
                                        ['Online', screens.online],
                                        ['Offline', screens.offline],
                                        ['Attention', screens.attention],
                                        ['Deployments', counts.deployments],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between gap-4"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-medium">
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Content counts
                            </CardTitle>
                            <CardDescription>
                                Library sizes for this business.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                {(
                                    [
                                        ['Media', counts.media],
                                        [
                                            'Screen designs',
                                            counts.screen_designs,
                                        ],
                                        ['Playlists', counts.playlists],
                                        ['Schedules', counts.schedules],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between gap-4"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-medium">
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Publishing recent
                        </CardTitle>
                        <CardDescription>
                            Latest deployments in this business.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {publishing.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No recent publishing activity.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Content</TableHead>
                                        <TableHead>Screen</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {publishing.map((row, index) => (
                                        <TableRow key={row.id ?? index}>
                                            <TableCell>
                                                {row.content_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {row.screen_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={
                                                        row.status_label ??
                                                        row.status ??
                                                        '—'
                                                    }
                                                    tone={row.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatWhen(row.deployed_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Members
                        </CardTitle>
                        <CardDescription>
                            Everyone currently in this business.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Joined</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((member, index) => (
                                        <TableRow
                                            key={
                                                member.id ??
                                                `${member.email}-${index}`
                                            }
                                        >
                                            <TableCell>
                                                {member.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {member.email ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {member.role ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(member.joined_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {members.map((member, index) => (
                                <div
                                    key={
                                        member.id ??
                                        `${member.email}-${index}-mobile`
                                    }
                                    className="rounded-lg border p-4 text-sm"
                                >
                                    <div className="font-medium">
                                        {member.name ?? '—'}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {member.email ?? '—'}
                                    </div>
                                    <div className="mt-2">
                                        {member.role ?? '—'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Recent activity
                        </CardTitle>
                        <CardDescription>
                            Audit events related to this business.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activity.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No recent activity recorded.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Actor</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {activity.map((row, index) => (
                                        <TableRow key={row.id ?? index}>
                                            <TableCell>
                                                <div>{row.action ?? '—'}</div>
                                                {row.entity_type ? (
                                                    <div className="text-muted-foreground text-xs">
                                                        {row.entity_type}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {row.actor_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatWhen(row.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete {workspace.name}</DialogTitle>
                        <DialogDescription>
                            Deleting {workspace.name} will remove access to its
                            content, TVs, media and business data. Paired TVs
                            are disconnected and an active subscription is
                            cancelled. This can be restored from the database,
                            but it is not a one-click undo in the product.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            {counts.screens ?? 0} TVs · {counts.media ?? 0}{' '}
                            media · {counts.screen_designs ?? 0} screen designs
                            · {counts.playlists ?? 0} playlists ·{' '}
                            {counts.schedules ?? 0} schedules
                        </p>
                        <Label htmlFor="confirm-business-name">
                            Type {workspace.name} to confirm
                        </Label>
                        <Input
                            id="confirm-business-name"
                            data-test="admin-delete-business-name"
                            value={confirmName}
                            onChange={(event) =>
                                setConfirmName(event.target.value)
                            }
                            autoComplete="off"
                        />
                    </div>
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
                            data-test="admin-delete-business-confirm"
                            disabled={!nameMatches || deleting}
                            onClick={removeBusiness}
                        >
                            Delete Business
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminWorkspaceShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Businesses',
            href: workspacesIndex(),
        },
        {
            title: props.workspace.name,
            href: showWorkspace.url({ workspace: props.workspace.id }),
        },
    ],
});
