import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ADMIN_BILLING_TABS } from '@/components/admin/admin-section-header';
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
import { edit as editPlan } from '@/routes/admin/subscriptions/plans';
import { subscriptions as subscriptionsIndex } from '@/routes/admin';

type AdminPlanRow = {
    id: number;
    key: string;
    name: string;
    monthly_formatted: string | null;
    annual_formatted: string | null;
    yearly_eq_formatted: string | null;
    screen_limit: number | null;
    storage_gb: number | null;
    team_member_limit: number | null;
    active: boolean;
    public: boolean;
    popular: boolean;
    enterprise: boolean;
    sort_order: number;
    stripe_synced: boolean;
};

type Props = {
    plans?: AdminPlanRow[];
    can_manage?: boolean;
    stripe_configured?: boolean;
    currency?: string;
};

export default function AdminBillingPlansIndex({
    plans = [],
    can_manage: canManage = false,
    stripe_configured: stripeConfigured = false,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };

    return (
        <>
            <Head title="Plans" />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-billing-plans"
            >
                <AdminPageHeader
                    title="Billing"
                    description="Plans, subscriptions and invoices."
                    badge={null}
                    tabs={ADMIN_BILLING_TABS}
                    activeTab="plans"
                />

                {flash?.success ? (
                    <p className="text-success text-sm font-medium">
                        {flash.success}
                    </p>
                ) : null}
                {flash?.error ? (
                    <p className="text-destructive text-sm font-medium">
                        {flash.error}
                    </p>
                ) : null}

                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="font-display text-lg">
                            Catalog
                        </CardTitle>
                        <CardDescription>
                            {stripeConfigured
                                ? 'Stripe is configured — price sync is available on edit.'
                                : 'Stripe is not configured — local amount edits are allowed; Prices will show as not synchronised.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Plan</TableHead>
                                    <TableHead>Monthly</TableHead>
                                    <TableHead>Annual</TableHead>
                                    <TableHead>Limits</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Stripe</TableHead>
                                    <TableHead>Order</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plans.map((plan) => (
                                    <TableRow
                                        key={plan.id}
                                        data-test={`admin-plan-row-${plan.key}`}
                                    >
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {plan.name}
                                                </span>
                                                {plan.popular ? (
                                                    <Badge variant="secondary">
                                                        Popular
                                                    </Badge>
                                                ) : null}
                                                {plan.enterprise ? (
                                                    <Badge variant="outline">
                                                        Custom
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                {plan.key}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {plan.enterprise
                                                ? 'Custom'
                                                : (plan.monthly_formatted ??
                                                  '—')}
                                        </TableCell>
                                        <TableCell>
                                            {plan.enterprise
                                                ? 'Custom'
                                                : plan.yearly_eq_formatted
                                                  ? `${plan.yearly_eq_formatted}/mo eq`
                                                  : (plan.annual_formatted ??
                                                    '—')}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {plan.enterprise
                                                ? 'Custom'
                                                : `${plan.screen_limit ?? '—'} screens · ${plan.storage_gb ?? '—'} GB · ${plan.team_member_limit ?? '—'} seats`}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge
                                                    variant={
                                                        plan.active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {plan.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                {plan.public ? (
                                                    <Badge variant="outline">
                                                        Public
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Hidden
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {plan.enterprise ? (
                                                <span className="text-muted-foreground text-sm">
                                                    N/A
                                                </span>
                                            ) : plan.stripe_synced ? (
                                                <Badge variant="outline">
                                                    Synced
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Not synchronised
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>{plan.sort_order}</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                                data-test={`admin-plan-edit-${plan.key}`}
                                            >
                                                <Link
                                                    href={editPlan.url(plan.id)}
                                                    prefetch
                                                >
                                                    <Pencil className="size-3.5" />
                                                    {canManage
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {plans.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                No plans seeded yet. Run{' '}
                                <code className="text-xs">
                                    php artisan db:seed
                                    --class=BillingPlanSeeder
                                </code>
                                .
                            </p>
                        ) : null}
                        <p className="text-muted-foreground mt-4 text-xs">
                            <Link
                                href={subscriptionsIndex.url()}
                                className="underline-offset-2 hover:underline"
                            >
                                Back to subscriptions
                            </Link>
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
