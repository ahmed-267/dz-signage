<?php

namespace App\Actions\Billing;

use App\Models\BillingInvoice;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Mirror a Stripe invoice into `billing_invoices` so customers and platform
 * staff can see billing history without a live Stripe call.
 *
 * Stripe retries webhooks, so this is idempotent on `stripe_invoice_id`.
 */
class SyncBillingInvoice
{
    /**
     * @param  array<string, mixed>  $invoice  Stripe invoice object as an array
     */
    public function handle(array $invoice, ?Workspace $workspace = null): ?BillingInvoice
    {
        $stripeInvoiceId = isset($invoice['id']) ? (string) $invoice['id'] : '';

        if ($stripeInvoiceId === '') {
            return null;
        }

        $workspace ??= $this->resolveWorkspace($invoice);

        if ($workspace === null) {
            return null;
        }

        return BillingInvoice::query()->updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoiceId],
            [
                'workspace_id' => $workspace->id,
                'number' => isset($invoice['number']) ? (string) $invoice['number'] : null,
                'status' => isset($invoice['status']) ? (string) $invoice['status'] : 'unknown',
                'currency' => isset($invoice['currency']) ? strtoupper((string) $invoice['currency']) : null,
                'total' => (int) ($invoice['total'] ?? 0),
                'hosted_invoice_url' => isset($invoice['hosted_invoice_url']) ? (string) $invoice['hosted_invoice_url'] : null,
                'invoice_pdf' => isset($invoice['invoice_pdf']) ? (string) $invoice['invoice_pdf'] : null,
                'billed_at' => $this->billedAt($invoice),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function resolveWorkspace(array $invoice): ?Workspace
    {
        $customer = $invoice['customer'] ?? null;

        if (! is_string($customer) || $customer === '') {
            return null;
        }

        return Workspace::query()->where('stripe_id', $customer)->first();
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function billedAt(array $invoice): ?Carbon
    {
        $transitions = is_array($invoice['status_transitions'] ?? null) ? $invoice['status_transitions'] : [];

        $timestamp = $transitions['paid_at']
            ?? $transitions['finalized_at']
            ?? $invoice['effective_at']
            ?? $invoice['created']
            ?? null;

        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }
}
