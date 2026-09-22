<?php

namespace App\Models;

use Database\Factories\BillingInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $stripe_invoice_id
 * @property string|null $number
 * @property string $status
 * @property string|null $currency
 * @property int $total
 * @property string|null $hosted_invoice_url
 * @property string|null $invoice_pdf
 * @property Carbon|null $billed_at
 */
class BillingInvoice extends Model
{
    /** @use HasFactory<BillingInvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'stripe_invoice_id',
        'number',
        'status',
        'currency',
        'total',
        'hosted_invoice_url',
        'invoice_pdf',
        'billed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'billed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
