import { Head, Link } from '@inertiajs/react';
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
import { workspaces as workspacesIndex } from '@/routes/admin';
import { show as showWorkspace } from '@/routes/admin/workspaces';

type Props = {
    workspace: {
        id: number;
        name: string;
        slug: string;
        industry: string;
        country: string;
        timezone: string;
        owner: {
            name: string | null;
            email: string | null;
        };
        members: Array<{
            name: string | null;
            email: string | null;
            role: string;
            joined_at: string | null;
        }>;
        created_at: string | null;
    };
};

export default function AdminWorkspaceShow({ workspace }: Props) {
    return (
        <>
            <Head title={workspace.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="mb-1">
                            <Badge variant="info">Super Admin</Badge>
                        </div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            {workspace.name}
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            /{workspace.slug}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={workspacesIndex()}>Back to workspaces</Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Details
                            </CardTitle>
                            <CardDescription>
                                Organisation metadata for this workspace.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Industry
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.industry}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Country
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.country}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Timezone
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.timezone}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Created
                                    </dt>
                                    <dd className="font-medium">
                                        {workspace.created_at
                                            ? new Date(
                                                  workspace.created_at,
                                              ).toLocaleDateString()
                                            : '—'}
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
                                {workspace.owner.name ?? '—'}
                            </div>
                            <div className="text-muted-foreground">
                                {workspace.owner.email ?? '—'}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Members
                        </CardTitle>
                        <CardDescription>
                            Everyone currently in this workspace.
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
                                    {workspace.members.map((member, index) => (
                                        <TableRow
                                            key={`${member.email}-${index}`}
                                        >
                                            <TableCell>
                                                {member.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {member.email ?? '—'}
                                            </TableCell>
                                            <TableCell>{member.role}</TableCell>
                                            <TableCell>
                                                {member.joined_at
                                                    ? new Date(
                                                          member.joined_at,
                                                      ).toLocaleDateString()
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {workspace.members.map((member, index) => (
                                <div
                                    key={`${member.email}-${index}-mobile`}
                                    className="rounded-lg border p-4 text-sm"
                                >
                                    <div className="font-medium">
                                        {member.name ?? '—'}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {member.email ?? '—'}
                                    </div>
                                    <div className="mt-2">{member.role}</div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminWorkspaceShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Workspaces',
            href: workspacesIndex(),
        },
        {
            title: props.workspace.name,
            href: showWorkspace.url({ workspace: props.workspace.id }),
        },
    ],
});
