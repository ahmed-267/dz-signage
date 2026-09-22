<?php

namespace App\Http\Controllers\App;

use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\CreateBillingPortalSession;
use App\Actions\Billing\ResumeSubscription;
use App\Actions\Billing\StartCheckout;
use App\Actions\Billing\UpdateScreenLicenceQuantity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workspace billing: named plans, Stripe Checkout, billing portal and the
 * mirrored invoice history. Entitlement questions are answered only by
 * App\Support\Billing\BillingEntitlement.
 */
class BillingController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        return redirect()->route(
            'app.settings.tab',
            array_merge(['tab' => 'billing'], $request->query()),
        );
    }

    public function checkout(Request $request, StartCheckout $action): Response
    {
        [$user, $workspace] = $this->manageContext($request);

        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(['starter', 'business'])],
            'interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
        ]);

        $url = $action->handle($user, $workspace, $data['plan'], $data['interval']);

        return Inertia::location($url);
    }

    public function portal(Request $request, CreateBillingPortalSession $action): Response
    {
        [$user, $workspace] = $this->manageContext($request);

        return Inertia::location($action->handle($user, $workspace));
    }

    public function updateQuantity(Request $request, UpdateScreenLicenceQuantity $action): RedirectResponse
    {
        [$user, $workspace] = $this->manageContext($request);

        $data = $request->validate([
            'quantity' => ['required', 'integer'],
        ]);

        $subscription = $action->handle($user, $workspace, (int) $data['quantity']);

        return redirect()
            ->route('app.settings.tab', ['tab' => 'billing'])
            ->with('success', 'TV licences updated to '.$subscription->quantity.'.');
    }

    public function cancel(Request $request, CancelSubscription $action): RedirectResponse
    {
        [$user, $workspace] = $this->manageContext($request);

        $subscription = $action->handle($user, $workspace);

        $endsAt = $subscription->ends_at?->toFormattedDateString();

        return redirect()
            ->route('app.settings.tab', ['tab' => 'billing'])
            ->with('success', $endsAt === null
                ? 'Subscription canceled.'
                : "Subscription canceled. TVs keep playing until {$endsAt}.");
    }

    public function resume(Request $request, ResumeSubscription $action): RedirectResponse
    {
        [$user, $workspace] = $this->manageContext($request);

        $action->handle($user, $workspace);

        return redirect()
            ->route('app.settings.tab', ['tab' => 'billing'])
            ->with('success', 'Subscription resumed.');
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
     * @return array{0: User, 1: Workspace}
     */
    private function manageContext(Request $request): array
    {
        [$user, $workspace] = $this->context($request);

        abort_unless($user->roleIn($workspace)?->canManageBilling() ?? false, 403);

        return [$user, $workspace];
    }
}
