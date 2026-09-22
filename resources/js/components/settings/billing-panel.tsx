import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CheckIcon,
    CreditCard,
    Download,
    ExternalLink,
    Info,
    Monitor,
    XIcon,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    billingStatusTone,
    formatBillingDate,
    intervalLabel,
    invoiceStatusTone,
} from '@/lib/billing';
import { ProductLabels, displayLabel } from '@/lib/product-labels';
import { cn } from '@/lib/utils';
import { screens as screensIndex } from '@/routes/app';
import billingRoutes from '@/routes/app/billing';
import type {
    BillingIndexProps,
    BillingInvoicePage,
    BillingInvoiceRow,
    MarketingComparisonRow,
    MarketingPlan,
} from '@/types/billing';

const CONNECTED_SCREEN_PREVIEW = 12;
const SALES_MAILTO = 'mailto:sales@dzsignage.com';

function ComparisonCell({ value }: { value: boolean | string }) {
    if (typeof value === 'boolean') {
        return value ? (
            <CheckIcon
                className="text-primary mx-auto size-4"
                aria-label="Included"
            />
        ) : (
            <XIcon
                className="text-muted-foreground/50 mx-auto size-4"
                aria-label="Not included"
            />
        );
    }

    return <span className="text-sm">{value}</span>;
}

