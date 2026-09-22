<?php

namespace Database\Seeders;

use App\Models\BillingPlan;
use Illuminate\Database\Seeder;

/**
 * Idempotent upsert of catalog plans from config/billing_plans.php.
 * Uses firstOrCreate by key so deliberate Super Admin edits are preserved.
 */
class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = config('billing_plans.plans', []);
        $currency = strtolower((string) config('billing.catalog.currency', config('cashier.currency', 'gbp')));

        if (! is_array($plans)) {
            return;
        }

        foreach ($plans as $key => $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $planKey = (string) ($plan['key'] ?? $key);
            if ($planKey === '' || $planKey === 'pro') {
                continue;
            }

            BillingPlan::query()->firstOrCreate(
                ['key' => $planKey],
                [
                    'name' => (string) ($plan['name'] ?? ucfirst($planKey)),
                    'description' => $plan['tagline'] ?? $plan['description'] ?? null,
                    'currency' => $currency,
                    'monthly_amount' => self::nullableInt($plan['monthly_amount'] ?? null),
                    'annual_amount' => self::nullableInt($plan['yearly_amount'] ?? $plan['annual_amount'] ?? null),
                    'yearly_monthly_equivalent' => self::nullableInt($plan['yearly_monthly_equivalent'] ?? null),
                    'monthly_stripe_price_id' => filled($plan['prices']['monthly'] ?? null)
                        ? (string) $plan['prices']['monthly']
                        : null,
                    'annual_stripe_price_id' => filled($plan['prices']['yearly'] ?? null)
                        ? (string) $plan['prices']['yearly']
                        : null,
                    'screen_limit' => self::nullableInt($plan['screen_limit'] ?? null),
                    'storage_gb' => self::nullableInt($plan['storage_gb'] ?? null),
                    'team_member_limit' => self::nullableInt($plan['team_limit'] ?? $plan['team_member_limit'] ?? null),
                    'features' => array_values($plan['features'] ?? []),
                    'feature_labels' => array_values($plan['feature_labels'] ?? []),
                    'badge' => $plan['badge'] ?? (($plan['popular'] ?? false) ? 'Most popular' : null),
                    'popular' => (bool) ($plan['popular'] ?? false),
                    'active' => (bool) ($plan['active'] ?? true),
                    'public' => (bool) ($plan['public'] ?? true),
                    'sort_order' => (int) ($plan['sort_order'] ?? 0),
                    'enterprise' => (bool) ($plan['enterprise'] ?? false),
                    'cta' => (string) ($plan['cta'] ?? 'Get Started'),
                ],
            );
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
