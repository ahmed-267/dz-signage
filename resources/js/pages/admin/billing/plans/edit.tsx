import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { cn } from '@/lib/utils';
import { plans as plansIndex } from '@/routes/admin/subscriptions';
import {
    migrate_subscribers as migrateSubscribers,
    sync_stripe_price as syncStripePrice,
    update as updatePlan,
} from '@/routes/admin/subscriptions/plans';

type FeatureItem = { key: string; label: string };

type AdminPlan = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    currency: string;
    monthly_amount: number | null;
    annual_amount: number | null;
    yearly_monthly_equivalent: number | null;
    monthly_stripe_price_id: string | null;
    annual_stripe_price_id: string | null;
    screen_limit: number | null;
    storage_gb: number | null;
    team_member_limit: number | null;
    features: string[];
    feature_labels: string[];
    badge: string | null;
    popular: boolean;
    active: boolean;
    public: boolean;
    sort_order: number;
    enterprise: boolean;
    cta: string;
    stripe_synced: boolean;
    legacy_price_count: number;
};

type Props = {
    plan: AdminPlan;
    feature_catalog?: FeatureItem[];
    can_manage?: boolean;
    stripe_configured?: boolean;
    eligible_subscriber_count?: number;
};

type FormData = {
    name: string;
    description: string;
    currency: string;
    monthly_amount: number | '';
    annual_amount: number | '';
    yearly_monthly_equivalent: number | '';
    monthly_stripe_price_id: string;
    annual_stripe_price_id: string;
    screen_limit: number | '';
    storage_gb: number | '';
    team_member_limit: number | '';
    features: string[];
    feature_labels: string;
    badge: string;
    popular: boolean;
    active: boolean;
    public: boolean;
    sort_order: number;
    cta: string;
    sync_stripe_prices: boolean;
    confirm: boolean;
};

function nullableNumber(value: number | null | undefined): number | '' {
    return value == null ? '' : value;
}

