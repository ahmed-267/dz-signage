<?php

namespace Database\Factories;

use App\Models\BillingInvoice;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingInvoice>
 */
class BillingInvoiceFactory extends Factory
{
    protected $model = BillingInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'stripe_invoice_id' => 'in_'.fake()->unique()->bothify('????????????????'),
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'status' => 'paid',
            'currency' => 'gbp',
            'total' => 4900,
            'hosted_invoice_url' => 'https://invoice.stripe.com/test',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/test/pdf',
            'billed_at' => now(),
        ];
    }
}
