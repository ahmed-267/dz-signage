<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\MigratePlanSubscribers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBillingPlanRequest;
use App\Models\BillingPlan;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingFeatureCatalog;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\Billing\StripePriceSynchronizer;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformPermissions;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;

/**
 * Super Admin plan catalog management. Platform Admin is read-only.
 */
class BillingPlanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        // Ensure seed defaults exist in empty environments (local/test).
        if (BillingPlan::query()->doesntExist()) {
            (new BillingPlanSeeder)->run();
            BillingPlanCatalog::clearCache();
        }

        $plans = BillingPlan::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BillingPlan $plan) => $this->adminRow($plan))
            ->values();

        return Inertia::render('admin/billing/plans/index', [
            'plans' => $plans,
            'can_manage' => PlatformPermissions::canManageBillingPlans($request->user()),
            'permissions' => PlatformPermissions::for($request->user()),
            'stripe_configured' => filled(config('cashier.secret')),
            'currency' => strtoupper((string) config('billing.catalog.currency', config('cashier.currency', 'gbp'))),
        ]);
    }

    public function edit(Request $request, BillingPlan $plan): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $migrator = app(MigratePlanSubscribers::class);

        return Inertia::render('admin/billing/plans/edit', [
            'plan' => $this->adminRow($plan),
            'feature_catalog' => BillingFeatureCatalog::checklist(),
            'can_manage' => PlatformPermissions::canManageBillingPlans($request->user()),
            'permissions' => PlatformPermissions::for($request->user()),
            'stripe_configured' => filled(config('cashier.secret')),
            'eligible_subscriber_count' => $migrator->eligibleCount($plan),
        ]);
    }

    public function update(
        UpdateBillingPlanRequest $request,
        BillingPlan $plan,
        StripePriceSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless(PlatformPermissions::canManageBillingPlans($request->user()), 403);

        if ($plan->key === 'pro') {
            abort(404);
        }

        $data = $request->validated();
        unset($data['confirm'], $data['sync_stripe_prices']);

        $before = $plan->only([
            'name',
            'description',
            'currency',
            'monthly_amount',
            'annual_amount',
            'yearly_monthly_equivalent',
            'monthly_stripe_price_id',
            'annual_stripe_price_id',
            'screen_limit',
            'storage_gb',
            'team_member_limit',
            'features',
            'feature_labels',
            'badge',
            'popular',
            'active',
            'public',
            'sort_order',
            'cta',
        ]);

        // Enterprise: clear self-serve pricing fields.
        if ($plan->enterprise) {
            $data['monthly_amount'] = null;
            $data['annual_amount'] = null;
            $data['yearly_monthly_equivalent'] = null;
            $data['monthly_stripe_price_id'] = null;
            $data['annual_stripe_price_id'] = null;
        }

        $data['currency'] = strtolower((string) $data['currency']);
        $data['features'] = array_values(array_unique($data['features'] ?? []));
        $data['feature_labels'] = array_values($data['feature_labels'] ?? []);

        $amountChanged = ! $plan->enterprise && (
            (int) ($before['monthly_amount'] ?? 0) !== (int) ($data['monthly_amount'] ?? 0)
            || (int) ($before['annual_amount'] ?? 0) !== (int) ($data['annual_amount'] ?? 0)
        );

        $plan->forceFill($data)->save();

        $syncMessage = null;
        $shouldSync = (bool) $request->boolean('sync_stripe_prices') || $amountChanged;

        if ($shouldSync && ! $plan->enterprise) {
            try {
                $result = $synchronizer->sync($plan->fresh(), forceNewPrices: $amountChanged);
                $syncMessage = $result['message'];
            } catch (ValidationException $e) {
                // Amounts already saved; surface Stripe issue without rolling back catalog edit.
                $syncMessage = $e->errors()['stripe'][0] ?? 'Stripe price not synchronised.';
            }
        }

        BillingPlanCatalog::clearCache();

        AuditLogger::record(
            $request->user(),
            'billing_plan.updated',
            'billing_plan',
            $plan->id,
            null,
            [
                'plan_key' => $plan->key,
                'before' => $before,
                'after' => $plan->fresh()->only(array_keys($before)),
                'amount_changed' => $amountChanged,
                'sync_message' => $syncMessage,
            ],
        );

        $success = 'Plan updated.';
        if ($syncMessage) {
            $success .= ' '.$syncMessage;
        }

        return redirect()
            ->route('admin.subscriptions.plans.edit', $plan)
            ->with('success', $success);
    }

    public function syncStripePrice(
        Request $request,
        BillingPlan $plan,
        StripePriceSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_unless(PlatformPermissions::canManageBillingPlans($request->user()), 403);
        abort_if($plan->enterprise, 404);

        $request->validate([
            'confirm' => ['accepted'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $result = $synchronizer->sync($plan, forceNewPrices: $request->boolean('force'));

        AuditLogger::record(
            $request->user(),
            'billing_plan.stripe_synced',
            'billing_plan',
            $plan->id,
            null,
            [
                'plan_key' => $plan->key,
                'result' => $result,
            ],
        );

        return back()->with('success', $result['message']);
    }

    public function migrateSubscribers(
        Request $request,
        BillingPlan $plan,
        MigratePlanSubscribers $migrator,
    ): RedirectResponse {
        abort_unless(PlatformPermissions::canManageBillingPlans($request->user()), 403);
        abort_if($plan->enterprise, 404);

        $data = $request->validate([
            'confirm' => ['accepted'],
            'interval' => ['nullable', 'string', 'in:monthly,yearly,both'],
        ]);

        if (! BillingEntitlement::stripeReady() && ! filled(config('cashier.secret'))) {
            throw ValidationException::withMessages([
                'billing' => 'Stripe is not configured; cannot migrate subscribers.',
            ]);
        }

        $result = $migrator->handle(
            $request->user(),
            $plan,
            $data['interval'] ?? 'both',
        );

        $message = sprintf(
            'Migration complete: %d migrated, %d skipped, %d failed.',
            $result['migrated'],
            $result['skipped'],
            $result['failed'],
        );

        if ($result['errors'] !== []) {
            return back()->with('error', $message.' '.implode(' ', array_slice($result['errors'], 0, 3)));
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminRow(BillingPlan $plan): array
    {
        $currency = strtoupper($plan->currency ?: (string) config('cashier.currency', 'gbp'));

        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description,
            'currency' => $currency,
            'monthly_amount' => $plan->monthly_amount,
            'annual_amount' => $plan->annual_amount,
            'yearly_monthly_equivalent' => $plan->yearly_monthly_equivalent,
            'monthly_formatted' => $plan->monthly_amount !== null
                ? Cashier::formatAmount($plan->monthly_amount, $currency)
                : null,
            'annual_formatted' => $plan->annual_amount !== null
                ? Cashier::formatAmount($plan->annual_amount, $currency)
                : null,
            'yearly_eq_formatted' => $plan->yearly_monthly_equivalent !== null
                ? Cashier::formatAmount($plan->yearly_monthly_equivalent, $currency)
                : null,
            'monthly_stripe_price_id' => $plan->monthly_stripe_price_id,
            'annual_stripe_price_id' => $plan->annual_stripe_price_id,
            'stripe_product_id' => $plan->stripe_product_id,
            'screen_limit' => $plan->screen_limit,
            'storage_gb' => $plan->storage_gb,
            'team_member_limit' => $plan->team_member_limit,
            'features' => array_values($plan->features ?? []),
            'feature_labels' => array_values($plan->feature_labels ?? []),
            'badge' => $plan->badge,
            'popular' => $plan->popular,
            'active' => $plan->active,
            'public' => $plan->public,
            'sort_order' => $plan->sort_order,
            'enterprise' => $plan->enterprise,
            'cta' => $plan->cta,
            'stripe_synced' => $plan->stripeSynced(),
            'legacy_price_count' => count($plan->legacy_stripe_price_ids ?? []),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
