<?php

namespace App\Support\Billing;

use App\Models\Screen;
use App\Models\Workspace;
use App\Models\WorkspaceBillingOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Throwable;

/**
 * Authoritative Workspace billing entitlement and Screen licence rules.
 *
 * Purchased licences = Stripe subscription quantity.
 * Used licences = Screens with a non-revoked ScreenDevice (Connected).
 * Online/Offline and Active/Inactive do not affect used licences.
 */
final class BillingEntitlement
{
    public const SUBSCRIPTION_TYPE = 'default';

    public static function enforce(): bool
    {
        return (bool) config('billing.enforce', false);
    }

    /**
     * True when Stripe can checkout, or when plan catalog amounts exist for display.
     */
    public static function isConfigured(): bool
    {
        if (self::stripeReady()) {
            return true;
        }

        foreach (BillingPlanCatalog::plans() as $plan) {
            if ($plan['enterprise'] ?? false) {
                continue;
            }

            if (($plan['monthly_amount'] ?? 0) > 0 || ($plan['yearly_amount'] ?? 0) > 0) {
                return true;
            }
        }

        $legacyMonthly = (int) config('billing.catalog.monthly_amount', 0);
        $legacyYearly = (int) config('billing.catalog.yearly_amount', 0);

        return $legacyMonthly > 0 || $legacyYearly > 0;
    }

    /**
     * Stripe Checkout can run (secret + at least one non-enterprise price ID).
     */
    public static function stripeReady(): bool
    {
        if (! filled(config('cashier.secret'))) {
            return false;
        }

        foreach (BillingPlanCatalog::plans() as $plan) {
            if ($plan['enterprise'] ?? false) {
                continue;
            }

            if (filled($plan['prices']['monthly'] ?? null) || filled($plan['prices']['yearly'] ?? null)) {
                return true;
            }
        }

        return filled(config('billing.prices.monthly')) || filled(config('billing.prices.yearly'));
    }

    /**
     * Legacy helper — maps to the Starter plan price for the interval.
     */
    public static function priceId(string $interval): ?string
    {
        return self::priceIdForPlan(BillingPlanCatalog::defaultPlanKey(), $interval)
            ?? match ($interval) {
                'monthly' => config('billing.prices.monthly') ?: null,
                'yearly' => config('billing.prices.yearly') ?: null,
                default => null,
            };
    }

    public static function priceIdForPlan(string $plan, string $interval): ?string
    {
        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            return null;
        }

        $row = BillingPlanCatalog::plan($plan);

        if ($row === null || ($row['enterprise'] ?? false)) {
            return null;
        }

        $priceId = $row['prices'][$interval] ?? null;

        if (filled($priceId)) {
            return (string) $priceId;
        }