export default function AdminBillingPlanEdit({
    plan,
    feature_catalog: featureCatalog = [],
    can_manage: canManage = false,
    stripe_configured: stripeConfigured = false,
    eligible_subscriber_count: eligibleCount = 0,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };

    const form = useForm<FormData>({
        name: plan.name,
        description: plan.description ?? '',
        currency: plan.currency.toLowerCase(),
        monthly_amount: nullableNumber(plan.monthly_amount),
        annual_amount: nullableNumber(plan.annual_amount),
        yearly_monthly_equivalent: nullableNumber(
            plan.yearly_monthly_equivalent,
        ),
        monthly_stripe_price_id: plan.monthly_stripe_price_id ?? '',
        annual_stripe_price_id: plan.annual_stripe_price_id ?? '',
        screen_limit: nullableNumber(plan.screen_limit),
        storage_gb: nullableNumber(plan.storage_gb),
        team_member_limit: nullableNumber(plan.team_member_limit),
        features: [...plan.features],
        feature_labels: plan.feature_labels.join('\n'),
        badge: plan.badge ?? '',
        popular: plan.popular,
        active: plan.active,
        public: plan.public,
        sort_order: plan.sort_order,
        cta: plan.cta,
        sync_stripe_prices: false,
        confirm: false,
    });

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [migrateOpen, setMigrateOpen] = useState(false);
    const [syncOpen, setSyncOpen] = useState(false);

    const diffs = useMemo(() => {
        const rows: { label: string; from: string; to: string }[] = [];
        const push = (label: string, from: unknown, to: unknown) => {
            const stringify = (value: unknown): string => {
                if (value === null || value === undefined) {
                    return '';
                }
                if (
                    typeof value === 'string' ||
                    typeof value === 'number' ||
                    typeof value === 'boolean'
                ) {
                    return String(value);
                }
                return JSON.stringify(value);
            };
            const a = stringify(from);
            const b = stringify(to);
            if (a !== b) {
                rows.push({ label, from: a || '—', to: b || '—' });
            }
        };

        push('Name', plan.name, form.data.name);
        push('Description', plan.description, form.data.description);
        push('Currency', plan.currency.toLowerCase(), form.data.currency);
        push('Monthly amount', plan.monthly_amount, form.data.monthly_amount);
        push('Annual amount', plan.annual_amount, form.data.annual_amount);
        push(
            'Yearly monthly eq',
            plan.yearly_monthly_equivalent,
            form.data.yearly_monthly_equivalent,
        );
        push(
            'Monthly Stripe Price',
            plan.monthly_stripe_price_id,
            form.data.monthly_stripe_price_id,
        );
        push(
            'Annual Stripe Price',
            plan.annual_stripe_price_id,
            form.data.annual_stripe_price_id,
        );
        push('Screen limit', plan.screen_limit, form.data.screen_limit);
        push('Storage GB', plan.storage_gb, form.data.storage_gb);
        push('Team seats', plan.team_member_limit, form.data.team_member_limit);
        push(
            'Features',
            plan.features.join(', '),
            form.data.features.join(', '),
        );
        push(
            'Feature labels',
            plan.feature_labels.join(' | '),
            form.data.feature_labels
                .split('\n')
                .map((l) => l.trim())
                .filter(Boolean)
                .join(' | '),
        );
        push('Badge', plan.badge, form.data.badge);
        push('Popular', plan.popular, form.data.popular);
        push('Active', plan.active, form.data.active);
        push('Public', plan.public, form.data.public);
        push('Sort order', plan.sort_order, form.data.sort_order);
        push('CTA', plan.cta, form.data.cta);

        return rows;
    }, [form.data, plan]);

    function toggleFeature(key: string, checked: boolean) {
        const next = checked
            ? [...form.data.features, key]
            : form.data.features.filter((f) => f !== key);
        form.setData('features', Array.from(new Set(next)));
    }

    function openConfirm(event: FormEvent) {
        event.preventDefault();
        if (!canManage) {
            return;
        }
        setConfirmOpen(true);
    }

    function submitConfirmed() {
        form.transform((data) => ({
            ...data,
            monthly_amount:
                data.monthly_amount === '' ? null : Number(data.monthly_amount),
            annual_amount:
                data.annual_amount === '' ? null : Number(data.annual_amount),
            yearly_monthly_equivalent:
                data.yearly_monthly_equivalent === ''
                    ? null
                    : Number(data.yearly_monthly_equivalent),
            screen_limit:
                data.screen_limit === '' ? null : Number(data.screen_limit),
            storage_gb: data.storage_gb === '' ? null : Number(data.storage_gb),
            team_member_limit:
                data.team_member_limit === ''
                    ? null
                    : Number(data.team_member_limit),
            monthly_stripe_price_id: data.monthly_stripe_price_id || null,
            annual_stripe_price_id: data.annual_stripe_price_id || null,
            badge: data.badge || null,
            description: data.description || null,
            feature_labels: data.feature_labels
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
            confirm: true,
        }));

        form.put(updatePlan.url(plan.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
            onFinish: () => form.setData('confirm', false),
        });
    }

    function runSync(force = false) {
        router.post(
            syncStripePrice.url(plan.id),
            { confirm: true, force },
            {
                preserveScroll: true,
                onFinish: () => setSyncOpen(false),
            },
        );
    }

    function runMigrate() {
        router.post(
            migrateSubscribers.url(plan.id),
            { confirm: true, interval: 'both' },
            {
                preserveScroll: true,
                onFinish: () => setMigrateOpen(false),
            },
        );
    }

    const readOnly = !canManage;

    return (
        <>
            <Head title={`Plan · ${plan.name}`} />
            <div
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
                data-test="admin-billing-plan-edit"
            >
                <AdminPageHeader
                    title={plan.name}
                    description={
                        plan.enterprise
                            ? 'Enterprise is custom — pricing stays contact-sales.'
                            : 'Edit identity, pricing, limits, and feature checklist.'
                    }
                />

                <p className="text-muted-foreground text-sm">
                    <Link
                        href={plansIndex.url()}
                        className="underline-offset-2 hover:underline"
                    >
                        ← All plans
                    </Link>
                </p>

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

                <form className="space-y-6" onSubmit={openConfirm}>
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Identity
                            </CardTitle>
                            <CardDescription>
                                Key <code className="text-xs">{plan.key}</code>{' '}
                                is immutable.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="cta">CTA label</Label>
                                <Input
                                    id="cta"
                                    value={form.data.cta}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        form.setData('cta', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="description">
                                    Description / tagline
                                </Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="badge">Badge</Label>
                                <Input
                                    id="badge"
                                    value={form.data.badge}
                                    disabled={readOnly}
                                    placeholder="Most popular"
                                    onChange={(e) =>
                                        form.setData('badge', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="sort_order">Sort order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    value={form.data.sort_order}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        form.setData(
                                            'sort_order',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            <div className="flex flex-wrap gap-6 md:col-span-2">
                                {(
                                    [
                                        ['active', 'Active'],
                                        ['public', 'Public'],
                                        ['popular', 'Popular'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <label
                                        key={key}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data[key]}
                                            disabled={readOnly}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {!plan.enterprise ? (
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardTitle className="font-display text-lg">
                                        Pricing
                                    </CardTitle>
                                    {plan.stripe_synced ? (
                                        <Badge variant="outline">
                                            Stripe synced
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Stripe price not synchronised
                                        </Badge>
                                    )}
                                </div>
                                <CardDescription>
                                    Amounts are minor units (pence). Changing
                                    amounts creates new Stripe Prices — existing
                                    subscriptions stay on the old Price until
                                    migrated.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input
                                        id="currency"
                                        value={form.data.currency}
                                        disabled={readOnly}
                                        maxLength={3}
                                        onChange={(e) =>
                                            form.setData(
                                                'currency',
                                                e.target.value.toLowerCase(),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="monthly_amount">
                                        Monthly amount
                                    </Label>
                                    <Input
                                        id="monthly_amount"
                                        type="number"
                                        value={form.data.monthly_amount}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'monthly_amount',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="annual_amount">
                                        Annual amount
                                    </Label>
                                    <Input
                                        id="annual_amount"
                                        type="number"
                                        value={form.data.annual_amount}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'annual_amount',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="yearly_monthly_equivalent">
                                        Yearly monthly equivalent
                                    </Label>
                                    <Input
                                        id="yearly_monthly_equivalent"
                                        type="number"
                                        value={
                                            form.data.yearly_monthly_equivalent
                                        }
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'yearly_monthly_equivalent',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="monthly_stripe_price_id">
                                        Monthly Stripe Price ID
                                    </Label>
                                    <Input
                                        id="monthly_stripe_price_id"
                                        value={
                                            form.data.monthly_stripe_price_id
                                        }
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'monthly_stripe_price_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="annual_stripe_price_id">
                                        Annual Stripe Price ID
                                    </Label>
                                    <Input
                                        id="annual_stripe_price_id"
                                        value={form.data.annual_stripe_price_id}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'annual_stripe_price_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                {canManage ? (
                                    <div className="flex flex-wrap gap-2 md:col-span-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={!stripeConfigured}
                                            onClick={() => setSyncOpen(true)}
                                        >
                                            Sync Stripe Prices
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={
                                                !stripeConfigured ||
                                                eligibleCount === 0
                                            }
                                            onClick={() => setMigrateOpen(true)}
                                        >
                                            Apply new pricing to existing
                                            subscriptions ({eligibleCount})
                                        </Button>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>
                    ) : null}

                    {!plan.enterprise ? (
                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="font-display text-lg">
                                    Limits
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="screen_limit">
                                        Screen limit
                                    </Label>
                                    <Input
                                        id="screen_limit"
                                        type="number"
                                        data-test="admin-plan-screen-limit"
                                        value={form.data.screen_limit}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'screen_limit',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="storage_gb">
                                        Storage (GB)
                                    </Label>
                                    <Input
                                        id="storage_gb"
                                        type="number"
                                        value={form.data.storage_gb}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'storage_gb',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="team_member_limit">
                                        Team member limit
                                    </Label>
                                    <Input
                                        id="team_member_limit"
                                        type="number"
                                        value={form.data.team_member_limit}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            form.setData(
                                                'team_member_limit',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="font-display text-lg">
                                Features
                            </CardTitle>
                            <CardDescription>
                                Checklist keys gate product capabilities when
                                billing enforcement is on.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {featureCatalog.map((feature) => (
                                    <label
                                        key={feature.key}
                                        className={cn(
                                            'flex items-start gap-2 rounded-md border p-3 text-sm',
                                            form.data.features.includes(
                                                feature.key,
                                            ) &&
                                                'border-primary/40 bg-muted/40',
                                        )}
                                    >
                                        <Checkbox
                                            checked={form.data.features.includes(
                                                feature.key,
                                            )}
                                            disabled={readOnly}
                                            onCheckedChange={(checked) =>
                                                toggleFeature(
                                                    feature.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span>
                                            <span className="font-medium">
                                                {feature.label}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {feature.key}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="feature_labels">
                                    Marketing feature labels (one per line)
                                </Label>
                                <textarea
                                    id="feature_labels"
                                    className="border-input min-h-32 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none"
                                    value={form.data.feature_labels}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        form.setData(
                                            'feature_labels',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {canManage ? (
                        <div className="flex justify-end gap-2">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="admin-plan-save"
                            >
                                Review & save
                            </Button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Read-only for your role.
                        </p>
                    )}
                </form>

                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirm plan changes</DialogTitle>
                            <DialogDescription>
                                Review the diff before saving. Amount changes
                                will create new Stripe Prices when Stripe is
                                configured; subscribers are not migrated
                                automatically.
                            </DialogDescription>
                        </DialogHeader>
                        {diffs.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No changes detected.
                            </p>
                        ) : (
                            <ul className="max-h-64 space-y-2 overflow-y-auto text-sm">
                                {diffs.map((diff) => (
                                    <li
                                        key={diff.label}
                                        className="rounded-md border p-2"
                                    >
                                        <p className="font-medium">
                                            {diff.label}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {diff.from} → {diff.to}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConfirmOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                disabled={form.processing || diffs.length === 0}
                                onClick={submitConfirmed}
                                data-test="admin-plan-confirm-save"
                            >
                                Save changes
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={syncOpen} onOpenChange={setSyncOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Sync Stripe Prices</DialogTitle>
                            <DialogDescription>
                                Creates new Prices on the Stripe Product when
                                amounts differ. Existing subscriptions keep
                                their current Price.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSyncOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                onClick={() => runSync(false)}
                            >
                                Sync
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={migrateOpen} onOpenChange={setMigrateOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Migrate existing subscribers
                            </DialogTitle>
                            <DialogDescription>
                                This will swap approximately {eligibleCount}{' '}
                                subscription(s) onto the plan&apos;s current
                                Stripe Prices via Cashier. This cannot be undone
                                from this screen.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setMigrateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={runMigrate}
                            >
                                Migrate {eligibleCount} subscribers
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
