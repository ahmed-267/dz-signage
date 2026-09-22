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
 * Resume a subscription that is still inside its grace period.
 */
class ResumeSubscription
{
    public function handle(User $user, Workspace $workspace): Subscription
    {
        $subscription = BillingEntitlement::subscription($workspace);

        if ($subscription === null || ! $subscription->onGracePeriod()) {
            throw ValidationException::withMessages([
                'billing' => 'Only a canceled subscription inside its grace period can be resumed.',
            ]);
        }

        try {
            $subscription->resume();
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not resume the subscription. Please try again.',
            ]);
        }

        AuditLogger::record(
            $user,
            'billing.subscription_resumed',
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'subscription_id' => $subscription->stripe_id,
                'quantity' => $subscription->quantity,
            ],
        );

        return $subscription->refresh();
    }
}
