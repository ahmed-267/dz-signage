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
 * Change how many Screen licences a Workspace pays for.
 *
 * Licences can never drop below the Screens that are currently Connected —
 * the customer disconnects Screens first, then reduces the subscription.
 */
class UpdateScreenLicenceQuantity
{
    public function handle(User $user, Workspace $workspace, int $quantity): Subscription
    {
        $subscription = BillingEntitlement::subscription($workspace);

        if ($subscription === null || (! $subscription->valid() && ! $subscription->pastDue())) {
            throw ValidationException::withMessages([
                'billing' => 'This workspace does not have an active subscription.',
            ]);
        }

        $min = BillingEntitlement::minLicenses();
        $max = BillingEntitlement::maxLicenses();

        if ($quantity < $min || $quantity > $max) {
            throw ValidationException::withMessages([
                'quantity' => "Choose between {$min} and {$max} TV licences.",
            ]);
        }

        $used = BillingEntitlement::usedScreenLicences($workspace);

        if ($quantity < $used) {
            $excess = $used - $quantity;

            throw ValidationException::withMessages([
                'quantity' => "Disconnect {$excess} Screen(s) before reducing to {$quantity} licences. {$used} Screens are currently connected.",
            ]);
        }

        $previous = (int) ($subscription->quantity ?? 0);

        try {
            $subscription->updateQuantity($quantity);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not update the TV licence count. Please try again.',
            ]);
        }

        AuditLogger::record(
            $user,
            'billing.quantity_changed',
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'previous_quantity' => $previous,
                'new_quantity' => $quantity,
                'used_licences' => $used,
                'subscription_id' => $subscription->stripe_id,
            ],
        );

        return $subscription->refresh();
    }
}
