<?php

namespace App\Actions\Billing;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Platform\AuditLogger;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Payment methods, receipts and tax details are managed in Stripe's own
 * billing portal — RMSignage never stores card data.
 */
class CreateBillingPortalSession
{
    public function handle(User $user, Workspace $workspace): string
    {
        if (! $workspace->hasStripeId()) {
            throw ValidationException::withMessages([
                'billing' => 'This workspace does not have a billing account yet.',
            ]);
        }

        try {
            $url = $workspace->billingPortalUrl(route('app.settings.tab', ['tab' => 'billing']));
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'billing' => 'Stripe could not open the billing portal. Please try again.',
            ]);
        }

        AuditLogger::record(
            $user,
            'billing.portal_opened',
            'workspace',
            $workspace->id,
            $workspace->id,
            ['stripe_id' => $workspace->stripe_id],
        );

        return $url;
    }
}
