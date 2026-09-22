<?php

namespace App\Actions\Billing;

use App\Models\BillingPlan;
use App\Models\User;
use App\Support\Billing\BillingEntitlement;
use App\Support\Platform\AuditLogger;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Throwable;

/**
 * Explicit Super Admin action: swap existing subscribers onto the plan's
 * current Stripe Price IDs. Never runs automatically on amount edits.
 */
class MigratePlanSubscribers
{
    /**
     * @return array{migrated: int, failed: int, skipped: int, errors: list<string>, eligible: int}
     */
    public function handle(User $actor, BillingPlan $plan, string $interval = 'both'): array
    {
        $intervals = match ($interval) {
            'monthly' => ['monthly'],
            'yearly' => ['yearly'],
            default => ['monthly', 'yearly'],
        };

        $targetPrices = [];
        foreach ($intervals as $iv) {
            $priceId = $iv === 'monthly'
                ? $plan->monthly_stripe_price_id
                : $plan->annual_stripe_price_id;
            if (filled($priceId)) {
                $targetPrices[$iv] = (string) $priceId;
            }
        }

        if ($targetPrices === []) {
            return [
                'migrated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'eligible' => 0,
                'errors' => ['No Stripe Price IDs are set on this plan for the selected interval(s).'],
            ];
        }

        $priceIntervalMap = $this->priceIntervalMap($plan);

        $candidatePriceIds = array_keys($priceIntervalMap);
        if ($plan->key === 'starter') {
            foreach (['monthly' => config('billing.prices.monthly'), 'yearly' => config('billing.prices.yearly')] as $iv => $legacyEnv) {
                if (filled($legacyEnv)) {
                    $candidatePriceIds[] = (string) $legacyEnv;
                    $priceIntervalMap[(string) $legacyEnv] = $iv;
                }
            }
        }

        $candidatePriceIds = array_values(array_unique(array_filter($candidatePriceIds)));

        $subscriptions = Subscription::query()
            ->where('type', BillingEntitlement::subscriptionType())
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->whereIn('stripe_price', $candidatePriceIds)
            ->get();

        $migrated = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($subscriptions as $subscription) {
            $currentPrice = (string) $subscription->stripe_price;
            $currentInterval = $priceIntervalMap[$currentPrice]
                ?? BillingEntitlement::intervalForPriceId($currentPrice);

            if ($currentInterval === null || ! isset($targetPrices[$currentInterval])) {
                $skipped++;

                continue;
            }

            $targetPrice = $targetPrices[$currentInterval];
            if ($currentPrice === $targetPrice) {
                $skipped++;

                continue;
            }

            try {
                $subscription->swapAndInvoice($targetPrice);
                $migrated++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $errors[] = ($subscription->stripe_id ?? 'unknown').': '.$e->getMessage();
                Log::warning('Plan subscriber migration failed', [
                    'subscription' => $subscription->stripe_id,
                    'plan' => $plan->key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLogger::record(
            $actor,
            'billing_plan.subscribers_migrated',
            'billing_plan',
            $plan->id,
            null,
            [
                'plan_key' => $plan->key,
                'interval' => $interval,
                'migrated' => $migrated,
                'failed' => $failed,
                'skipped' => $skipped,
                'target_prices' => $targetPrices,
            ],
        );

        return [
            'migrated' => $migrated,
            'failed' => $failed,
            'skipped' => $skipped,
            'eligible' => $subscriptions->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Count subscriptions that would be migrated (for confirmation UI).
     */
    public function eligibleCount(BillingPlan $plan): int
    {
        $map = $this->priceIntervalMap($plan);
        $ids = array_keys($map);
        $targets = array_filter([
            $plan->monthly_stripe_price_id,
            $plan->annual_stripe_price_id,
        ]);

        $legacyOnly = array_values(array_diff($ids, $targets));
        if ($legacyOnly === []) {
            return 0;
        }

        return Subscription::query()
            ->where('type', BillingEntitlement::subscriptionType())
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->whereIn('stripe_price', $legacyOnly)
            ->count();
    }

    /**
     * @return array<string, string> price_id => monthly|yearly
     */
    private function priceIntervalMap(BillingPlan $plan): array
    {
        $map = [];

        if (filled($plan->monthly_stripe_price_id)) {
            $map[(string) $plan->monthly_stripe_price_id] = 'monthly';
        }
        if (filled($plan->annual_stripe_price_id)) {
            $map[(string) $plan->annual_stripe_price_id] = 'yearly';
        }

        foreach ($plan->legacy_stripe_price_ids ?? [] as $row) {
            if (is_string($row)) {
                if ($row !== '') {
                    $map[$row] = $map[$row] ?? 'monthly';
                }

                continue;
            }

            $interval = in_array($row['interval'], ['monthly', 'yearly'], true)
                ? $row['interval']
                : 'monthly';
            $map[$row['price_id']] = $interval;
        }

        return $map;
    }
}
