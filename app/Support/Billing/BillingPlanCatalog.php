<?php

namespace App\Support\Billing;

use App\Models\BillingPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Single source of truth for named billing plans (Starter / Business / Enterprise).
 * Reads persisted BillingPlan rows when available; falls back to config/billing_plans.php.
 * Pro is not part of the catalog.
 */
final class BillingPlanCatalog
{
    public const CACHE_KEY = 'billing.plan_catalog.v1';

    public const MARKETING_CACHE_KEY = 'billing.plan_marketing.v1';

    /**
     * @return list<array<string, mixed>>
     */
    public static function plans(bool $activeOnly = true): array
    {
        $rows = self::allNormalized();

        if ($activeOnly) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $plan): bool => (bool) ($plan['active'] ?? true),
            ));
        }

        usort($rows, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function plan(string $key): ?array
    {
        if ($key === 'pro') {
            return null;
        }

        foreach (self::allNormalized() as $plan) {
            if (($plan['key'] ?? '') === $key) {
                return $plan;
            }
        }

        return null;
    }

    public static function defaultPlanKey(): string
    {
        $key = (string) config('billing_plans.default_plan', 'starter');

        return self::plan($key) !== null ? $key : 'starter';
    }

    /**
     * Resolve plan from a Stripe Price ID.
     */
    public static function planKeyForPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach (self::plans(false) as $plan) {
            foreach (['monthly', 'yearly'] as $interval) {
                if (($plan['prices'][$interval] ?? null) === $priceId) {
                    return $plan['key'];
                }
            }
        }

        // Legacy single-price env fallback → Starter
        if ($priceId === config('billing.prices.monthly') || $priceId === config('billing.prices.yearly')) {
            return 'starter';
        }

        return null;
    }

    /**
     * @return list<array{feature: string, starter: mixed, business: mixed, enterprise: mixed}>
     */
    public static function comparison(): array
    {
        $fromDb = self::comparisonFromPlans();
        if ($fromDb !== []) {
            return $fromDb;
        }

        $raw = config('billing_plans.comparison', []);
        $rows = [];

        if (! is_array($raw)) {
            return [];
        }

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'feature' => (string) ($row['feature'] ?? ''),
                'starter' => $row['starter'] ?? null,
                'business' => $row['business'] ?? null,
                'enterprise' => $row['enterprise'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Public/marketing display payload (no secrets).
     *
     * @return array<string, mixed>
     */
    public static function marketingPayload(): array
    {
        $ttl = max(60, (int) config('billing.price_cache_ttl', 3600));

        try {
            /** @var array<string, mixed> $payload */
            $payload = Cache::remember(self::MARKETING_CACHE_KEY, $ttl, fn (): array => self::buildMarketingPayload());

            return $payload;
        } catch (Throwable) {
            return self::buildMarketingPayload();
        }
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::MARKETING_CACHE_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allNormalized(): array
    {
        $ttl = max(60, (int) config('billing.price_cache_ttl', 3600));

        try {
            /** @var list<array<string, mixed>> $cached */
            $cached = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => self::loadPlans());

            return $cached;
        } catch (Throwable) {
            return self::loadPlans();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadPlans(): array
    {
        $fromDb = self::loadFromDatabase();
        if ($fromDb !== []) {
            return $fromDb;
        }

        return self::loadFromConfig();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('billing_plans')) {
                return [];
            }

            if (BillingPlan::query()->doesntExist()) {
                return [];
            }

            return array_values(BillingPlan::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (BillingPlan $plan): array => self::normalize($plan->toCatalogArray()))
                ->all());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadFromConfig(): array
    {
        $plans = config('billing_plans.plans', []);
        $rows = [];

        if (! is_array($plans)) {
            return [];
        }

        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                continue;
            }
            if (($plan['key'] ?? '') === 'pro') {
                continue;
            }
            $rows[] = self::normalize($plan);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildMarketingPayload(): array
    {
        $currency = strtoupper((string) config('billing.catalog.currency', config('cashier.currency', 'gbp')));
        $plans = array_values(array_filter(
            self::plans(),
            fn (array $plan): bool => (bool) ($plan['public'] ?? true),
        ));

        return [
            'plans' => array_map(function (array $plan) use ($currency): array {
                $planCurrency = strtoupper((string) ($plan['currency'] ?? $currency));

                return [
                    'key' => $plan['key'],
                    'name' => $plan['name'],
                    'tagline' => $plan['tagline'],
                    'enterprise' => $plan['enterprise'],
                    'popular' => $plan['popular'],
                    'badge' => $plan['badge'] ?? null,
                    'cta' => $plan['cta'],
                    'screen_limit' => $plan['screen_limit'],
                    'storage_gb' => $plan['storage_gb'],
                    'team_limit' => $plan['team_limit'],
                    'feature_labels' => $plan['feature_labels'],
                    'monthly' => self::amountDisplay($plan['monthly_amount'], $planCurrency),
                    'yearly' => self::amountDisplay($plan['yearly_amount'], $planCurrency),
                    'yearly_monthly_equivalent' => self::amountDisplay($plan['yearly_monthly_equivalent'], $planCurrency),
                ];
            }, $plans),
            'comparison' => self::comparison(),
            'currency' => $currency,
        ];
    }

    /**
     * Derive a comparison matrix from active public plans when DB is the source.
     *
     * @return list<array{feature: string, starter: mixed, business: mixed, enterprise: mixed}>
     */
    private static function comparisonFromPlans(): array
    {
        $plans = [];
        foreach (self::plans() as $plan) {
            if (! ($plan['public'] ?? true)) {
                continue;
            }
            $plans[$plan['key']] = $plan;
        }

        if ($plans === [] || ! isset($plans['starter'], $plans['business'])) {
            return [];
        }

        $feature = static function (string $key) use ($plans): array {
            $cell = static function (string $planKey) use ($plans, $key): bool {
                $plan = $plans[$planKey] ?? null;
                if ($plan === null) {
                    return false;
                }
                if ($plan['enterprise'] ?? false) {
                    return true;
                }

                return BillingFeatureCatalog::planHas($plan['features'] ?? [], $key);
            };

            return [
                'starter' => $cell('starter'),
                'business' => $cell('business'),
                'enterprise' => $cell('enterprise'),
            ];
        };

        return [
            [
                'feature' => 'Connected Screens',
                'starter' => isset($plans['starter']['screen_limit']) ? (string) $plans['starter']['screen_limit'] : '—',
                'business' => isset($plans['business']['screen_limit']) ? (string) $plans['business']['screen_limit'] : '—',
                'enterprise' => 'Custom',
            ],
            [
                'feature' => 'Storage',
                'starter' => isset($plans['starter']['storage_gb']) ? $plans['starter']['storage_gb'].' GB' : '—',
                'business' => isset($plans['business']['storage_gb']) ? $plans['business']['storage_gb'].' GB' : '—',
                'enterprise' => 'Custom',
            ],
            [
                'feature' => 'Team members',
                'starter' => isset($plans['starter']['team_limit']) ? (string) $plans['starter']['team_limit'] : '—',
                'business' => isset($plans['business']['team_limit']) ? (string) $plans['business']['team_limit'] : '—',
                'enterprise' => 'Custom',
            ],
            ['feature' => 'Template library', ...$feature('full_template_library')],
            ['feature' => 'Brand Kit', ...$feature('brand_kit')],
            ['feature' => 'Playlists & Schedules', ...$feature('playlists')],
            ['feature' => 'Locations', ...$feature('locations')],
            ['feature' => 'Basic Analytics', ...$feature('basic_analytics')],
            ['feature' => 'Advanced Analytics', ...$feature('advanced_analytics')],
            [
                'feature' => 'Priority support',
                'starter' => BillingFeatureCatalog::planHas($plans['starter']['features'] ?? [], 'priority_email_support') ? 'Email' : false,
                'business' => BillingFeatureCatalog::planHas($plans['business']['features'] ?? [], 'priority_support') ? 'Priority' : false,
                'enterprise' => 'Dedicated',
            ],
            [
                'feature' => 'SLA & custom onboarding',
                'starter' => false,
                'business' => false,
                'enterprise' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function normalize(array $plan): array
    {
        return [
            'key' => (string) ($plan['key'] ?? ''),
            'name' => (string) ($plan['name'] ?? ''),
            'tagline' => (string) ($plan['tagline'] ?? $plan['description'] ?? ''),
            'monthly_amount' => isset($plan['monthly_amount']) ? (int) $plan['monthly_amount'] : null,
            'yearly_amount' => isset($plan['yearly_amount'])
                ? (int) $plan['yearly_amount']
                : (isset($plan['annual_amount']) ? (int) $plan['annual_amount'] : null),
            'yearly_monthly_equivalent' => isset($plan['yearly_monthly_equivalent'])
                ? (int) $plan['yearly_monthly_equivalent']
                : null,
            'prices' => [
                'monthly' => $plan['prices']['monthly'] ?? $plan['monthly_stripe_price_id'] ?? null,
                'yearly' => $plan['prices']['yearly'] ?? $plan['annual_stripe_price_id'] ?? null,
            ],
            'stripe_product_id' => $plan['stripe_product_id'] ?? null,
            'screen_limit' => array_key_exists('screen_limit', $plan) && $plan['screen_limit'] !== null
                ? (int) $plan['screen_limit']
                : null,
            'storage_gb' => array_key_exists('storage_gb', $plan) && $plan['storage_gb'] !== null
                ? (int) $plan['storage_gb']
                : null,
            'team_limit' => array_key_exists('team_limit', $plan) && $plan['team_limit'] !== null
                ? (int) $plan['team_limit']
                : (array_key_exists('team_member_limit', $plan) && $plan['team_member_limit'] !== null
                    ? (int) $plan['team_member_limit']
                    : null),
            'features' => array_values($plan['features'] ?? []),
            'feature_labels' => array_values($plan['feature_labels'] ?? []),
            'badge' => $plan['badge'] ?? null,
            'popular' => (bool) ($plan['popular'] ?? false),
            'enterprise' => (bool) ($plan['enterprise'] ?? false),
            'active' => (bool) ($plan['active'] ?? true),
            'public' => (bool) ($plan['public'] ?? true),
            'sort_order' => (int) ($plan['sort_order'] ?? 0),
            'cta' => (string) ($plan['cta'] ?? 'Get Started'),
            'currency' => strtoupper((string) ($plan['currency'] ?? config('billing.catalog.currency', 'gbp'))),
            'stripe_synced' => (bool) ($plan['stripe_synced'] ?? (
                ($plan['enterprise'] ?? false)
                || filled($plan['prices']['monthly'] ?? $plan['monthly_stripe_price_id'] ?? null)
                || filled($plan['prices']['yearly'] ?? $plan['annual_stripe_price_id'] ?? null)
            )),
        ];
    }

    /**
     * @return array{amount: int|null, formatted: string|null, currency: string}|null
     */
    private static function amountDisplay(?int $amount, string $currency): ?array
    {
        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'formatted' => Cashier::formatAmount($amount, $currency),
            'currency' => $currency,
        ];
    }
}
