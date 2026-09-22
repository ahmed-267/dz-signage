<?php

namespace App\Actions\Billing;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\Platform\AuditLogger;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Start a Stripe Checkout Session for a named plan (Starter / Business).
 *
 * The Price ID is never taken from the client: only the plan key and interval
 * are accepted and resolved through BillingPlanCatalog / BillingEntitlement.
 */
class StartCheckout
{
    public function handle(User $user, Workspace $workspace, string $planKey, string $interval): string
    {
        if ($planKey === 'enterprise') {
            throw ValidationException::withMessages([
                'plan' => 'Enterprise plans require contacting sales.',
            ]);
        }

        $plan = BillingPlanCatalog::plan($planKey);

        if ($plan === null || ($plan['enterprise'] ?? false)) {
            throw ValidationException::withMessages([
                'plan' => 'Choose a Starter or Business plan.',
            ]);
        }

        if (! BillingEntitlement::stripeReady()) {
            throw ValidationException::withMessages([
                'billing' => 'Billing is not configured for this environment yet.',
            ]);
        }

        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages([
                'interval' => 'Choose a monthly or yearly plan.',
            ]);
        }

        $priceId = BillingEntitlement::priceIdForPlan($planKey, $interval);

        if ($priceId === null) {
            throw ValidationException::withMessages([
                'interval' => 'Choose a monthly or yearly plan.',
            ]);
        }

        $quantity = (int) ($plan['screen_limit'] ?? 0);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'plan' => 'This plan does not include TV licences.',
            ]);
        }

        if (BillingEntitlement::hasActiveSubscription($workspace)) {
            throw ValidationException::withMessages([
                'billing' => 'This workspace already has a subscription. Change the TV licence count instead.',
            ]);
        }

        try {
            $workspace->createOrGetStripeCustomer([
                'metadata' => [
                    'workspace_id' => (string) $workspace->id,
                    'workspace_name' => (string) $workspace->name,
                ],
            ]);

            $checkout = $workspace
                ->newSubscription(BillingEntitlement::subscriptionType(), $priceId)
                ->quantity($quantity)
                ->checkout([
                    'success_url' => route('app.settings.tab', ['tab' => 'billing']).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('app.settings.tab', ['tab' => 'billing']).'?checkout=cancelled',
                ]);

            $url = (string) $checkout->asStripeCheckoutSession()->url;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not start the checkout session. Please try again.',
            ]);
        }

        AuditLogger::record(
            $user,
            'billing.checkout_started',
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'plan' => $planKey,
                'interval' => $interval,
                'price_id' => $priceId,
                'quantity' => $quantity,
            ],
        );

        return $url;
    }
}