        // Legacy env fallback for Starter only ($interval already monthly|yearly)
        if ($plan === 'starter') {
            return $interval === 'monthly'
                ? (config('billing.prices.monthly') ?: null)
                : (config('billing.prices.yearly') ?: null);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function allowedIntervals(): array
    {
        $intervals = [];

        foreach (['monthly', 'yearly'] as $interval) {
            foreach (BillingPlanCatalog::plans() as $plan) {
                if ($plan['enterprise'] ?? false) {
                    continue;
                }

                if (filled(self::priceIdForPlan($plan['key'], $interval))) {
                    $intervals[] = $interval;
                    break;
                }
            }
        }

        if ($intervals === []) {
            if (filled(config('billing.prices.monthly'))) {
                $intervals[] = 'monthly';
            }
            if (filled(config('billing.prices.yearly'))) {
                $intervals[] = 'yearly';
            }
        }

        return $intervals;
    }

    public static function minLicenses(): int
    {
        return max(1, (int) config('billing.min_screen_licenses', 1));
    }

    public static function maxLicenses(): int
    {
        return max(self::minLicenses(), (int) config('billing.max_screen_licenses', 500));
    }

    public static function subscription(Workspace $workspace): ?Subscription
    {
        return $workspace->subscription(self::subscriptionType());
    }

    public static function subscriptionType(): string
    {
        return (string) config('billing.subscription_type', self::SUBSCRIPTION_TYPE);
    }

    public static function currentPlanKey(Workspace $workspace): ?string
    {
        $subscription = self::subscription($workspace);

        if ($subscription === null || (! $subscription->valid() && ! $subscription->pastDue())) {
            return null;
        }

        return BillingPlanCatalog::planKeyForPriceId($subscription->stripe_price);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function currentPlan(Workspace $workspace): ?array
    {
        $key = self::currentPlanKey($workspace);

        return $key !== null ? BillingPlanCatalog::plan($key) : null;
    }

    /**
     * Licensed Stripe quantity when subscribed; otherwise the plan screen_limit or 0.
     */
    public static function screenLimit(Workspace $workspace): int
    {
        if (self::hasActiveSubscription($workspace)) {
            return self::licensedScreenCount($workspace);
        }

        $plan = self::currentPlan($workspace);

        return (int) ($plan['screen_limit'] ?? 0);
    }

    public static function storageLimitBytes(Workspace $workspace): ?int
    {
        $override = self::override($workspace);
        if ($override?->storage_gb !== null) {
            return (int) $override->storage_gb * 1024 * 1024 * 1024;
        }

        $plan = self::currentPlan($workspace);
        $gb = $plan['storage_gb'] ?? null;

        if ($gb === null) {
            return null;
        }

        return (int) $gb * 1024 * 1024 * 1024;
    }

    public static function teamLimit(Workspace $workspace): ?int
    {
        $override = self::override($workspace);
        if ($override?->team_limit !== null) {
            return (int) $override->team_limit;
        }

        $plan = self::currentPlan($workspace);

        if ($plan === null) {
            // Unsubscribed workspaces still need a ceiling when enforcement is on.
            $default = BillingPlanCatalog::plan(BillingPlanCatalog::defaultPlanKey());

            return $default['team_limit'] ?? null;
        }

        return $plan['team_limit'] ?? null;
    }

    /**
     * Members + pending invitations.
     */
    public static function teamUsage(Workspace $workspace): int
    {
        $members = $workspace->members()->count();
        $pending = $workspace->invitations()->pending()->count();

        return $members + $pending;
    }

    public static function hasFeature(Workspace $workspace, string $feature): bool
    {
        if (! self::enforce()) {
            return true;
        }

        $override = self::override($workspace);
        if ($override !== null && is_array($override->features) && $override->features !== []) {
            return BillingFeatureCatalog::planHas(array_values($override->features), $feature);
        }

        $plan = self::currentPlan($workspace);

        if ($plan === null) {
            return false;
        }

        if ($plan['enterprise'] ?? false) {
            return true;
        }

        return BillingFeatureCatalog::planHas($plan['features'] ?? [], $feature);
    }

    public static function override(Workspace $workspace): ?WorkspaceBillingOverride
    {
        $workspace->loadMissing('billingOverride');

        return $workspace->billingOverride;
    }

    /**
     * Workspace may operate live signage / pair within licences when entitled.
     */
    public static function hasEntitlement(Workspace $workspace): bool
    {
        if (! self::enforce()) {
            return true;
        }

        $subscription = self::subscription($workspace);

        if ($subscription === null) {
            return false;
        }

        return $subscription->valid() || $subscription->pastDue();
    }

    public static function hasActiveSubscription(Workspace $workspace): bool
    {
        $subscription = self::subscription($workspace);

        return $subscription !== null && ($subscription->valid() || $subscription->pastDue());
    }

    public static function status(Workspace $workspace): string
    {
        $subscription = self::subscription($workspace);

        if ($subscription === null) {
            return 'none';
        }

        if ($subscription->onGracePeriod()) {
            return 'canceling';
        }

        if ($subscription->canceled()) {
            return 'canceled';
        }

        if ($subscription->pastDue()) {
            return 'past_due';
        }

        if ($subscription->incomplete()) {
            return 'incomplete';
        }

        if ($subscription->onTrial()) {
            return 'trialing';
        }

        if ($subscription->active()) {
            return 'active';
        }

        return (string) $subscription->stripe_status;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'none' => 'No subscription',
            'active' => 'Active',
            'trialing' => 'Trialing',
            'past_due' => 'Past due',
            'canceling' => 'Canceling',
            'canceled' => 'Canceled',
            'incomplete' => 'Incomplete',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function licensedScreenCount(Workspace $workspace): int
    {
        $subscription = self::subscription($workspace);

        if ($subscription === null || (! $subscription->valid() && ! $subscription->pastDue())) {
            return 0;
        }

        return max(0, (int) ($subscription->quantity ?? 0));
    }

    /**
     * Connected Screens: non-revoked ScreenDevice, regardless of Online/Active.
     */
    public static function usedScreenLicences(Workspace $workspace): int
    {
        return Screen::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('devices', fn ($q) => $q->whereNull('revoked_at'))
            ->count();
    }

    public static function remainingScreenLicences(Workspace $workspace): int
    {
        return max(0, self::licensedScreenCount($workspace) - self::usedScreenLicences($workspace));
    }

    public static function canPairScreen(Workspace $workspace): bool
    {
        if (! self::enforce()) {
            return true;
        }

        if (! self::hasEntitlement($workspace)) {
            return false;
        }

        return self::usedScreenLicences($workspace) < self::licensedScreenCount($workspace);
    }

    /**
     * @return array{allowed: bool, message: string|null, code: string|null}
     */
    public static function pairingGate(Workspace $workspace): array
    {
        if (! self::enforce()) {
            return ['allowed' => true, 'message' => null, 'code' => null];
        }

        if (! self::hasEntitlement($workspace)) {
            return [
                'allowed' => false,
                'message' => 'An active subscription with TV licences is required to pair TVs.',
                'code' => 'subscription_required',
            ];
        }

        $licensed = self::licensedScreenCount($workspace);
        $used = self::usedScreenLicences($workspace);

        if ($used >= $licensed) {
            return [
                'allowed' => false,
                'message' => "All {$licensed} TV licences are currently in use.",
                'code' => 'licence_limit',
            ];
        }

        return ['allowed' => true, 'message' => null, 'code' => null];
    }

    public static function canPublish(Workspace $workspace): bool
    {
        if (! self::enforce()) {
            return true;
        }

        return self::hasEntitlement($workspace);
    }

    public static function canReceiveLiveContent(Workspace $workspace): bool
    {
        return self::canPublish($workspace);
    }

    /**
     * Assert pairing is allowed inside a transaction with a workspace lock.
     *
     * @throws ValidationException
     */
    public static function assertCanPairLocked(Workspace $workspace): void
    {
        Workspace::query()->whereKey($workspace->id)->lockForUpdate()->first();
        $workspace->refresh();

        $gate = self::pairingGate($workspace);

        if ($gate['allowed']) {
            return;
        }

        throw ValidationException::withMessages([
            'billing' => $gate['message'] ?? 'Screen pairing is not allowed.',
            'code' => $gate['code'] ?? 'billing',
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function connectedScreenSummaries(Workspace $workspace): array
    {
        $rows = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('devices', fn ($q) => $q->whereNull('revoked_at'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Screen $screen) => [
                'id' => $screen->id,
                'name' => $screen->name,
            ])
            ->values()
            ->all();

        return array_values($rows);
    }

    /**
     * Cached Stripe Price display info, falling back to catalog amounts when
     * Stripe is not configured (local/dev). Never invents amounts outside config.
     *
     * @return array{interval: string, price_id: string|null, unit_amount: int|null, currency: string|null, formatted: string|null, source: string}|null
     */
    public static function priceDisplay(string $interval): ?array
    {
        $priceId = self::priceId($interval);
        $catalog = self::catalogDisplay($interval);

        if ($priceId === null || ! filled(config('cashier.secret'))) {
            return $catalog;
        }

        $ttl = max(60, (int) config('billing.price_cache_ttl', 3600));

        try {
            /** @var array{interval: string, price_id: string|null, unit_amount: int|null, currency: string|null, formatted: string|null, source: string}|null $cached */
            $cached = Cache::remember("billing.price.{$interval}.{$priceId}", $ttl, function () use ($interval, $priceId) {
                $price = Cashier::stripe()->prices->retrieve($priceId);
                $amount = $price->unit_amount;
                $currency = strtoupper((string) $price->currency);
                $formatted = null;

                if ($amount !== null) {
                    $formatted = Cashier::formatAmount($amount, $currency);
                }

                return [
                    'interval' => $interval,
                    'price_id' => $priceId,
                    'unit_amount' => $amount,
                    'currency' => $currency,
                    'formatted' => $formatted,
                    'source' => 'stripe',
                ];
            });

            return $cached;
        } catch (Throwable) {
            return $catalog ?? [
                'interval' => $interval,
                'price_id' => $priceId,
                'unit_amount' => null,
                'currency' => null,
                'formatted' => null,
                'source' => 'unavailable',
            ];
        }
    }

    /**
     * @return array{interval: string, price_id: string|null, unit_amount: int|null, currency: string|null, formatted: string|null, source: string}|null
     */
    public static function catalogDisplay(string $interval): ?array
    {
        $starter = BillingPlanCatalog::plan('starter');
        $amount = match ($interval) {
            'monthly' => (int) ($starter['monthly_amount'] ?? config('billing.catalog.monthly_amount', 0)),
            'yearly' => (int) ($starter['yearly_amount'] ?? config('billing.catalog.yearly_amount', 0)),
            default => 0,
        };

        if ($amount <= 0) {
            return null;
        }

        $currency = strtoupper((string) config('billing.catalog.currency', config('cashier.currency', 'gbp')));

        return [
            'interval' => $interval,
            'price_id' => self::priceId($interval),
            'unit_amount' => $amount,
            'currency' => $currency,
            'formatted' => Cashier::formatAmount($amount, $currency),
            'source' => 'catalog',
        ];
    }

    /**
     * @return list<array{interval: string, price_id: string|null, unit_amount: int|null, currency: string|null, formatted: string|null, source?: string}>
     */
    public static function availablePriceDisplays(): array
    {
        $rows = [];
        $intervals = self::allowedIntervals();

        if ($intervals === []) {
            $intervals = ['monthly', 'yearly'];
        }

        foreach ($intervals as $interval) {
            $display = self::priceDisplay($interval);
            if ($display !== null && ($display['formatted'] ?? null) !== null) {
                $rows[] = $display;
            }
        }

        return $rows;
    }

    /**
     * Format a Stripe minor-unit amount for display.
     */
    public static function formatAmount(int $amount, ?string $currency = null): string
    {
        return Cashier::formatAmount($amount, $currency ?: (string) config('cashier.currency'));
    }

    public static function intervalForPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach (BillingPlanCatalog::plans(false) as $plan) {
            foreach (['monthly', 'yearly'] as $interval) {
                if (($plan['prices'][$interval] ?? null) === $priceId) {
                    return $interval;
                }
            }
        }

        if ($priceId === config('billing.prices.monthly')) {
            return 'monthly';
        }

        if ($priceId === config('billing.prices.yearly')) {
            return 'yearly';
        }

        return null;
    }

    /**
     * Snapshot for Inertia / Admin payloads.
     *
     * @return array<string, mixed>
     */
    public static function summary(Workspace $workspace): array
    {
        $subscription = self::subscription($workspace);
        $status = self::status($workspace);
        $licensed = self::licensedScreenCount($workspace);
        $used = self::usedScreenLicences($workspace);
        $priceId = $subscription?->stripe_price;
        $interval = self::intervalForPriceId($priceId);
        $plan = self::currentPlan($workspace);
        $planKey = $plan['key'] ?? null;
        $storageGb = $plan['storage_gb'] ?? null;
        $override = self::override($workspace);
        if ($override?->storage_gb !== null) {
            $storageGb = $override->storage_gb;
        }
        $teamLimit = self::teamLimit($workspace);
        $teamUsed = self::teamUsage($workspace);
        $features = $override !== null && is_array($override->features) && $override->features !== []
            ? $override->features
            : ($plan['features'] ?? []);

        return [
            'configured' => self::isConfigured(),
            'enforce' => self::enforce(),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'has_subscription' => $subscription !== null,
            'has_entitlement' => self::hasEntitlement($workspace),
            'stripe_id' => $workspace->stripe_id,
            'subscription_id' => $subscription?->stripe_id,
            'interval' => $interval,
            'interval_label' => $interval === 'yearly' ? 'Yearly' : ($interval === 'monthly' ? 'Monthly' : null),
            'plan_key' => $planKey,
            'plan_name' => $plan['name'] ?? null,
            'storage_gb' => $storageGb,
            'storage_limit_bytes' => self::storageLimitBytes($workspace),
            'team_limit' => $teamLimit,
            'team_used' => $teamUsed,
            'features' => array_values($features),
            'licensed' => $licensed,
            'used' => $used,
            'remaining' => max(0, $licensed - $used),
            'screen_limit' => self::screenLimit($workspace),
            'quantity' => $subscription?->quantity,
            'on_grace_period' => $subscription?->onGracePeriod() ?? false,
            'ends_at' => $subscription?->ends_at?->toIso8601String(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'past_due' => $subscription?->pastDue() ?? false,
            'connected_screens' => self::connectedScreenSummaries($workspace),
        ];
    }
}
