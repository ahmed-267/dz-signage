import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { cn } from '@/lib/utils';
import { support as supportIndex } from '@/routes/admin';
import {
    show as showSupport,
    update as updateSupport,
} from '@/routes/admin/support';

type Note = {
    id?: number;
    body?: string | null;
    author_name?: string | null;
    created_at?: string | null;
    is_internal?: boolean;
};

type FilterOption = { value: string; label: string };

type SupportDetail = {
    id: number;
    subject?: string | null;
    category?: string | null;
    category_label?: string | null;
    message?: string | null;
    workspace_name?: string | null;
    user_name?: string | null;
    user_email?: string | null;
    requester_name?: string | null;
    requester_email?: string | null;
    status?: string | null;
    status_label?: string | null;
    priority?: string | null;
    priority_label?: string | null;
    admin_notes?: string | null;
    assignee_name?: string | null;
    created_at?: string | null;
    resolved_at?: string | null;
    notes?: Note[];
};

type Props = {
    request: SupportDetail;
    statuses?: FilterOption[];
    priorities?: FilterOption[];
    can_update?: boolean;
};

const textareaClassName = cn(
    'border-input placeholder:text-muted-foreground flex min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

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

export default function AdminSupportShow({
    request,
    statuses = [],
    priorities = [],
    can_update = true,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string };
    };

    const notes = request.notes ?? [];

    const form = useForm({
        status: request.status ?? 'open',
        priority: request.priority ?? 'medium',
        admin_notes: request.admin_notes ?? '',
        note: '',
        is_internal: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(updateSupport.url(request.id), {
            preserveScroll: true,
            onSuccess: () => form.setData('note', ''),
        });
    }

    return (
        <>
            <Head title={request.subject ?? `Support #${request.id}`} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-support-detail"
            >
                <AdminPageHeader
                    title={request.subject ?? `Support #${request.id}`}
                    description={
                        request.category_label ?? request.category ?? undefined
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={supportIndex()}>Back to support</Link>
                        </Button>
                    }
                />

                {flash?.success ? (
                    <p
                        className="text-success text-sm font-medium"
                        data-test="admin-support-flash"
                    >
                        {flash.success}
                    </p>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="shadow-none lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Request
                            </CardTitle>
                            <CardDescription>
                                Submitted {formatWhen(request.created_at)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Workspace
                                    </dt>
                                    <dd className="font-medium">
                                        {request.workspace_name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Requester
                                    </dt>
                                    <dd className="font-medium">
                                        {request.user_name ??
                                            request.requester_name ??
                                            '—'}
                                        {(request.user_email ??
                                        request.requester_email) ? (
                                            <div className="text-muted-foreground text-xs font-normal">
                                                {request.user_email ??
                                                    request.requester_email}
                                            </div>
                                        ) : null}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd className="mt-1">
                                        <StatusBadge
                                            label={
                                                request.status_label ??
                                                request.status ??
                                                '—'
                                            }
                                            tone={request.status}
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Priority
                                    </dt>
                                    <dd className="mt-1">
                                        <StatusBadge
                                            label={
                                                request.priority_label ??
                                                request.priority ??
                                                '—'
                                            }
                                            tone={
                                                request.priority === 'high' ||
                                                request.priority === 'urgent'
                                                    ? 'degraded'
                                                    : 'neutral'
                                            }
                                        />
                                    </dd>
                                </div>
                            </dl>
                            <div className="rounded-lg border p-4 whitespace-pre-wrap">
                                {request.message ?? 'No message provided.'}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Update
                            </CardTitle>
                            <CardDescription>
                                Change status, priority, and add a note.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-4" onSubmit={submit}>
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        className={adminSelectClassName}
                                        value={form.data.status}
                                        disabled={!can_update}
                                        onChange={(event) =>
                                            form.setData(
                                                'status',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {statuses.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="priority">Priority</Label>
                                    <select
                                        id="priority"
                                        className={adminSelectClassName}
                                        value={form.data.priority}
                                        disabled={!can_update}
                                        onChange={(event) =>
                                            form.setData(
                                                'priority',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {priorities.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="admin_notes">
                                        Admin notes
                                    </Label>
                                    <textarea
                                        id="admin_notes"
                                        className={textareaClassName}
                                        value={form.data.admin_notes}
                                        disabled={!can_update}
                                        onChange={(event) =>
                                            form.setData(
                                                'admin_notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="note">Add note</Label>
                                    <textarea
                                        id="note"
                                        className={textareaClassName}
                                        value={form.data.note}
                                        disabled={!can_update}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional internal note..."
                                    />
                                </div>
                                {can_update ? (
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        Save changes
                                    </Button>
                                ) : null}
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Notes
                        </CardTitle>
                        <CardDescription>
                            Internal notes for platform staff.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {notes.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No notes yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Note</TableHead>
                                        <TableHead>Author</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {notes.map((note, index) => (
                                        <TableRow key={note.id ?? index}>
                                            <TableCell className="max-w-xl text-sm whitespace-pre-wrap">
                                                {note.body ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {note.author_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatWhen(note.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminSupportShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Support Requests',
            href: supportIndex(),
        },
        {
            title: props.request.subject ?? `Request #${props.request.id}`,
            href: showSupport.url(props.request.id),
        },
    ],
});
