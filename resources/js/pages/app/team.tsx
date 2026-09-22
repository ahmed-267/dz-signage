import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, Shield, UserPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    member_count: number;
    member_limit: number | null;
    plan_name: string | null;
    assignableRoles: RoleOption[];
    roleDescriptions: RoleOption[];
};

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function roleBadgeProps(role: string): {
    variant?: 'info' | 'success' | 'warning' | 'neutral' | 'secondary';
    className?: string;
} {
    switch (role) {
        case 'owner':
            return {
                className:
                    'border-transparent bg-violet-500/15 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
            };
        case 'admin':
            return { variant: 'info' };
        case 'designer':
            return { variant: 'success' };
        case 'content_manager':
            return { variant: 'warning' };
        case 'location_manager':
        case 'viewer':
            return { variant: 'neutral' };
        default:
            return { variant: 'secondary' };
    }
}

function memberInitial(row: TeamRow): string {
    const source = (row.name ?? row.email).trim();
    return source.charAt(0).toUpperCase() || '?';
}

function teamSubtitle(
    count: number,
    planName: string | null,
    memberLimit: number | null,
): string {
    const countLabel = `${count} ${count === 1 ? 'member' : 'members'}`;

    if (planName && memberLimit != null) {
        return `${countLabel} · ${planName} includes ${memberLimit}`;
    }

    if (memberLimit != null) {
        return `${countLabel} · plan includes ${memberLimit}`;
    }

    if (planName) {
        return `${countLabel} · ${planName}`;
    }

    return countLabel;
}

export default function TeamPage({
    members: rows,
    member_count: memberCount,
    member_limit: memberLimit,
    plan_name: planName,
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
        if (!confirm('Remove this member from the business?')) {
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
        if (!confirm('Leave this business?')) {
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
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Actions for invitation ${row.email}`}
                            data-test="team-row-actions"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() => resendInvitation(row.id)}
                        >
                            Resend invite
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => revokeInvitation(row.id)}
                        >
                            Revoke invite
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
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
        const canLeaveSelf = row.is_self && permissions?.can_leave;

        if (!canChangeRole && !canRemove && !canLeaveSelf) {
            return null;
        }

        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${row.email}`}
                        data-test="team-row-actions"
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {canChangeRole ? (
                        <>
                            <DropdownMenuLabel>Change role</DropdownMenuLabel>
                            {assignableRoles.map((role) => (
                                <DropdownMenuItem
                                    key={role.value}
                                    disabled={role.value === row.role}
                                    onSelect={() =>
                                        updateRole(row.id, role.value)
                                    }
                                >
                                    {role.label}
                                </DropdownMenuItem>
                            ))}
                            {(canRemove || canLeaveSelf) && (
                                <DropdownMenuSeparator />
                            )}
                        </>
                    ) : null}
                    {canRemove ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => removeMember(row.id)}
                        >
                            Remove member
                        </DropdownMenuItem>
                    ) : null}
                    {canLeaveSelf ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={leaveWorkspace}
                        >
                            Leave business
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    function MemberCell({ row }: { row: TeamRow }) {
        return (
            <div className="flex items-center gap-3">
                <Avatar className="size-9">
                    <AvatarFallback className="bg-gradient-to-br from-cyan-400 to-violet-500 text-sm font-semibold text-white">
                        {memberInitial(row)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {row.name ?? row.email}
                        {row.is_self ? (
                            <span className="text-muted-foreground font-normal">
                                {' '}
                                (you)
                            </span>
                        ) : null}
                    </p>
                    {row.name ? (
                        <p className="text-muted-foreground truncate text-xs">
                            {row.email}
                        </p>
                    ) : null}
                </div>
            </div>
        );
    }

    function RoleBadge({ row }: { row: TeamRow }) {
        const props = roleBadgeProps(row.role);

        return (
            <Badge variant={props.variant} className={props.className}>
                {row.role_label}
            </Badge>
        );
    }

    function StatusBadge({ row }: { row: TeamRow }) {
        const invited = row.status === 'pending';

        return (
            <Badge variant={invited ? 'warning' : 'success'}>
                {invited ? 'Invited' : 'Active'}
            </Badge>
        );
    }

    return (
        <>
            <Head title="Team" />
            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Team
                        </h1>
                        <p
                            className="text-muted-foreground mt-0.5 text-sm"
                            data-test="team-subtitle"
                        >
                            {teamSubtitle(memberCount, planName, memberLimit)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions?.can_leave ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={leaveWorkspace}
                            >
                                Leave business
                            </Button>
                        ) : null}
                        {permissions?.can_invite ? (
                            <Dialog
                                open={inviteOpen}
                                onOpenChange={setInviteOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button data-test="team-invite-button">
                                        <UserPlus className="size-4" />
                                        Invite Member
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Invite Team Member
                                        </DialogTitle>
                                        <DialogDescription>
                                            Send an email invitation with a
                                            business role.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form
                                        onSubmit={submitInvite}
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-email">
                                                Email address
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
                                                placeholder="colleague@company.com"
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
                                                        {role.label} —{' '}
                                                        {role.description}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={inviteForm.errors.role}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setInviteOpen(false)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={inviteForm.processing}
                                                data-test="team-invite-submit"
                                            >
                                                {inviteForm.processing ? (
                                                    <Spinner />
                                                ) : null}
                                                Send Invite
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : null}
                    </div>
                </div>

                <div className="border-border bg-card overflow-hidden rounded-xl border">
                    <div className="hidden md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="font-mono text-xs uppercase">
                                        Member
                                    </TableHead>
                                    <TableHead className="font-mono text-xs uppercase">
                                        Role
                                    </TableHead>
                                    <TableHead className="font-mono text-xs uppercase">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={`${row.type}-${row.id}`}>
                                        <TableCell>
                                            <MemberCell row={row} />
                                        </TableCell>
                                        <TableCell>
                                            <RoleBadge row={row} />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge row={row} />
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

                    <div className="divide-border divide-y md:hidden">
                        {rows.map((row) => (
                            <div
                                key={`${row.type}-${row.id}-mobile`}
                                className="flex items-start justify-between gap-3 p-4"
                            >
                                <div className="min-w-0 space-y-2">
                                    <MemberCell row={row} />
                                    <div className="flex flex-wrap items-center gap-2">
                                        <RoleBadge row={row} />
                                        <StatusBadge row={row} />
                                    </div>
                                </div>
                                {renderActions(row)}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="border-border bg-card rounded-xl border p-5">
                    <h2 className="font-display mb-3 flex items-center gap-2 text-base font-semibold tracking-tight">
                        <Shield className="text-muted-foreground size-4" />
                        Roles & Permissions
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {roleDescriptions.map((role) => {
                            const badge = roleBadgeProps(role.value);

                            return (
                                <div
                                    key={role.value}
                                    className="bg-secondary/60 rounded-lg p-3"
                                >
                                    <Badge
                                        variant={badge.variant}
                                        className={cn(
                                            'mb-1.5',
                                            badge.className,
                                        )}
                                    >
                                        {role.label}
                                    </Badge>
                                    <p className="text-muted-foreground text-xs leading-relaxed">
                                        {role.description}
                                    </p>
                                </div>
                            );
                        })}
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
