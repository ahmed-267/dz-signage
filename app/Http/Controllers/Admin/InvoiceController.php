<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Support\Billing\BillingEntitlement;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only platform view of the Stripe invoices mirrored by
 * App\Actions\Billing\SyncBillingInvoice.
 */
class InvoiceController extends Controller
{
    private const STATUSES = ['draft', 'open', 'paid', 'uncollectible', 'void'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $sort = ListPagination::sort(
            $request,
            ['number', 'billed', 'total', 'status'],
            'billed',
            'desc',
        );

        $query = BillingInvoice::query()->with('workspace:id,name,slug');

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('number', 'ilike', $term)
                    ->orWhere('stripe_invoice_id', 'ilike', $term)
                    ->orWhereHas('workspace', function ($workspace) use ($term): void {
                        $workspace->where('name', 'ilike', $term)
                            ->orWhere('slug', 'ilike', $term);
                    });
            });
        }

        if ($status !== 'all' && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'number' => $query->orderBy('number', $direction)->orderByDesc('id'),
            'total' => $query->orderBy('total', $direction)->orderByDesc('id'),
            'status' => $query->orderBy('status', $direction)->orderByDesc('id'),
            default => $query->orderBy('billed_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 20);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (BillingInvoice $invoice) => [
                    'id' => $invoice->id,
                    'stripe_invoice_id' => $invoice->stripe_invoice_id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'currency' => $invoice->currency,
                    'total' => $invoice->total,
                    'total_formatted' => BillingEntitlement::formatAmount($invoice->total, $invoice->currency),
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'billed_at' => $invoice->billed_at?->toIso8601String(),
                    'workspace' => $invoice->workspace === null ? null : [
                        'id' => $invoice->workspace->id,
                        'name' => $invoice->workspace->name,
                        'slug' => $invoice->workspace->slug,
                    ],
                ])
                ->values(),
        );

        return Inertia::render('admin/billing/invoices', [
            'title' => 'Invoices',
            'description' => BillingEntitlement::isConfigured()
                ? 'Read-only view of Stripe invoices mirrored into RMSignage.'
                : 'Stripe is not configured for this environment, so no invoices can exist yet.',
            // Truthful: without Stripe keys/prices there is nothing to show.
            'billing_unavailable' => ! BillingEntitlement::isConfigured(),
            'kind' => 'invoices',
            'configured' => BillingEntitlement::isConfigured(),
            'invoices' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'statuses' => array_map(
                fn (string $value) => ['value' => $value, 'label' => ucfirst($value)],
                self::STATUSES,
            ),
        ]);
    }
}