export function BillingPanel({
    summary,
    plans,
    comparison,
    licence_limits: licenceLimits,
    unavailable_message: unavailableMessage,
    stripe_configured: stripeConfigured = true,
    invoices,
    permissions,
    checkout,
}: BillingIndexProps) {
    const pageErrors = usePage().props.errors as Record<string, string>;

    const canManage = permissions.can_manage;
    const stripeReady = stripeConfigured !== false;
    const subscribed =
        summary.has_subscription &&
        summary.status !== 'none' &&
        summary.status !== 'canceled';

    const [tab, setTab] = useState('overview');
    const [annual, setAnnual] = useState(true);
    const [selectedPlan, setSelectedPlan] = useState(
        summary.plan_key === 'business' ? 'business' : 'starter',
    );
    const [quantityOpen, setQuantityOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [busyAction, setBusyAction] = useState<string | null>(null);

    const quantityForm = useForm({
        quantity: summary.quantity ?? summary.licensed ?? licenceLimits.min,
    });

    const hiddenScreenCount = Math.max(
        0,
        summary.connected_screens.length - CONNECTED_SCREEN_PREVIEW,
    );

    const usagePercent =
        summary.licensed > 0
            ? Math.min(100, Math.round((summary.used / summary.licensed) * 100))
            : 0;

    function simplePost(url: string, action: string) {
        setBusyAction(action);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyAction(null),
            },
        );
    }

    function openQuantityDialog() {
        quantityForm.clearErrors();
        quantityForm.setData(
            'quantity',
            summary.quantity ?? summary.licensed ?? licenceLimits.min,
        );
        setQuantityOpen(true);
    }

    function startCheckout(planKey: string) {
        setSelectedPlan(planKey);
        setBusyAction('checkout');
        router.post(
            billingRoutes.checkout.url(),
            {
                plan: planKey,
                interval: annual ? 'yearly' : 'monthly',
            },
            {
                preserveScroll: true,
                onFinish: () => setBusyAction(null),
            },
        );
    }

    return (
        <>
            <div data-test="app-billing" className="flex flex-col gap-5">
                {checkout === 'success' ? (
                    <div
                        data-test="billing-checkout-success"
                        className="border-success/40 bg-success/10 flex items-start gap-3 rounded-xl border p-4 text-sm"
                    >
                        <CheckCircle2
                            className="text-success mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <p>
                            Checkout complete. Your plan appears here as soon as
                            Stripe confirms the payment.
                        </p>
                    </div>
                ) : null}

                {checkout === 'cancelled' ? (
                    <div
                        data-test="billing-checkout-cancelled"
                        className="border-border bg-muted/40 text-muted-foreground flex items-start gap-3 rounded-xl border p-4 text-sm"
                    >
                        <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <p>Checkout cancelled. Nothing was charged.</p>
                    </div>
                ) : null}

                {pageErrors.billing ? (
                    <div
                        data-test="billing-error"
                        role="alert"
                        className="border-destructive/40 bg-destructive/10 flex items-start gap-3 rounded-xl border p-4 text-sm"
                    >
                        <AlertTriangle
                            className="text-destructive mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <p>{pageErrors.billing}</p>
                    </div>
                ) : null}

                {unavailableMessage ? (
                    <div
                        data-test="billing-config-notice"
                        role="status"
                        className="border-border bg-muted/40 flex items-start gap-3 rounded-xl border p-4 text-sm"
                    >
                        <Info
                            className="text-muted-foreground mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <div>
                            <p className="font-medium">
                                Stripe actions unavailable
                            </p>
                            <p className="text-muted-foreground mt-1">
                                {unavailableMessage}
                            </p>
                        </div>
                    </div>
                ) : null}

                {summary.past_due ? (
                    <div
                        data-test="billing-past-due"
                        role="alert"
                        className="border-warning/40 bg-warning/10 flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="flex items-start gap-3 text-sm">
                            <AlertTriangle
                                className="text-warning mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            <p>
                                Your last payment failed. Update your payment
                                method to keep TVs playing live content.
                            </p>
                        </div>
                        {canManage ? (
                            <Button
                                type="button"
                                size="sm"
                                data-test="billing-past-due-portal"
                                disabled={busyAction !== null}
                                onClick={() =>
                                    simplePost(
                                        billingRoutes.portal.url(),
                                        'portal',
                                    )
                                }
                            >
                                {busyAction === 'portal' ? (
                                    <Spinner />
                                ) : (
                                    <CreditCard className="size-3.5" />
                                )}
                                Manage billing
                            </Button>
                        ) : null}
                    </div>
                ) : null}

                {summary.on_grace_period ? (
                    <div
                        data-test="billing-grace-period"
                        className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="flex items-start gap-3 text-sm">
                            <Info
                                className="text-muted-foreground mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            <p>
                                Your subscription is cancelled and ends on{' '}
                                <span className="font-medium">
                                    {formatBillingDate(summary.ends_at)}
                                </span>
                                . TVs keep playing until then.
                            </p>
                        </div>
                        {canManage ? (
                            <Button
                                type="button"
                                size="sm"
                                data-test="billing-resume"
                                disabled={busyAction !== null}
                                onClick={() =>
                                    simplePost(
                                        billingRoutes.resume.url(),
                                        'resume',
                                    )
                                }
                            >
                                {busyAction === 'resume' ? <Spinner /> : null}
                                Resume subscription
                            </Button>
                        ) : null}
                    </div>
                ) : null}

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList data-test="billing-tabs">
                        <TabsTrigger
                            value="overview"
                            data-test="billing-tab-overview"
                        >
                            Overview
                        </TabsTrigger>
                        <TabsTrigger
                            value="plans"
                            data-test="billing-tab-plans"
                        >
                            Plans
                        </TabsTrigger>
                        <TabsTrigger
                            value="invoices"
                            data-test="billing-tab-invoices"
                        >
                            Invoices
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-5">
                        <Card
                            className="shadow-none"
                            data-test="billing-subscription"
                        >
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="mb-1 flex flex-wrap items-center gap-2">
                                            <CardTitle className="font-display text-lg">
                                                {summary.plan_name ?? 'No plan'}
                                            </CardTitle>
                                            <StatusBadge
                                                label={summary.status_label}
                                                tone={billingStatusTone(
                                                    summary.status,
                                                )}
                                                data-test="billing-status"
                                            />
                                        </div>
                                        <CardDescription data-test="billing-interval">
                                            {subscribed
                                                ? `Billed ${(summary.interval_label ?? intervalLabel(summary.interval)).toLowerCase()}`
                                                : 'Choose a plan to connect TVs and invite your team.'}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <p className="text-muted-foreground text-xs">
                                            {ProductLabels.displayPlural}
                                        </p>
                                        <p
                                            className="mt-1 font-mono text-sm"
                                            data-test="billing-usage"
                                        >
                                            {summary.used} /{' '}
                                            {summary.licensed ||
                                                summary.screen_limit ||
                                                '—'}
                                        </p>
                                        {summary.licensed > 0 ? (
                                            <div
                                                className="bg-secondary mt-2 h-1.5 rounded-full"
                                                role="progressbar"
                                                aria-valuenow={summary.used}
                                                aria-valuemin={0}
                                                aria-valuemax={summary.licensed}
                                                aria-label={`${ProductLabels.licences} used`}
                                            >
                                                <div
                                                    className={cn(
                                                        'h-full rounded-full',
                                                        usagePercent >= 100
                                                            ? 'bg-warning'
                                                            : 'bg-primary',
                                                    )}
                                                    style={{
                                                        width: `${usagePercent}%`,
                                                    }}
                                                />
                                            </div>
                                        ) : null}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs">
                                            Team
                                        </p>
                                        <p
                                            className="mt-1 font-mono text-sm"
                                            data-test="billing-team-usage"
                                        >
                                            {summary.team_used ?? 0}
                                            {summary.team_limit != null
                                                ? ` / ${summary.team_limit}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs">
                                            Storage
                                        </p>
                                        <p
                                            className="mt-1 font-mono text-sm"
                                            data-test="billing-storage"
                                        >
                                            {summary.storage_gb != null
                                                ? `${summary.storage_gb} GB`
                                                : '—'}
                                        </p>
                                    </div>
                                </div>

                                {subscribed ? (
                                    <>
                                        <div>
                                            <p className="mb-2 text-sm font-medium">
                                                Connected{' '}
                                                {ProductLabels.displayPlural}
                                            </p>
                                            {summary.connected_screens
                                                .length === 0 ? (
                                                <p className="text-muted-foreground text-sm">
                                                    No TVs are connected yet.
                                                    Pairing a TV uses one
                                                    licence.
                                                </p>
                                            ) : (
                                                <ul
                                                    className="flex flex-wrap gap-1.5"
                                                    data-test="billing-connected-screens"
                                                >
                                                    {summary.connected_screens
                                                        .slice(
                                                            0,
                                                            CONNECTED_SCREEN_PREVIEW,
                                                        )
                                                        .map((screen) => (
                                                            <li key={screen.id}>
                                                                <Badge variant="secondary">
                                                                    <Monitor
                                                                        aria-hidden
                                                                    />
                                                                    {
                                                                        screen.name
                                                                    }
                                                                </Badge>
                                                            </li>
                                                        ))}
                                                    {hiddenScreenCount > 0 ? (
                                                        <li>
                                                            <Link
                                                                href={screensIndex.url()}
                                                                className="text-muted-foreground hover:text-foreground text-xs underline underline-offset-2"
                                                                data-test="billing-connected-screens-more"
                                                            >
                                                                +
                                                                {
                                                                    hiddenScreenCount
                                                                }{' '}
                                                                more on{' '}
                                                                {
                                                                    ProductLabels.pairedNav
                                                                }
                                                            </Link>
                                                        </li>
                                                    ) : null}
                                                </ul>
                                            )}
                                        </div>

                                        {canManage ? (
                                            <div className="border-border flex flex-wrap gap-2 border-t pt-5">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="billing-change-quantity"
                                                    onClick={openQuantityDialog}
                                                >
                                                    Change licence quantity
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="billing-portal"
                                                    disabled={
                                                        busyAction !== null ||
                                                        !stripeReady
                                                    }
                                                    onClick={() =>
                                                        simplePost(
                                                            billingRoutes.portal.url(),
                                                            'portal',
                                                        )
                                                    }
                                                >
                                                    {busyAction === 'portal' ? (
                                                        <Spinner />
                                                    ) : (
                                                        <CreditCard className="size-3.5" />
                                                    )}
                                                    Manage billing
                                                </Button>
                                                {summary.on_grace_period ? null : (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-muted-foreground hover:text-destructive sm:ml-auto"
                                                        data-test="billing-cancel"
                                                        onClick={() =>
                                                            setCancelOpen(true)
                                                        }
                                                    >
                                                        Cancel subscription
                                                    </Button>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="border-border text-muted-foreground border-t pt-5 text-sm">
                                                Only a Workspace Owner can
                                                change licences or payment
                                                details.
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <div className="border-border flex flex-wrap gap-2 border-t pt-5">
                                        <Button
                                            type="button"
                                            size="sm"
                                            data-test="billing-choose-plan"
                                            onClick={() => setTab('plans')}
                                        >
                                            View plans
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="plans" className="space-y-5">
                        <div className="flex justify-center">
                            <div
                                className="border-border bg-muted/60 flex w-fit gap-1 rounded-full border p-1"
                                role="group"
                                aria-label="Billing interval"
                            >
                                <button
                                    type="button"
                                    data-test="billing-interval-monthly"
                                    aria-pressed={!annual}
                                    onClick={() => setAnnual(false)}
                                    className={cn(
                                        'rounded-full px-4 py-1.5 font-mono text-[10px] font-bold tracking-widest uppercase transition-colors',
                                        !annual
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    Monthly
                                </button>
                                <button
                                    type="button"
                                    data-test="billing-interval-yearly"
                                    aria-pressed={annual}
                                    onClick={() => setAnnual(true)}
                                    className={cn(
                                        'rounded-full px-4 py-1.5 font-mono text-[10px] font-bold tracking-widest uppercase transition-colors',
                                        annual
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    Annual
                                </button>
                            </div>
                        </div>

                        <div
                            className="grid gap-4 lg:grid-cols-3"
                            data-test="billing-plan-cards"
                        >
                            {plans.map((plan) => (
                                <PlanCard
                                    key={plan.key}
                                    plan={plan}
                                    annual={annual}
                                    selected={selectedPlan === plan.key}
                                    current={summary.plan_key === plan.key}
                                    subscribed={subscribed}
                                    canManage={canManage}
                                    stripeReady={stripeReady}
                                    processing={busyAction === 'checkout'}
                                    onSelect={() => setSelectedPlan(plan.key)}
                                    onCheckout={() => startCheckout(plan.key)}
                                />
                            ))}
                        </div>

                        {pageErrors.plan || pageErrors.interval ? (
                            <p
                                className="text-destructive text-sm"
                                data-test="billing-checkout-error"
                            >
                                {pageErrors.plan ?? pageErrors.interval}
                            </p>
                        ) : null}

                        {!stripeReady ? (
                            <p
                                className="text-muted-foreground text-xs"
                                data-test="billing-checkout-disabled-hint"
                            >
                                Stripe test billing is not configured for this
                                environment.
                            </p>
                        ) : null}

                        {comparison.length > 0 ? (
                            <Card
                                className="shadow-none"
                                data-test="billing-comparison"
                            >
                                <CardHeader>
                                    <CardTitle className="font-display text-lg">
                                        Compare plans
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="overflow-x-auto">
                                    <ComparisonTable comparison={comparison} />
                                </CardContent>
                            </Card>
                        ) : null}
                    </TabsContent>

                    <TabsContent value="invoices">
                        <InvoicesCard invoices={invoices} />
                    </TabsContent>
                </Tabs>
            </div>

            <Dialog open={quantityOpen} onOpenChange={setQuantityOpen}>
                <DialogContent
                    className="sm:max-w-md"
                    data-test="billing-quantity-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>
                            Change {ProductLabels.licences}
                        </DialogTitle>
                        <DialogDescription>
                            Your subscription will change from{' '}
                            {summary.quantity ?? summary.licensed} to{' '}
                            {quantityForm.data.quantity}{' '}
                            {ProductLabels.licences}. Proration may apply on
                            your next Stripe invoice.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-1.5">
                        <Label htmlFor="billing-new-quantity">
                            {ProductLabels.licences}
                        </Label>
                        <Input
                            id="billing-new-quantity"
                            data-test="billing-quantity-input"
                            type="number"
                            inputMode="numeric"
                            min={licenceLimits.min}
                            max={licenceLimits.max}
                            value={quantityForm.data.quantity}
                            onChange={(event) =>
                                quantityForm.setData(
                                    'quantity',
                                    Number(event.target.value),
                                )
                            }
                            aria-describedby="billing-new-quantity-hint"
                        />
                        <p
                            id="billing-new-quantity-hint"
                            className="text-muted-foreground text-xs"
                        >
                            {summary.used} {displayLabel(summary.used)}
                            {summary.used === 1 ? ' is' : ' are'} connected, so
                            licences cannot drop below {summary.used}.
                        </p>
                        {quantityForm.errors.quantity ? (
                            <p
                                className="text-destructive text-xs"
                                data-test="billing-quantity-dialog-error"
                            >
                                {quantityForm.errors.quantity}
                            </p>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setQuantityOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="billing-quantity-confirm"
                            disabled={quantityForm.processing}
                            onClick={() =>
                                quantityForm.patch(
                                    billingRoutes.quantity.url(),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setQuantityOpen(false),
                                    },
                                )
                            }
                        >
                            {quantityForm.processing ? <Spinner /> : null}
                            Confirm change
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                <DialogContent
                    className="sm:max-w-md"
                    data-test="billing-cancel-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>Cancel subscription?</DialogTitle>
                        <DialogDescription>
                            {summary.ends_at
                                ? `Your ${ProductLabels.licences} stay active until ${formatBillingDate(summary.ends_at)}. TVs keep playing until then, and you can resume any time before that date.`
                                : `Your ${ProductLabels.licences} stay active until the end of the current billing period. TVs keep playing until then, and you can resume any time before the period ends.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCancelOpen(false)}
                        >
                            Keep subscription
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="billing-cancel-confirm"
                            disabled={busyAction !== null}
                            onClick={() => {
                                setCancelOpen(false);
                                simplePost(
                                    billingRoutes.cancel.url(),
                                    'cancel',
                                );
                            }}
                        >
                            Cancel subscription
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function PlanCard({
    plan,
    annual,
    selected,
    current,
    subscribed,
    canManage,
    stripeReady,
    processing,
    onSelect,
    onCheckout,
}: {
    plan: MarketingPlan;
    annual: boolean;
    selected: boolean;
    current: boolean;
    subscribed: boolean;
    canManage: boolean;
    stripeReady: boolean;
    processing: boolean;
    onSelect: () => void;
    onCheckout: () => void;
}) {
    const showAnnual = annual && !plan.enterprise;
    const priceLabel = plan.enterprise
        ? 'Custom'
        : showAnnual
          ? (plan.yearly_monthly_equivalent?.formatted ??
            plan.monthly?.formatted ??
            '—')
          : (plan.monthly?.formatted ?? '—');
    const priceHint = plan.enterprise
        ? 'Contact sales'
        : showAnnual
          ? 'per month, billed annually'
          : 'per month';

    return (
        <div
            data-test={`billing-plan-${plan.key}`}
            className={cn(
                'border-border relative flex flex-col rounded-xl border p-5',
                plan.popular && 'border-primary',
                selected && !plan.enterprise && 'ring-primary/30 ring-2',
            )}
        >
            {plan.popular ? (
                <Badge className="absolute -top-2.5 left-1/2 -translate-x-1/2">
                    Most Popular
                </Badge>
            ) : null}
            {current ? (
                <Badge
                    variant="secondary"
                    className="absolute -top-2.5 right-3"
                    data-test="billing-plan-current"
                >
                    Current
                </Badge>
            ) : null}

            <button
                type="button"
                className="text-left"
                onClick={onSelect}
                disabled={plan.enterprise}
            >
                <h3 className="font-display text-lg font-semibold">
                    {plan.name}
                </h3>
                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                    {plan.tagline}
                </p>
                <p className="font-display mt-4 text-3xl font-semibold tracking-tight">
                    {priceLabel}
                </p>
                <p className="text-muted-foreground mt-1 font-mono text-[10px] tracking-widest uppercase">
                    {priceHint}
                </p>
            </button>

            <ul className="mt-4 flex flex-1 flex-col gap-2">
                {plan.feature_labels.slice(0, 6).map((label) => (
                    <li key={label} className="flex gap-2 text-sm">
                        <CheckIcon className="text-primary mt-0.5 size-3.5 shrink-0" />
                        <span>{label}</span>
                    </li>
                ))}
            </ul>

            <div className="mt-5">
                {plan.enterprise ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        data-test={`billing-plan-${plan.key}-cta`}
                        asChild
                    >
                        <a href={SALES_MAILTO}>Contact Sales</a>
                    </Button>
                ) : subscribed && current ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        disabled
                    >
                        Current plan
                    </Button>
                ) : (
                    <Button
                        type="button"
                        className="w-full"
                        data-test={`billing-plan-${plan.key}-checkout`}
                        disabled={
                            !canManage ||
                            !stripeReady ||
                            processing ||
                            subscribed
                        }
                        onClick={onCheckout}
                    >
                        {processing ? <Spinner /> : null}
                        {subscribed ? 'Contact support to change' : 'Checkout'}
                    </Button>
                )}
            </div>
        </div>
    );
}

