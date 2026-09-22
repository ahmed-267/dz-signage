import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ListPagination } from '@/components/ui/list-pagination';
import { SortableTableHeader } from '@/components/ui/sortable-table-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListSort } from '@/hooks/use-list-sort';
import { adminSelectClassName } from '@/lib/admin-select-class';
import {
    billingStatusTone,
    formatBillingDate,
    intervalLabel,
    invoiceStatusTone,
} from '@/lib/billing';
import { cn } from '@/lib/utils';
import AdminBillingUnavailable from '@/pages/admin/billing/unavailable';
import { subscriptions as subscriptionsIndex } from '@/routes/admin';
import { show as showSubscription } from '@/routes/admin/subscriptions';
import { show as showWorkspace } from '@/routes/admin/workspaces';
import type { AdminSubscriptionsProps } from '@/types/billing';

/** A large fleet must not bury the licence figures under hundreds of badges. */
const CONNECTED_SCREEN_PREVIEW = 12;

export default function AdminSubscriptions(props: AdminSubscriptionsProps) {
    if (props.kind === 'subscription' && props.subscription) {
        return <SubscriptionDetail {...props} />;
    }

    const rows = props.subscriptions?.data ?? [];

    // Truthful: without Stripe keys there are no live subscriptions to manage,
    // but the plan catalog still renders on the index.
    if (
        props.billing_unavailable &&
        rows.length === 0 &&
        !props.plans?.length
    ) {
        return (
            <AdminBillingUnavailable
                title={props.title ?? 'Subscriptions'}
                path="/admin/subscriptions"
                billing_unavailable={props.billing_unavailable}
                message={
                    props.description ??
                    'Subscription management will become available when platform billing is enabled.'
                }
            />
        );
    }

    return <SubscriptionsIndex {...props} />;
}

