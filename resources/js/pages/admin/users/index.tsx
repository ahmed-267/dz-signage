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
import { users as usersIndex } from '@/routes/admin';

type UserRow = {
    id: number;
    name: string;
    email: string;
    workspace_count: number;
    is_admin: boolean;
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
    users: Paginated<UserRow>;
};

export default function AdminUsersIndex({ users }: Props) {
    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <div className="mb-1">
                        <Badge variant="info">Super Admin</Badge>
                    </div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Users
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Platform accounts across all workspaces.
                    </p>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All users
                        </CardTitle>
                        <CardDescription>
                            {users.data.length} shown on this page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Workspaces</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Joined</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                {user.workspace_count}
                                            </TableCell>
                                            <TableCell>
                                                {user.is_admin ? (
                                                    <Badge variant="info">
                                                        Super Admin
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        User
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {user.created_at
                                                    ? new Date(
                                                          user.created_at,
                                                      ).toLocaleDateString()
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {users.data.map((user) => (
                                <div
                                    key={user.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {user.email}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {user.is_admin ? (
                                            <Badge variant="info">
                                                Super Admin
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                User
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground text-xs">
                                            {user.workspace_count} workspaces
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {users.links.length > 3 ? (
                            <div className="flex flex-wrap gap-2">
                                {users.links.map((link, index) =>
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

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
};