function ComparisonTable({
    comparison,
}: {
    comparison: MarketingComparisonRow[];
}) {
    return (
        <table className="w-full min-w-[560px] text-left">
            <thead>
                <tr className="border-border border-b">
                    <th className="text-muted-foreground px-2 py-2 font-mono text-[10px] tracking-widest uppercase">
                        Feature
                    </th>
                    <th className="px-2 py-2 text-center text-sm font-semibold">
                        Starter
                    </th>
                    <th className="px-2 py-2 text-center text-sm font-semibold">
                        Business
                    </th>
                    <th className="px-2 py-2 text-center text-sm font-semibold">
                        Enterprise
                    </th>
                </tr>
            </thead>
            <tbody>
                {comparison.map((row) => (
                    <tr key={row.feature} className="border-border/60 border-b">
                        <td className="px-2 py-2.5 text-sm font-medium">
                            {row.feature}
                        </td>
                        <td className="px-2 py-2.5 text-center">
                            <ComparisonCell value={row.starter} />
                        </td>
                        <td className="px-2 py-2.5 text-center">
                            <ComparisonCell value={row.business} />
                        </td>
                        <td className="px-2 py-2.5 text-center">
                            <ComparisonCell value={row.enterprise} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function InvoicesCard({ invoices }: { invoices: BillingInvoicePage }) {
    const rows = invoices.data;

    return (
        <Card className="shadow-none" data-test="billing-invoices">
            <CardHeader>
                <CardTitle className="font-display text-lg">Invoices</CardTitle>
                <CardDescription>
                    {invoices.meta.total === 0
                        ? 'Stripe invoices appear here once your first payment is processed.'
                        : `${invoices.meta.total} invoice${invoices.meta.total === 1 ? '' : 's'} mirrored from Stripe.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {rows.length === 0 ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="billing-invoices-empty"
                    >
                        No invoices yet.
                    </p>
                ) : (
                    <>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Open
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((invoice) => (
                                        <TableRow
                                            key={invoice.id}
                                            data-test={`billing-invoice-row-${invoice.id}`}
                                        >
                                            <TableCell className="font-mono text-sm">
                                                {invoice.number ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatBillingDate(
                                                    invoice.billed_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm font-medium">
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
                                                <InvoiceLinks
                                                    invoice={invoice}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {rows.map((invoice) => (
                                <div
                                    key={invoice.id}
                                    data-test={`billing-invoice-card-${invoice.id}`}
                                    className="border-border space-y-2 rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-mono text-sm">
                                                {invoice.number ?? '—'}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {formatBillingDate(
                                                    invoice.billed_at,
                                                )}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            label={invoice.status}
                                            tone={invoiceStatusTone(
                                                invoice.status,
                                            )}
                                            className="capitalize"
                                        />
                                    </div>
                                    <p className="font-mono text-sm font-medium">
                                        {invoice.total_formatted}
                                    </p>
                                    <InvoiceLinks invoice={invoice} />
                                </div>
                            ))}
                        </div>
                    </>
                )}

                {invoices.links.prev || invoices.links.next ? (
                    <div className="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!invoices.links.prev}
                            onClick={() => {
                                if (invoices.links.prev) {
                                    router.get(
                                        invoices.links.prev,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            Previous
                        </Button>
                        <span className="text-muted-foreground text-xs">
                            Page {invoices.meta.current_page} of{' '}
                            {invoices.meta.last_page}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!invoices.links.next}
                            onClick={() => {
                                if (invoices.links.next) {
                                    router.get(
                                        invoices.links.next,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            Next
                        </Button>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function InvoiceLinks({ invoice }: { invoice: BillingInvoiceRow }) {
    if (!invoice.hosted_invoice_url && !invoice.invoice_pdf) {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5 md:justify-end">
            {invoice.hosted_invoice_url ? (
                <Button size="sm" variant="ghost" asChild>
                    <a
                        href={invoice.hosted_invoice_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-test={`billing-invoice-view-${invoice.id}`}
                        aria-label={`View invoice ${invoice.number ?? invoice.id} on Stripe`}
                    >
                        <ExternalLink className="size-3.5" />
                        View
                    </a>
                </Button>
            ) : null}
            {invoice.invoice_pdf ? (
                <Button size="sm" variant="ghost" asChild>
                    <a
                        href={invoice.invoice_pdf}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-test={`billing-invoice-pdf-${invoice.id}`}
                        aria-label={`Download PDF for invoice ${invoice.number ?? invoice.id}`}
                    >
                        <Download className="size-3.5" />
                        PDF
                    </a>
                </Button>
            ) : null}
        </div>
    );
}
