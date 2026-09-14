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

type WorkspaceRow = {
    id: number;
    name: string;
    industry: string;
    owner: string | null;
    owner_email: string | null;
    member_count: number;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Props = {
    workspaces: Paginated<WorkspaceRow>;
};

export default function AdminWorkspacesIndex({ workspaces }: Props) {
    return (
        <>
            <Head title="Workspaces" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <div className="mb-1">
                        <Badge variant="info">Super Admin</Badge>
                    </div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Workspaces
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Platform-wide workspace directory.
                    </p>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All workspaces
                        </CardTitle>
                        <CardDescription>
                            {workspaces.data.length} shown on this page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Industry</TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>Members</TableHead>
                                        <TableHead className="text-right">
                                            Open
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {workspaces.data.map((workspace) => (
                                        <TableRow key={workspace.id}>
                                            <TableCell className="font-medium">
                                                {workspace.name}
                                            </TableCell>
                                            <TableCell>
                                                {workspace.industry}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {workspace.owner ?? '—'}
                                                </div>
                                                {workspace.owner_email ? (
                                                    <div className="text-muted-foreground text-xs">
                                                        {workspace.owner_email}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                {workspace.member_count}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showWorkspace.url(
                                                            {
                                                                workspace:
                                                                    workspace.id,
                                                            },
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {workspaces.data.map((workspace) => (
                                <div
                                    key={workspace.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <div className="font-medium">
                                        {workspace.name}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {workspace.industry} ·{' '}
                                        {workspace.member_count} members
                                    </div>
                                    <div className="text-sm">
                                        Owner: {workspace.owner ?? '—'}
                                    </div>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={showWorkspace.url({
                                                workspace: workspace.id,
                                            })}
                                        >
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>

                        {workspaces.links.length > 3 ? (
                            <div className="flex flex-wrap gap-2">
                                {workspaces.links.map((link, index) =>
                                    link.url ? (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            size="sm"
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            asChild
                                        >
                                            <Link href={link.url}>
                                                <span
                                                    dangerouslySetInnerHTML={{
                                                        __html: link.label,
                                                    }}
                                                />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            size="sm"
                                            variant="outline"
                                            disabled
                                        >
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Button>
                                    ),
                                )}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminWorkspacesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Workspaces',
            href: workspacesIndex(),
        },
    ],
};
