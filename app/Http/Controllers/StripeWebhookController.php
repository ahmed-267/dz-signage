<?php

namespace App\Http\Controllers;

use App\Actions\Billing\SyncBillingInvoice;
use App\Models\Workspace;
use App\Support\Platform\AuditLogger;
use Laravel\Cashier\Http\Controllers\WebhookController;

/**
 * Cashier keeps `subscriptions` in sync; this controller adds the RMSignage
 * invoice mirror (`billing_invoices`) and platform audit entries on top.
 *
 * Cashier registers POST {cashier.path}/webhook itself — AppServiceProvider
 * binds Cashier's controller to this subclass so that route resolves here.
 */
class StripeWebhookController extends WebhookController
{
    public function __construct(private readonly SyncBillingInvoice $syncInvoice)
    {
        parent::__construct();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaid(array $payload): mixed
    {
        $this->mirrorInvoice($payload);

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentFailed(array $payload): mixed
    {
        $this->mirrorInvoice($payload);

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentSucceeded(array $payload): mixed
    {
        $this->mirrorInvoice($payload);

        return parent::handleInvoicePaymentSucceeded($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): mixed
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->auditSubscription($payload, 'billing.subscription_updated');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): mixed
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $this->auditSubscription($payload, 'billing.subscription_deleted');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mirrorInvoice(array $payload): void
    {
        $invoice = $payload['data']['object'] ?? null;

        if (is_array($invoice)) {
            $this->syncInvoice->handle($invoice);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function auditSubscription(array $payload, string $action): void
    {
        $data = $payload['data']['object'] ?? [];

        if (! is_array($data)) {
            return;
        }

        $workspace = $this->workspaceFor($data['customer'] ?? null);

        if ($workspace === null) {
            return;
        }

        $firstItem = $data['items']['data'][0] ?? [];

        AuditLogger::record(
            null,
            $action,
            'workspace',
            $workspace->id,
            $workspace->id,
            [
                'subscription_id' => isset($data['id']) ? (string) $data['id'] : null,
                'stripe_status' => isset($data['status']) ? (string) $data['status'] : null,
                'quantity' => is_array($firstItem) ? ($firstItem['quantity'] ?? null) : null,
                'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
                'source' => 'stripe_webhook',
            ],
        );
    }

    private function workspaceFor(mixed $customerId): ?Workspace
    {
        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return Workspace::query()->where('stripe_id', $customerId)->first();
    }
}
