<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\AdminChangeWorkspaceSubscription;
use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\ListPagination;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;

/**
 * Platform view of Workspace subscriptions. Super Admin may change plan/interval
 * through Cashier → Stripe; failed provider calls never rewrite local state alone.
 */
class SubscriptionController extends Controller
{
    private const STATUSES = [
        'active',
        'trialing',
        'past_due',
        'canceled',
        'incomplete',
        'incomplete_expired',
        'unpaid',
        'paused',
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $sort = ListPagination::sort(
            $request,
            ['status', 'quantity', 'ends', 'created'],
            'created',
            'desc',
        );

        $query = Subscription::query()
            ->with(['owner.ownerMembership.user:id,name,email']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term, $q): void {
                $inner->whereHas('owner', function ($workspace) use ($term): void {
                    $workspace->where('name', 'ilike', $term)
                        ->orWhere('slug', 'ilike', $term)
                        ->orWhere('stripe_id', 'ilike', $term);
                })->orWhere('stripe_id', 'ilike', '%'.$q.'%');
            });
        }

        if ($status !== 'all' && in_array($status, self::STATUSES, true)) {
            $query->where('stripe_status', $status);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'status' => $query->orderBy('stripe_status', $direction)->orderByDesc('id'),
            'quantity' => $query->orderBy('quantity', $direction)->orderByDesc('id'),
            'ends' => $query->orderBy('ends_at', $direction)->orderByDesc('id'),
            default => $query->orderBy('created_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 20);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Subscription $subscription) => $this->row($subscription))
                ->values(),
        );

        return Inertia::render('admin/billing/subscriptions', [
            'title' => 'Subscriptions',
            'description' => BillingEntitlement::stripeReady()
                ? 'Workspace subscriptions synced from Stripe. Super Admins can change Starter/Business plans.'
                : 'Plan catalog is visible. Stripe is not configured, so live subscription mutations are unavailable.',
            'billing_unavailable' => ! BillingEntitlement::stripeReady(),
            'kind' => 'subscriptions',
            'configured' => BillingEntitlement::stripeReady(),
            'enforced' => BillingEntitlement::enforce(),
            'plans' => BillingPlanCatalog::marketingPayload()['plans'],
            'comparison' => BillingPlanCatalog::comparison(),
            'can_manage_billing' => PlatformPermissions::canManageCustomerBilling($request->user()),
            'subscriptions' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'statuses' => array_map(
                fn (string $value) => [
                    'value' => $value,
                    'label' => BillingEntitlement::statusLabel($value),
                ],
                self::STATUSES,
            ),
        ]);
    }

    public function show(Request $request, Subscription $subscription): Response
    {
        $subscription->load(['owner.ownerMembership.user:id,name,email', 'items']);

        /** @var Workspace|null $workspace */
        $workspace = $subscription->owner;
        abort_unless($workspace instanceof Workspace, 404);

        $invoices = BillingInvoice::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('billed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (BillingInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'total' => $invoice->total,
                'total_formatted' => BillingEntitlement::formatAmount($invoice->total, $invoice->currency),
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'billed_at' => $invoice->billed_at?->toIso8601String(),
            ])
            ->values();

        $items = [];
        foreach ($subscription->items as $item) {
            $items[] = [
                'id' => (int) $item->getAttribute('id'),
                'stripe_id' => (string) $item->getAttribute('stripe_id'),
                'stripe_price' => (string) $item->getAttribute('stripe_price'),
                'quantity' => $item->getAttribute('quantity'),
            ];
        }

        return Inertia::render('admin/billing/subscriptions', [
            'title' => 'Subscription',
            'description' => 'Subscription detail for '.$workspace->name.'.',
            'billing_unavailable' => ! BillingEntitlement::stripeReady(),
            'kind' => 'subscription',
            'plans' => BillingPlanCatalog::marketingPayload()['plans'],
            'can_manage_billing' => PlatformPermissions::canManageCustomerBilling($request->user()),
            'subscription' => [
                ...$this->row($subscription),
                'items' => $items,
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'billing' => BillingEntitlement::summary($workspace),
            ],
            'invoices' => $invoices,
        ]);
    }

    public function update(
        Request $request,
        Subscription $subscription,
        AdminChangeWorkspaceSubscription $action,
    ): RedirectResponse {
        abort_unless(PlatformPermissions::canManageCustomerBilling($request->user()), 403);

        $subscription->load('owner');
        /** @var Workspace|null $workspace */
        $workspace = $subscription->owner;
        abort_unless($workspace instanceof Workspace, 404);

        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(['starter', 'business'])],
            'interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'confirm' => ['accepted'],
        ]);

        $action->handle($request->user(), $workspace, $data['plan'], $data['interval']);

        return redirect()
            ->route('admin.subscriptions.show', $subscription)
            ->with('success', 'Subscription updated in Stripe.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Subscription $subscription): array
    {
        /** @var Workspace|null $workspace */
        $workspace = $subscription->owner;

        $status = $workspace instanceof Workspace
            ? BillingEntitlement::status($workspace)
            : (string) $subscription->stripe_status;

        return [
            'id' => $subscription->id,
            'stripe_id' => $subscription->stripe_id,
            'stripe_status' => $subscription->stripe_status,
            'status' => $status,
            'status_label' => BillingEntitlement::statusLabel($status),
            'type' => $subscription->type,
            'interval' => BillingEntitlement::intervalForPriceId($subscription->stripe_price),
            'plan_key' => $workspace instanceof Workspace
                ? BillingEntitlement::currentPlanKey($workspace)
                : BillingPlanCatalog::planKeyForPriceId($subscription->stripe_price),
            'plan_name' => $workspace instanceof Workspace
                ? (BillingEntitlement::currentPlan($workspace)['name'] ?? null)
                : null,
            'stripe_price' => $subscription->stripe_price,
            'quantity' => $subscription->quantity,
            'licensed' => $workspace instanceof Workspace
                ? BillingEntitlement::licensedScreenCount($workspace)
                : 0,
            'used' => $workspace instanceof Workspace
                ? BillingEntitlement::usedScreenLicences($workspace)
                : 0,
            'on_grace_period' => $subscription->onGracePeriod(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
            'workspace' => $workspace instanceof Workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'stripe_id' => $workspace->stripe_id,
                'owner_name' => $workspace->ownerMembership?->user?->name,
                'owner_email' => $workspace->ownerMembership?->user?->email,
            ] : null,
        ];
    }
}
