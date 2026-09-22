<?php

namespace App\Http\Controllers\App;

use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\BillingInvoice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\CountryCatalog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Customer Settings hub: General (profile), Workspace, Billing, Security.
 */
class SettingsController extends Controller
{
    public const TABS = ['general', 'workspace', 'billing', 'security'];

    public function show(Request $request, ?string $tab = null): InertiaResponse
    {
        $tab = $tab ?: 'general';
        abort_unless(in_array($tab, ['general', 'workspace', 'billing'], true), 404);

        return $this->render($request, $tab);
    }

    public function security(TwoFactorAuthenticationRequest $request): InertiaResponse
    {
        return $this->render($request, 'security', app(SecurityController::class)->props($request));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(Request $request, string $tab, array $extra = []): InertiaResponse
    {
        [$user, $workspace] = $this->context($request);
        $role = $user->roleIn($workspace);

        $canManageWorkspace = $role?->canManageWorkspace() ?? false;
        $canViewBilling = $role?->canViewBilling() ?? false;

        if ($tab === 'workspace') {
            abort_unless($canManageWorkspace, 403);
        }

        if ($tab === 'billing') {
            abort_unless($canViewBilling, 403);
        }

        $props = [
            'tab' => $tab,
            'availableTabs' => [
                'general' => true,
                'workspace' => $canManageWorkspace,
                'billing' => $canViewBilling,
                'security' => true,
            ],
            ...$extra,
        ];

        $props = match ($tab) {
            'general' => array_merge($props, $this->generalProps($request)),
            'workspace' => array_merge($props, $this->workspaceProps($workspace)),
            'billing' => array_merge($props, $this->billingProps($request, $user, $workspace)),
            'security' => $props,
            default => $props,
        };

        return Inertia::render('app/settings/index', $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function generalProps(Request $request): array
    {
        return [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceProps(Workspace $workspace): array
    {
        return [
            'workspaceSettings' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'industry' => $workspace->industry->value,
                'country' => $workspace->country,
                'timezone' => $workspace->timezone,
                'logo_url' => $workspace->logoUrl(),
            ],
            'industries' => collect(WorkspaceIndustry::cases())->map(fn ($i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
            'timezones' => timezone_identifiers_list(),
            'countries' => CountryCatalog::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingProps(Request $request, User $user, Workspace $workspace): array
    {
        $role = $user->roleIn($workspace);
        abort_unless($role?->canViewBilling() ?? false, 403);

        $invoices = BillingInvoice::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('billed_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $checkout = (string) $request->query('checkout', '');
        $catalog = BillingPlanCatalog::marketingPayload();
        $stripeConfigured = BillingEntitlement::stripeReady();

        return [
            'summary' => BillingEntitlement::summary($workspace),
            'plans' => $catalog['plans'],
            'comparison' => $catalog['comparison'],
            'prices' => BillingEntitlement::availablePriceDisplays(),
            'licence_limits' => [
                'min' => BillingEntitlement::minLicenses(),
                'max' => BillingEntitlement::maxLicenses(),
            ],
            'stripe_configured' => $stripeConfigured,
            'unavailable_message' => $stripeConfigured
                ? null
                : 'Stripe test billing is not configured for this environment. Pricing below uses the catalog amounts from configuration.',
            'invoices' => [
                'data' => $invoices->getCollection()
                    ->map(fn (BillingInvoice $invoice) => $this->invoiceRow($invoice))
                    ->values(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
                'links' => [
                    'prev' => $invoices->previousPageUrl(),
                    'next' => $invoices->nextPageUrl(),
                ],
            ],
            'permissions' => [
                'can_manage' => $role->canManageBilling(),
            ],
            'checkout' => in_array($checkout, ['success', 'cancelled'], true) ? $checkout : null,
        ];
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function context(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);

        return [$user, $workspace];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(BillingInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'total' => $invoice->total,
            'total_formatted' => BillingEntitlement::formatAmount($invoice->total, $invoice->currency),
            'hosted_invoice_url' => $invoice->hosted_invoice_url,
            'invoice_pdf' => $invoice->invoice_pdf,
            'billed_at' => $invoice->billed_at?->toIso8601String(),
        ];
    }
}
