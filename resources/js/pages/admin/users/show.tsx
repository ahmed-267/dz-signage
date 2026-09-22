import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { adminSelectClassName } from '@/lib/admin-select-class';
import { users as usersIndex } from '@/routes/admin';
import {
    platform_role as updatePlatformRole,
    show as showUser,
} from '@/routes/admin/users';

type Membership = {
    workspace_id?: number;
    workspace_name?: string | null;
    role?: string | null;
    joined_at?: string | null;
};

type AuditRow = {
    id?: number;
    action?: string | null;
    subject?: string | null;
    created_at?: string | null;
};

type RoleOption = { value: string; label: string };

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        email_verified_at?: string | null;
        verified?: boolean;
        platform_role?: string | null;
        platform_role_label?: string | null;
        created_at?: string | null;
        is_self?: boolean;
    };
    memberships?: Membership[];
    recent_audit?: AuditRow[];
    can_manage_role?: boolean;
    role_options?: RoleOption[];
    platform_roles?: RoleOption[];
    permissions?: {
        can_manage_platform_roles?: boolean;
        is_super_admin?: boolean;
    };
};

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

function isVerified(user: Props['user']): boolean {
    if (typeof user.verified === 'boolean') {
        return user.verified;
    }

    return Boolean(user.email_verified_at);
}

export default function AdminUserShow({
    user,
    memberships = [],
    recent_audit = [],
    can_manage_role,
    role_options,
    platform_roles,
    permissions,
}: Props) {
    const page = usePage();
    const authUserId = page.props.auth?.user?.id;
    const isSelf =
        user.is_self === true ||
        (authUserId != null && Number(authUserId) === Number(user.id));

    const options: RoleOption[] = [
        { value: '', label: 'User (no platform role)' },
        ...(role_options ??
            platform_roles ?? [
                { value: 'platform_admin', label: 'Admin' },
                { value: 'super_admin', label: 'Super Admin' },
            ]),
    ];

    const allowManage =
        (can_manage_role ?? permissions?.can_manage_platform_roles ?? false) &&
        !isSelf;

    const [selectedRole, setSelectedRole] = useState(user.platform_role ?? '');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    function confirmRoleChange() {
        setSubmitting(true);
        router.patch(
            updatePlatformRole.url(user.id),
            { platform_role: selectedRole || null },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setConfirmOpen(false);
                },
            },
        );
    }

    return (
        <>
            <Head title={user.name} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-user-detail"
            >
                <AdminPageHeader
                    title={user.name}
                    description={user.email}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={usersIndex()}>Back to users</Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Identity
                            </CardTitle>
                            <CardDescription>
                                Account identity and verification.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd className="font-medium">{user.name}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd className="font-medium">
                                        {user.email}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Verification
                                    </dt>
                                    <dd>
                                        {isVerified(user) ? (
                                            <Badge variant="success">
                                                Verified
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning">
                                                Unverified
                                            </Badge>
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Platform role
                                    </dt>
                                    <dd>
                                        {user.platform_role_label ? (
                                            <Badge variant="info">
                                                {user.platform_role_label}
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                User
                                            </Badge>
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Joined
                                    </dt>
                                    <dd className="font-medium">
                                        {formatWhen(user.created_at)}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    {allowManage ? (
                        <Card
                            className="shadow-none"
                            data-test="admin-user-role-form"
                        >
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Platform role
                                </CardTitle>
                                <CardDescription>
                                    Super Admin can promote or demote Platform
                                    Admins. You cannot change your own role
                                    here.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="platform_role">Role</Label>
                                    <select
                                        id="platform_role"
                                        className={adminSelectClassName}
                                        value={selectedRole}
                                        onChange={(event) =>
                                            setSelectedRole(event.target.value)
                                        }
                                    >
                                        {options.map((option) => (
                                            <option
                                                key={option.value || 'none'}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button
                                    type="button"
                                    disabled={
                                        selectedRole ===
                                            (user.platform_role ?? '') ||
                                        submitting
                                    }
                                    onClick={() => setConfirmOpen(true)}
                                >
                                    Update role
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Platform role
                                </CardTitle>
                                <CardDescription>
                                    {isSelf
                                        ? 'You cannot change your own platform role from this screen.'
                                        : 'Only Super Admins can change platform roles.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {user.platform_role_label ? (
                                    <Badge variant="info">
                                        {user.platform_role_label}
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">User</Badge>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Memberships
                        </CardTitle>
                        <CardDescription>
                            Businesses this user belongs to.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {memberships.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No workspace memberships.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Workspace</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Joined</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {memberships.map((membership, index) => (
                                        <TableRow
                                            key={
                                                membership.workspace_id ?? index
                                            }
                                        >
                                            <TableCell>
                                                {membership.workspace_id !=
                                                null ? (
                                                    <Link
                                                        href={`/admin/workspaces/${membership.workspace_id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {membership.workspace_name ??
                                                            '—'}
                                                    </Link>
                                                ) : (
                                                    (membership.workspace_name ??
                                                    '—')
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {membership.role ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatWhen(
                                                    membership.joined_at,
                                                )}
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
                            Recent audit
                        </CardTitle>
                        <CardDescription>
                            Recent actions involving this user.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recent_audit.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No recent audit events.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_audit.map((row, index) => (
                                        <TableRow key={row.id ?? index}>
                                            <TableCell>
                                                {row.action ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {row.subject ?? '—'}
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

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm role change</DialogTitle>
                        <DialogDescription>
                            Update {user.name}&apos;s platform role to{' '}
                            {options.find((o) => o.value === selectedRole)
                                ?.label ?? 'User'}
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                            disabled={submitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={confirmRoleChange}
                            disabled={submitting}
                        >
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminUserShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
        {
            title: props.user.name,
            href: showUser.url(props.user.id),
        },
    ],
});
