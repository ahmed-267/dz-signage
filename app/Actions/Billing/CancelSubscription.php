<?php

namespace App\Actions\Billing;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Platform\AuditLogger;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;
use Throwable;

/**
 * Cancel at period end. Screens keep playing until the grace period expires.
 */
class CancelSubscription
{
    public function handle(User $user, Workspace $workspace): Subscription
    {
        $subscription = BillingEntitlement::subscription($workspace);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'billing' => 'This workspace does not have a subscription.',
            ]);
        }

        if ($subscription->canceled()) {
            throw ValidationException::withMessages([
                'billing' => 'This subscription is already canceled.',
            ]);
        }

        try {
            $subscription->cancel();
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not cancel the subscription. Please try again.',
            ]);
        }

        AuditLogger::record(
            $user,
            'billing.subscription_canceled',
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'subscription_id' => $subscription->stripe_id,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'quantity' => $subscription->quantity,
            ],
        );

        return $subscription;
    }
}
