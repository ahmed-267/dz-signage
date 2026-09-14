import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { team as teamIndex } from '@/routes/app';
import team from '@/routes/app/team';

type RoleOption = {
    value: string;
    label: string;
    description: string;
};

type TeamRow = {
    id: number;
    type: 'member' | 'invitation';
    user_id: number | null;
    name: string | null;
    email: string;
    role: string;
    role_label: string;
    status: 'active' | 'pending';
    joined_at: string | null;
    is_self: boolean;
};

type Props = {
    members: TeamRow[];
    assignableRoles: RoleOption[];
    roleDescriptions: RoleOption[];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export default function TeamPage({
    members: rows,
    assignableRoles,
    roleDescriptions,
}: Props) {
    const { workspace } = usePage().props;
    const permissions = workspace?.permissions;
    const [inviteOpen, setInviteOpen] = useState(false);

    const inviteForm = useForm({
        email: '',
        role: assignableRoles[0]?.value ?? 'viewer',
    });

    function submitInvite(event: FormEvent) {
        event.preventDefault();
        inviteForm.post(team.invite.url(), {
            preserveScroll: true,
            onSuccess: () => {
                inviteForm.reset();
                setInviteOpen(false);
            },
        });
    }

    function updateRole(memberId: number, role: string) {
        router.patch(
            team.members.update.url({ member: memberId }),
            { role },
            { preserveScroll: true },
        );
    }

    function removeMember(memberId: number) {
        if (!confirm('Remove this member from the workspace?')) {
            return;
        }

        router.delete(team.members.destroy.url({ member: memberId }), {
            preserveScroll: true,
        });
    }

    function resendInvitation(invitationId: number) {
        router.post(
            team.invitations.resend.url({ invitation: invitationId }),
            {},
            { preserveScroll: true },
        );
    }

    function revokeInvitation(invitationId: number) {
        if (!confirm('Cancel this invitation?')) {
            return;
        }

        router.post(
            team.invitations.revoke.url({ invitation: invitationId }),
            {},
            { preserveScroll: true },
        );
    }

    function leaveWorkspace() {
        if (!confirm('Leave this workspace?')) {
            return;
        }

        router.post(team.leave.url());
    }

    function renderActions(row: TeamRow) {
        if (row.type === 'invitation') {
            if (!permissions?.can_invite) {
                return null;
            }

            return (
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => resendInvitation(row.id)}
                    >
                        Resend
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        onClick={() => revokeInvitation(row.id)}
                    >
                        Revoke
                    </Button>
                </div>
            );
        }

        const canChangeRole =
            permissions?.can_update_roles &&
            !row.is_self &&
            row.role !== 'owner';
        const canRemove =
            permissions?.can_remove_members &&
            !row.is_self &&
            row.role !== 'owner';

        if (!canChangeRole && !canRemove) {
            return null;
        }

        return (
            <div className="flex flex-wrap items-center gap-2">
                {canChangeRole ? (
                    <select
                        className={cn(selectClassName, 'w-auto min-w-36')}
                        value={row.role}
                        onChange={(event) =>
                            updateRole(row.id, event.target.value)
                        }
                        aria-label={`Change role for ${row.email}`}
                    >
                        {assignableRoles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                ) : null}
                {canRemove ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        onClick={() => removeMember(row.id)}
                    >
                        Remove
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <>
            <Head title="Team" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Team
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Manage members and pending invitations for this
                            workspace.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions?.can_leave ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={leaveWorkspace}
                            >
                                Leave workspace
                            </Button>
                        ) : null}
                        {permissions?.can_invite ? (
                            <Dialog
                                open={inviteOpen}
                                onOpenChange={setInviteOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button data-test="team-invite-button">
                                        Invite member
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Invite member</DialogTitle>
                                        <DialogDescription>
                                            Send an email invitation with a
                                            workspace role.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form
                                        onSubmit={submitInvite}
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-email">
                                                Email
                                            </Label>
                                            <Input
                                                id="invite-email"
                                                type="email"
                                                value={inviteForm.data.email}
                                                onChange={(event) =>
                                                    inviteForm.setData(
                                                        'email',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                placeholder="colleague@example.com"
                                                data-test="team-invite-email"
                                            />
                                            <InputError
                                                message={
                                                    inviteForm.errors.email
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-role">
                                                Role
                                            </Label>
                                            <select
                                                id="invite-role"
                                                className={selectClassName}
                                                value={inviteForm.data.role}
                                                onChange={(event) =>
                                                    inviteForm.setData(
                                                        'role',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {assignableRoles.map((role) => (
                                                    <option
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={inviteForm.errors.role}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={inviteForm.processing}
                                                data-test="team-invite-submit"
                                            >
                                                {inviteForm.processing ? (
                                                    <Spinner />
                                                ) : null}
                                                Send invitation
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : null}
                    </div>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Members & invitations
                        </CardTitle>
                        <CardDescription>
                            Active members and pending invites for the current
                            workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Person</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow key={`${row.type}-${row.id}`}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {row.name ?? row.email}
                                                </div>
                                                {row.name ? (
                                                    <div className="text-muted-foreground text-xs">
                                                        {row.email}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {row.role_label}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        row.status === 'active'
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                >
                                                    {row.status === 'active'
                                                        ? 'Active'
                                                        : 'Pending'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end">
                                                    {renderActions(row)}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {rows.map((row) => (
                                <div
                                    key={`${row.type}-${row.id}-mobile`}
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {row.name ?? row.email}
                                        </div>
                                        {row.name ? (
                                            <div className="text-muted-foreground text-sm">
                                                {row.email}
                                            </div>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary">
                                            {row.role_label}
                                        </Badge>
                                        <Badge
                                            variant={
                                                row.status === 'active'
                                                    ? 'success'
                                                    : 'warning'
                                            }
                                        >
                                            {row.status === 'active'
                                                ? 'Active'
                                                : 'Pending'}
                                        </Badge>
                                    </div>
                                    {renderActions(row)}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <h2 className="font-display mb-3 text-lg font-semibold tracking-tight">
                        Role descriptions
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {roleDescriptions.map((role) => (
                            <Card key={role.value} className="shadow-none">
                                <CardHeader className="gap-1">
                                    <CardTitle className="text-base">
                                        {role.label}
                                    </CardTitle>
                                    <CardDescription>
                                        {role.description}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

TeamPage.layout = {
    breadcrumbs: [
        {
            title: 'Team',
            href: teamIndex(),
        },
    ],
};
