import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { help as helpIndex } from '@/routes/app';
import { store as storeHelp } from '@/routes/app/help';

type CategoryOption = { value: string; label: string };

type RecentRequest = {
    id: number;
    subject?: string | null;
    status?: string | null;
    status_label?: string | null;
    priority?: string | null;
    created_at?: string | null;
};

type Props = {
    categories?: CategoryOption[];
    priorities?: CategoryOption[];
    support_email?: string | null;
    recent_requests?: RecentRequest[];
};

const textareaClassName = cn(
    'border-input placeholder:text-muted-foreground flex min-h-32 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const DEFAULT_CATEGORIES: CategoryOption[] = [
    { value: 'general', label: 'General' },
    { value: 'billing', label: 'Billing' },
    { value: 'screens', label: 'TVs / Player' },
    { value: 'content', label: 'Content & publishing' },
    { value: 'account', label: 'Account & access' },
    { value: 'other', label: 'Other' },
];

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

export default function AppHelpIndex({
    categories = DEFAULT_CATEGORIES,
    priorities = [
        { value: 'low', label: 'Low' },
        { value: 'medium', label: 'Medium' },
        { value: 'high', label: 'High' },
        { value: 'urgent', label: 'Urgent' },
    ],
    support_email,
    recent_requests = [],
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string };
    };

    const form = useForm({
        subject: '',
        category: categories[0]?.value ?? 'general',
        message: '',
        priority:
            priorities.find((p) => p.value === 'medium')?.value ??
            priorities[0]?.value ??
            'medium',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeHelp.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('subject', 'message'),
        });
    }

    return (
        <>
            <Head title="Help & Support" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">
                        Help & Support
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Send a request to the RMSignage platform team
                        {support_email ? ` (${support_email})` : ''}.
                    </p>
                </div>

                {flash?.success ? (
                    <p
                        className="text-success text-sm font-medium"
                        data-test="app-help-success"
                    >
                        {flash.success}
                    </p>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                How to pair a TV
                            </CardTitle>
                            <CardDescription>
                                Connect any browser-capable TV, stick, or mini
                                PC — no native Samsung, LG, or Fire TV app
                                required.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="text-muted-foreground list-decimal space-y-2 pl-4 text-sm leading-relaxed">
                                <li>
                                    On the TV, open the RMSignage Player URL
                                    (shown under Paired TVs → Pair a TV).
                                </li>
                                <li>
                                    The TV displays a QR code and a short
                                    pairing PIN. Keep that screen open.
                                </li>
                                <li>
                                    In RMSignage, go to{' '}
                                    <span className="text-foreground font-medium">
                                        Paired TVs
                                    </span>{' '}
                                    and choose Pair a TV. Enter the PIN, or scan
                                    the QR on another device to open the claim
                                    link.
                                </li>
                                <li>
                                    Give the TV a name and optional location,
                                    then confirm.
                                </li>
                                <li>
                                    Publish a Screen or Playlist so the TV has
                                    content to display.
                                </li>
                            </ol>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Contact support
                            </CardTitle>
                            <CardDescription>
                                Tell us what you need help with. We will follow
                                up by email.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-4"
                                onSubmit={submit}
                                data-test="app-help-form"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="subject">Subject</Label>
                                    <Input
                                        id="subject"
                                        value={form.data.subject}
                                        onChange={(event) =>
                                            form.setData(
                                                'subject',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        data-test="app-help-subject"
                                    />
                                    <InputError message={form.errors.subject} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="category">Category</Label>
                                    <select
                                        id="category"
                                        className={adminSelectClassName}
                                        value={form.data.category}
                                        onChange={(event) =>
                                            form.setData(
                                                'category',
                                                event.target.value,
                                            )
                                        }
                                        data-test="app-help-category"
                                    >
                                        {categories.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.category}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="priority">Priority</Label>
                                    <select
                                        id="priority"
                                        className={adminSelectClassName}
                                        value={form.data.priority}
                                        onChange={(event) =>
                                            form.setData(
                                                'priority',
                                                event.target.value,
                                            )
                                        }
                                        data-test="app-help-priority"
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
                                    <InputError
                                        message={form.errors.priority}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="message">Message</Label>
                                    <textarea
                                        id="message"
                                        className={textareaClassName}
                                        value={form.data.message}
                                        onChange={(event) =>
                                            form.setData(
                                                'message',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        data-test="app-help-message"
                                    />
                                    <InputError message={form.errors.message} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    data-test="app-help-submit"
                                >
                                    Submit request
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Your recent requests
                        </CardTitle>
                        <CardDescription>
                            Requests you submitted from this business.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recent_requests.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No support requests yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_requests.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium">
                                                {row.subject ?? '—'}
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
        </>
    );
}

AppHelpIndex.layout = {
    breadcrumbs: [
        {
            title: 'Help & Support',
            href: helpIndex(),
        },
    ],
};
