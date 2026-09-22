<?php

namespace App\Actions\Billing;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\Platform\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Super Admin: change a Workspace plan/interval via Cashier → Stripe.
 * Never updates local plan state without a successful provider mutation.
 */
class AdminChangeWorkspaceSubscription
{
    public function handle(
        User $actor,
        Workspace $workspace,
        string $planKey,
        string $interval,
    ): void {
        if ($planKey === 'enterprise') {
            throw ValidationException::withMessages([
                'plan' => 'Enterprise changes require a custom sales agreement, not self-serve mutation.',
            ]);
        }

        $plan = BillingPlanCatalog::plan($planKey);
        if ($plan === null || ($plan['enterprise'] ?? false)) {
            throw ValidationException::withMessages([
                'plan' => 'Choose Starter or Business.',
            ]);
        }

        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages([
                'interval' => 'Choose monthly or yearly.',
            ]);
        }

        if (! BillingEntitlement::stripeReady()) {
            throw ValidationException::withMessages([
                'billing' => 'Stripe is not configured for this environment.',
            ]);
        }

        $priceId = BillingEntitlement::priceIdForPlan($planKey, $interval);
        if ($priceId === null) {
            throw ValidationException::withMessages([
                'billing' => 'No Stripe Price ID is configured for this plan and interval.',
            ]);
        }

        $quantity = max(1, (int) ($plan['screen_limit'] ?? 1));
        $used = BillingEntitlement::usedScreenLicences($workspace);
        if ($used > $quantity) {
            throw ValidationException::withMessages([
                'plan' => "This workspace has {$used} connected Screens. Disconnect ".($used - $quantity)." before moving to {$plan['name']} ({$quantity} Screens).",
            ]);
        }

        $subscription = BillingEntitlement::subscription($workspace);
        if ($subscription === null || (! $subscription->valid() && ! $subscription->pastDue() && ! $subscription->onGracePeriod())) {
            throw ValidationException::withMessages([
                'billing' => 'This workspace has no mutable subscription. The Workspace Owner must start Checkout first.',
            ]);
        }

        $before = [
            'plan' => BillingEntitlement::currentPlanKey($workspace),
            'interval' => BillingEntitlement::intervalForPriceId($subscription->stripe_price),
            'quantity' => (int) ($subscription->quantity ?? 0),
            'stripe_price' => $subscription->stripe_price,
        ];

        try {
            DB::transaction(function () use ($subscription, $priceId, $quantity): void {
                $subscription->swapAndInvoice($priceId);
                $subscription->updateQuantity($quantity);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not update the subscription. Local billing state was not changed.',
            ]);
        }

        $workspace->unsetRelation('subscriptions');
        $subscription->refresh();

        AuditLogger::record(
            $actor,
            'billing.admin_plan_changed',
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'before' => $before,
                'after' => [
                    'plan' => $planKey,
                    'interval' => $interval,
                    'quantity' => $quantity,
                    'stripe_price' => $priceId,
                ],
            ],
        );
    }
}