function SubscriptionsIndex({
    title = 'Subscriptions',
    description,
    subscriptions,
    filters = {},
    statuses = [],
    plans = [],
    billing_unavailable: billingUnavailable = false,
}: AdminSubscriptionsProps) {
    const rows = subscriptions?.data ?? [];
    const meta = subscriptions?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: null,
        to: null,
    };
    const [searchInput, setSearchInput] = useState(filters.q ?? '');
    const perPage = filters.per_page ?? meta.per_page ?? 20;

    const { currentSort, currentDirection, onSort, ariaSort } = useListSort({
        baseUrl: subscriptionsIndex.url(),
        filters: {
            q: filters.q,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: perPage,
        },
        defaultSort: 'created',
        defaultDirection: 'desc',
    });

    useEffect(() => {
        setSearchInput(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === (filters.q ?? '')) {
                return;
            }
            navigate({ q: searchInput, page: 1 });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce against filters snapshot
    }, [searchInput]);

    function navigate(
        patch: Partial<{
            q: string;
            status: string;
            sort: string;
            direction: string;
            per_page: number;
            page: number;
        }>,
    ) {
        router.get(
            subscriptionsIndex.url(),
            {
                q: patch.q ?? filters.q ?? '',
                status: patch.status ?? filters.status ?? 'all',
                sort: patch.sort ?? filters.sort ?? 'created',
                direction: patch.direction ?? filters.direction ?? 'desc',
                per_page: patch.per_page ?? perPage,
                ...(patch.page ? { page: patch.page } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title={title} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-subscriptions"
            >
                <AdminPageHeader title={title} description={description} />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            data-test="admin-subscriptions-search"
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Search businesses or Stripe IDs..."
                            className="pl-9"
                            aria-label="Search subscriptions"
                        />
                    </div>
                    {statuses.length > 0 ? (
                        <select
                            className={cn(adminSelectClassName, 'sm:w-52')}
                            value={filters.status ?? 'all'}
                            aria-label="Status filter"
                            data-test="admin-subscriptions-status"
                            onChange={(event) =>
                                navigate({
                                    status: event.target.value,
                                    page: 1,
                                })
                            }
                        >
                            <option value="all">All statuses</option>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    ) : null}
                </div>

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            All subscriptions
                        </CardTitle>
                        <CardDescription>
                            {meta.total} total. Read-only — changes happen in
                            Stripe or in the Workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {rows.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="admin-subscriptions-empty"
                            >
                                No subscriptions match this search.
                            </p>
                        ) : null}

                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Workspace</TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('status')}
                                        >
                                            <SortableTableHeader
                                                label="Status"
                                                column="status"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead>Interval</TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('quantity')}
                                        >
                                            <SortableTableHeader
                                                label="Licences"
                                                column="quantity"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead aria-sort={ariaSort('ends')}>
                                            <SortableTableHeader
                                                label="Ends"
                                                column="ends"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead
                                            aria-sort={ariaSort('created')}
                                        >
                                            <SortableTableHeader
                                                label="Started"
                                                column="created"
                                                currentSort={currentSort}
                                                currentDirection={
                                                    currentDirection
                                                }
                                                onSort={onSort}
                                            />
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Open
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow
                                            key={row.id}
                                            data-test={`admin-subscription-row-${row.id}`}
                                        >
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={showSubscription.url({
                                                        subscription: row.id,
                                                    })}
                                                    className="hover:underline"
                                                >
                                                    {row.workspace?.name ??
                                                        'Unknown workspace'}
                                                </Link>
                                                <p className="text-muted-foreground font-mono text-xs">
                                                    {row.stripe_id ?? '—'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={row.status_label}
                                                    tone={billingStatusTone(
                                                        row.status,
                                                    )}
                                                />
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {row.interval
                                                    ? intervalLabel(
                                                          row.interval,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {row.used} / {row.licensed}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatBillingDate(row.ends_at)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatBillingDate(
                                                    row.created_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showSubscription.url(
                                                            {
                                                                subscription:
                                                                    row.id,
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
                            {rows.map((row) => (
                                <div
                                    key={row.id}
                                    className="space-y-2 rounded-lg border p-4"
                                    data-test={`admin-subscription-card-${row.id}`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <Link
                                            href={showSubscription.url({
                                                subscription: row.id,
                                            })}
                                            className="font-medium hover:underline"
                                        >
                                            {row.workspace?.name ??
                                                'Unknown workspace'}
                                        </Link>
                                        <StatusBadge
                                            label={row.status_label}
                                            tone={billingStatusTone(row.status)}
                                        />
                                    </div>
                                    <p className="text-muted-foreground font-mono text-xs">
                                        {row.stripe_id ?? '—'}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {row.interval
                                            ? intervalLabel(row.interval)
                                            : 'Unknown interval'}{' '}
                                        · {row.used} / {row.licensed} licences
                                    </p>
                                </div>
                            ))}
                        </div>

                        {meta.last_page > 1 || meta.total > perPage ? (
                            <ListPagination
                                page={meta.current_page}
                                pageCount={meta.last_page}
                                total={meta.total}
                                from={meta.from ?? null}
                                to={meta.to ?? null}
                                perPage={perPage}
                                onPageChange={(page) => navigate({ page })}
                                onPerPageChange={(per_page) =>
                                    navigate({ per_page, page: 1 })
                                }
                            />
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function SubscriptionDetail({
    title = 'Subscription',
    description,
    subscription,
    workspace,
    invoices = [],
    plans = [],
    can_manage_billing: canManageBilling = false,
    billing_unavailable: billingUnavailable = false,
}: AdminSubscriptionsProps) {
    const billing = workspace?.billing;
    const [plan, setPlan] = useState(
        subscription?.plan_key === 'business' ? 'business' : 'starter',
    );
    const [interval, setInterval] = useState(
        subscription?.interval === 'yearly' ? 'yearly' : 'monthly',
    );
    const [confirm, setConfirm] = useState(false);
    const [busy, setBusy] = useState(false);

    if (!subscription) {
        return null;
    }

    const subscriptionId = subscription.id;

    function submitChange() {
        if (!confirm || !canManageBilling || billingUnavailable) {
            return;
        }
        setBusy(true);
        router.patch(
            showSubscription.url({ subscription: subscriptionId }),
            { plan, interval, confirm: true },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    return (
        <>
            <Head title={title} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-subscription-detail"
            >
                <AdminPageHeader
                    title={workspace?.name ?? title}
                    description={description}
                    actions={
                        <>
                            <Button size="sm" variant="outline" asChild>
                                <Link href={subscriptionsIndex.url()}>
                                    All subscriptions
                                </Link>
                            </Button>
                            {workspace ? (
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={showWorkspace.url({
                                            workspace: workspace.id,
                                        })}
                                    >
                                        Open workspace
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="font-display text-lg">
                                    Subscription
                                </CardTitle>
                                <StatusBadge
                                    label={subscription.status_label}
                                    tone={billingStatusTone(
                                        subscription.status,
                                    )}
                                    data-test="admin-subscription-status"
                                />
                            </div>
                            <CardDescription>
                                Synced from Stripe. Super Admins can change
                                Starter/Business plan and interval below when
                                Stripe is configured.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <DetailRow
                                    label="Plan"
                                    value={
                                        subscription.plan_name ??
                                        subscription.plan_key ??
                                        '—'
                                    }
                                />
                                <DetailRow
                                    label="Stripe subscription"
                                    value={subscription.stripe_id ?? '—'}
                                    mono
                                />
                                <DetailRow
                                    label="Stripe status"
                                    value={subscription.stripe_status ?? '—'}
                                    mono
                                />
                                <DetailRow
                                    label="Interval"
                                    value={
                                        subscription.interval
                                            ? intervalLabel(
                                                  subscription.interval,
                                              )
                                            : 'Unknown'
                                    }
                                />
                                <DetailRow
                                    label="Price"
                                    value={subscription.stripe_price ?? '—'}
                                    mono
                                />
                                <DetailRow
                                    label="Quantity"
                                    value={String(subscription.quantity ?? 0)}
                                    mono
                                />
                                <DetailRow
                                    label="Grace period"
                                    value={
                                        subscription.on_grace_period
                                            ? 'Yes'
                                            : 'No'
                                    }
                                />
                                <DetailRow
                                    label="Trial ends"
                                    value={formatBillingDate(
                                        subscription.trial_ends_at,
                                    )}
                                />
                                <DetailRow
                                    label="Ends"
                                    value={formatBillingDate(
                                        subscription.ends_at,
                                    )}
                                />
                                <DetailRow
                                    label="Started"
                                    value={formatBillingDate(
                                        subscription.created_at,
                                    )}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    {canManageBilling ? (
                        <Card
                            className="shadow-none lg:col-span-2"
                            data-test="admin-subscription-change"
                        >
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Change plan
                                </CardTitle>
                                <CardDescription>
                                    Updates the live Stripe subscription. Failed
                                    Stripe calls leave local state unchanged.
                                    Enterprise requires a sales agreement.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <div className="space-y-1.5">
                                    <label
                                        className="text-sm font-medium"
                                        htmlFor="admin-sub-plan"
                                    >
                                        Plan
                                    </label>
                                    <select
                                        id="admin-sub-plan"
                                        className={adminSelectClassName}
                                        value={plan}
                                        disabled={billingUnavailable || busy}
                                        onChange={(e) =>
                                            setPlan(e.target.value)
                                        }
                                        data-test="admin-sub-plan"
                                    >
                                        {(plans.length > 0
                                            ? plans
                                            : [
                                                  {
                                                      key: 'starter',
                                                      name: 'Starter',
                                                  },
                                                  {
                                                      key: 'business',
                                                      name: 'Business',
                                                  },
                                              ]
                                        )
                                            .filter(
                                                (p) =>
                                                    p.key === 'starter' ||
                                                    p.key === 'business',
                                            )
                                            .map((p) => (
                                                <option
                                                    key={p.key}
                                                    value={p.key}
                                                >
                                                    {p.name}
                                                </option>
                                            ))}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <label
                                        className="text-sm font-medium"
                                        htmlFor="admin-sub-interval"
                                    >
                                        Interval
                                    </label>
                                    <select
                                        id="admin-sub-interval"
                                        className={adminSelectClassName}
                                        value={interval}
                                        disabled={billingUnavailable || busy}
                                        onChange={(e) =>
                                            setInterval(e.target.value)
                                        }
                                        data-test="admin-sub-interval"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Annual</option>
                                    </select>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={confirm}
                                        onChange={(e) =>
                                            setConfirm(e.target.checked)
                                        }
                                        data-test="admin-sub-confirm"
                                    />
                                    Confirm Stripe mutation
                                </label>
                                <Button
                                    type="button"
                                    disabled={
                                        billingUnavailable || busy || !confirm
                                    }
                                    onClick={submitChange}
                                    data-test="admin-sub-change-submit"
                                >
                                    Apply change
                                </Button>
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                TV licences
                            </CardTitle>
                            <CardDescription>
                                Used licences count TVs with a non-revoked
                                device.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <DetailRow
                                    label="Used"
                                    value={String(
                                        billing?.used ?? subscription.used,
                                    )}
                                    mono
                                />
                                <DetailRow
                                    label="Licensed"
                                    value={String(
                                        billing?.licensed ??
                                            subscription.licensed,
                                    )}
                                    mono
                                />
                                <DetailRow
                                    label="Remaining"
                                    value={String(billing?.remaining ?? 0)}
                                    mono
                                />
                                <DetailRow
                                    label="Entitled"
                                    value={
                                        billing?.has_entitlement ? 'Yes' : 'No'
                                    }
                                />
                            </dl>

                            {billing && billing.connected_screens.length > 0 ? (
                                <div>
                                    <p className="mb-2 text-sm font-medium">
                                        Connected TVs
                                    </p>
                                    <ul className="flex flex-wrap gap-1.5">
                                        {billing.connected_screens
                                            .slice(0, CONNECTED_SCREEN_PREVIEW)
                                            .map((screen) => (
                                                <li key={screen.id}>
                                                    <Badge variant="secondary">
                                                        {screen.name}
                                                    </Badge>
                                                </li>
                                            ))}
                                        {billing.connected_screens.length >
                                        CONNECTED_SCREEN_PREVIEW ? (
                                            <li className="text-muted-foreground self-center text-xs">
                                                +
                                                {billing.connected_screens
                                                    .length -
                                                    CONNECTED_SCREEN_PREVIEW}{' '}
                                                more
                                            </li>
                                        ) : null}
                                    </ul>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                {subscription.items && subscription.items.length > 0 ? (
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Subscription items
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Stripe item</TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead>Quantity</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {subscription.items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-mono text-sm">
                                                {item.stripe_id ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {item.stripe_price ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {item.quantity ?? 0}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                ) : null}

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Recent invoices
                        </CardTitle>
                        <CardDescription>
                            Latest Stripe invoices mirrored for this business.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No invoices yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Stripe
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoices.map((invoice) => (
                                        <TableRow key={invoice.id}>
                                            <TableCell className="font-mono text-sm">
                                                {invoice.number ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatBillingDate(
                                                    invoice.billed_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {invoice.total_formatted}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={invoice.status}
                                                    tone={invoiceStatusTone(
                                                        invoice.status,
                                                    )}
                                                    className="capitalize"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {invoice.hosted_invoice_url ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                invoice.hosted_invoice_url
                                                            }
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            View
                                                        </a>
                                                    </Button>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">
                                                        —
                                                    </span>
                                                )}
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

function DetailRow({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-sm break-all',
                    mono ? 'font-mono' : undefined,
                )}
            >
                {value}
            </dd>
        </div>
    );
}

AdminSubscriptions.layout = (props: AdminSubscriptionsProps) => ({
    breadcrumbs:
        props.kind === 'subscription'
            ? [
                  { title: 'Subscriptions', href: subscriptionsIndex.url() },
                  {
                      title: props.workspace?.name ?? 'Subscription',
                      href: props.subscription
                          ? showSubscription.url({
                                subscription: props.subscription.id,
                            })
                          : subscriptionsIndex.url(),
                  },
              ]
            : [{ title: 'Subscriptions', href: subscriptionsIndex.url() }],
});
