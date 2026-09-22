<?php

namespace App\Support\Billing;

use App\Models\BillingPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Creates new Stripe Prices when catalog amounts change.
 * Never mutates an existing Stripe Price — subscriptions stay on the old Price
 * until an explicit migrate-subscribers action runs.
 */
final class StripePriceSynchronizer
{
    /**
     * Ensure Stripe Product + Prices exist for the given plan amounts.
     * Updates the BillingPlan stripe price IDs when new Prices are created.
     *
     * @return array{synced: bool, monthly_price_id: string|null, annual_price_id: string|null, message: string}
     */
    public function sync(BillingPlan $plan, bool $forceNewPrices = false): array
    {
        if ($plan->enterprise) {
            return [
                'synced' => true,
                'monthly_price_id' => null,
                'annual_price_id' => null,
                'message' => 'Enterprise plans do not use self-serve Stripe Prices.',
            ];
        }

        if (! filled(config('cashier.secret'))) {
            return [
                'synced' => false,
                'monthly_price_id' => $plan->monthly_stripe_price_id,
                'annual_price_id' => $plan->annual_stripe_price_id,
                'message' => 'Stripe is not configured. Amounts were saved locally; Stripe price not synchronised.',
            ];
        }

        try {
            $stripe = Cashier::stripe();
            $currency = strtolower($plan->currency ?: (string) config('cashier.currency', 'gbp'));

            $productId = $plan->stripe_product_id;
            if (! filled($productId)) {
                $product = $stripe->products->create([
                    'name' => 'RMSignage '.$plan->name,
                    'metadata' => [
                        'billing_plan_key' => $plan->key,
                    ],
                ]);
                $productId = $product->id;
                $plan->forceFill(['stripe_product_id' => $productId])->save();
            }

            $monthlyId = $plan->monthly_stripe_price_id;
            $annualId = $plan->annual_stripe_price_id;

            if ($plan->monthly_amount !== null && $plan->monthly_amount > 0) {
                if ($forceNewPrices || ! filled($monthlyId) || $this->amountMismatch($monthlyId, $plan->monthly_amount, $currency)) {
                    $this->rememberLegacyPrice($plan, $monthlyId, 'monthly');
                    $price = $stripe->prices->create([
                        'product' => $productId,
                        'unit_amount' => $plan->monthly_amount,
                        'currency' => $currency,
                        'recurring' => ['interval' => 'month'],
                        'metadata' => [
                            'billing_plan_key' => $plan->key,
                            'interval' => 'monthly',
                        ],
                    ]);
                    $monthlyId = $price->id;
                }
            }

            if ($plan->annual_amount !== null && $plan->annual_amount > 0) {
                if ($forceNewPrices || ! filled($annualId) || $this->amountMismatch($annualId, $plan->annual_amount, $currency)) {
                    $this->rememberLegacyPrice($plan, $annualId, 'yearly');
                    $price = $stripe->prices->create([
                        'product' => $productId,
                        'unit_amount' => $plan->annual_amount,
                        'currency' => $currency,
                        'recurring' => ['interval' => 'year'],
                        'metadata' => [
                            'billing_plan_key' => $plan->key,
                            'interval' => 'yearly',
                        ],
                    ]);
                    $annualId = $price->id;
                }
            }

            $plan->forceFill([
                'monthly_stripe_price_id' => $monthlyId,
                'annual_stripe_price_id' => $annualId,
            ])->save();

            BillingPlanCatalog::clearCache();

            return [
                'synced' => true,
                'monthly_price_id' => $monthlyId,
                'annual_price_id' => $annualId,
                'message' => 'Stripe Prices synchronised. Existing subscriptions remain on previous Prices until migrated.',
            ];
        } catch (Throwable $e) {
            report($e);
            Log::warning('Stripe price sync failed', [
                'plan' => $plan->key,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'stripe' => 'Stripe could not create Prices. Local plan amounts were kept; Stripe price not synchronised.',
            ]);
        }
    }

    private function rememberLegacyPrice(BillingPlan $plan, ?string $priceId, string $interval): void
    {
        if (! filled($priceId)) {
            return;
        }

        $legacy = $plan->legacy_stripe_price_ids ?? [];
        $legacy[] = [
            'price_id' => $priceId,
            'interval' => $interval,
        ];

        // Deduplicate by price_id
        $seen = [];
        $unique = [];
        foreach ($legacy as $row) {
            if (is_string($row)) {
                if ($row === '') {
                    continue;
                }
                $id = $row;
                $rowInterval = 'monthly';
            } else {
                $id = $row['price_id'];
                if ($id === '') {
                    continue;
                }
                $rowInterval = in_array($row['interval'], ['monthly', 'yearly'], true)
                    ? $row['interval']
                    : 'monthly';
            }

            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = [
                'price_id' => $id,
                'interval' => $rowInterval,
            ];
        }

        $plan->forceFill(['legacy_stripe_price_ids' => $unique])->save();
    }

    private function amountMismatch(string $priceId, int $expectedAmount, string $currency): bool
    {
        try {
            $price = Cashier::stripe()->prices->retrieve($priceId);

            return (int) $price->unit_amount !== $expectedAmount
                || strtolower((string) $price->currency) !== strtolower($currency);
        } catch (Throwable) {
            return true;
        }
    }
}
