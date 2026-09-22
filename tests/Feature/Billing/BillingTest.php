<?php

use App\Actions\Billing\SyncBillingInvoice;
use App\Actions\Deployments\PublishContentToScreens;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\BillingInvoice;
use App\Models\PairingSession;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;

function billingOwner(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function billingSubscription(Workspace $workspace, int $quantity = 1, string $status = 'active'): Subscription
{
    $workspace->forceFill(['stripe_id' => 'cus_test_'.$workspace->id])->save();

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => BillingEntitlement::subscriptionType(),
        'stripe_id' => 'sub_test_'.$workspace->id,
        'stripe_status' => $status,
        'stripe_price' => 'price_test_monthly',
        'quantity' => $quantity,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    $workspace->unsetRelation('subscriptions');

    return $subscription;
}

function billingConnectedScreen(Workspace $workspace, string $name = 'Lobby'): Screen
{
    $screen = Screen::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => $name,
    ]);

    ScreenDevice::factory()->forScreen($screen)->create();

    return $screen;
}

test('billing page is visible to the workspace owner', function () {
    [$user] = billingOwner();

    $this->actingAs($user)
        ->get(route('app.billing'))
        ->assertRedirect(route('app.settings.tab', ['tab' => 'billing']));

    $this->actingAs($user)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/index', false)
            ->where('tab', 'billing')
            ->where('summary.status', 'none')
            ->where('summary.licensed', 0)
            ->where('permissions.can_manage', true)
            ->where('invoices.meta.total', 0)
            ->where('stripe_configured', false)
            ->has('plans', 3)
            ->where('plans.0.key', 'starter')
            ->where('plans.0.monthly.formatted', '£19.00')
            ->where('plans.1.key', 'business')
            ->where('plans.1.monthly.formatted', '£49.00')
            ->where('plans.2.key', 'enterprise')
            ->has('comparison')
            ->has('prices')
            ->where('prices.0.formatted', '£19.00')
            ->where('prices.0.source', 'catalog')
            ->where('unavailable_message', fn ($message) => is_string($message) && str_contains($message, 'not configured'))
        );
});

test('billing page is read only for workspace admins and hidden from designers', function () {
    [$admin] = billingOwner(WorkspaceRole::Admin);

    $this->actingAs($admin)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.can_manage', false));

    $this->actingAs($admin)
        ->post(route('app.billing.checkout'), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertForbidden();

    [$designer] = billingOwner(WorkspaceRole::Designer);

    $this->actingAs($designer)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertForbidden();
});

test('billing page lists mirrored invoices for the workspace only', function () {
    [$user, $workspace] = billingOwner();

    BillingInvoice::factory()->create([
        'workspace_id' => $workspace->id,
        'number' => 'INV-OWNED',
    ]);

    BillingInvoice::factory()->create(['number' => 'INV-OTHER']);

    $this->actingAs($user)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoices.meta.total', 1)
            ->where('invoices.data.0.number', 'INV-OWNED'));
});

test('checkout is rejected when stripe is not configured', function () {
    [$user] = billingOwner();

    $this->actingAs($user)
        ->post(route('app.billing.checkout'), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertSessionHasErrors('billing');
});

test('checkout requires a plan key', function () {
    [$user] = billingOwner();

    $this->actingAs($user)
        ->post(route('app.billing.checkout'), ['interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
});

test('checkout rejects enterprise plan', function () {
    [$user] = billingOwner();

    config([
        'cashier.secret' => 'sk_test_checkout',
        'billing_plans.plans.starter.prices.monthly' => 'price_starter_monthly',
    ]);

    $this->actingAs($user)
        ->post(route('app.billing.checkout'), ['plan' => 'enterprise', 'interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
});

test('checkout rejects an unknown interval before touching stripe', function () {
    [$user] = billingOwner();

    $this->actingAs($user)
        ->post(route('app.billing.checkout'), ['plan' => 'starter', 'interval' => 'weekly'])
        ->assertSessionHasErrors('interval');
});

test('screen licences cannot drop below the connected screen count', function () {
    [$user, $workspace] = billingOwner();

    billingSubscription($workspace, 3);
    billingConnectedScreen($workspace, 'Lobby');
    billingConnectedScreen($workspace, 'Bar');

    $this->actingAs($user)
        ->patch(route('app.billing.quantity'), ['quantity' => 1])
        ->assertSessionHasErrors('quantity');

    expect(BillingEntitlement::usedScreenLicences($workspace->fresh()))->toBe(2);
});

test('licence changes require an active subscription', function () {
    [$user] = billingOwner();

    $this->actingAs($user)
        ->patch(route('app.billing.quantity'), ['quantity' => 4])
        ->assertSessionHasErrors('billing');
});

test('resume is rejected when there is no grace period', function () {
    [$user, $workspace] = billingOwner();

    billingSubscription($workspace, 2);

    $this->actingAs($user)
        ->post(route('app.billing.resume'))
        ->assertSessionHasErrors('billing');
});

test('entitlement counts connected screens regardless of network state', function () {
    [, $workspace] = billingOwner();

    billingSubscription($workspace, 5);
    billingConnectedScreen($workspace, 'Lobby');

    $revoked = Screen::factory()->create(['workspace_id' => $workspace->id]);
    ScreenDevice::factory()->forScreen($revoked)->revoked()->create();

    $workspace = $workspace->fresh();

    expect(BillingEntitlement::usedScreenLicences($workspace))->toBe(1)
        ->and(BillingEntitlement::licensedScreenCount($workspace))->toBe(5)
        ->and(BillingEntitlement::remainingScreenLicences($workspace))->toBe(4);
});

test('pairing is blocked when enforcement is on without a subscription', function () {
    config(['billing.enforce' => true]);

    [$user] = billingOwner();

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $created['public_id']), [
            'name' => 'Unlicensed Screen',
            'orientation' => 'landscape',
        ])
        ->assertSessionHasErrors('billing');

    // The pairing session must stay claimable so the customer can retry.
    $session = PairingSession::query()->where('public_id', $created['public_id'])->firstOrFail();

    expect(Screen::query()->where('name', 'Unlicensed Screen')->exists())->toBeFalse()
        ->and($session->claimed_at)->toBeNull()
        ->and($session->isClaimable())->toBeTrue();
});

test('pairing is blocked once every screen licence is in use', function () {
    config(['billing.enforce' => true]);

    [$user, $workspace] = billingOwner();
    billingSubscription($workspace, 1);
    billingConnectedScreen($workspace, 'Lobby');

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $created['public_id']), [
            'name' => 'Second Screen',
            'orientation' => 'landscape',
        ])
        ->assertSessionHasErrors('billing');

    expect(Screen::query()->where('name', 'Second Screen')->exists())->toBeFalse();
});

test('pairing succeeds within the purchased screen licences', function () {
    config(['billing.enforce' => true]);

    [$user, $workspace] = billingOwner();
    billingSubscription($workspace, 2);
    billingConnectedScreen($workspace, 'Lobby');

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $created['public_id']), [
            'name' => 'Licensed Screen',
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    expect(Screen::query()->where('name', 'Licensed Screen')->exists())->toBeTrue();
});

test('publishing is blocked without an entitlement', function () {
    config(['billing.enforce' => true]);

    [$user, $workspace] = billingOwner();
    $screen = billingConnectedScreen($workspace, 'Lobby');

    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    expect(fn () => app(PublishContentToScreens::class)
        ->publishDesign($user, $workspace->fresh(), $design, [$screen->id]))
        ->toThrow(ValidationException::class);
});

test('player manifest reports billing_required without an entitlement', function () {
    [$user] = billingOwner();

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $created['public_id']), [
            'name' => 'Player Billing Screen',
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $token = $this->getJson(route('player.api.pairing_sessions.show', $created['public_id']))
        ->assertOk()
        ->json('device_token');

    config(['billing.enforce' => true]);

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJsonPath('status', 'billing_required')
        ->assertJsonPath('contentSource', 'none');

    $this->withToken($token)
        ->getJson(route('player.api.manifest.check'))
        ->assertOk()
        ->assertJsonPath('status', 'billing_required')
        ->assertJsonPath('version', 'billing_required');
});

test('invoice sync is idempotent on the stripe invoice id', function () {
    [, $workspace] = billingOwner();

    $payload = [
        'id' => 'in_test_123',
        'customer' => 'cus_test_123',
        'number' => 'INV-0001',
        'status' => 'open',
        'currency' => 'gbp',
        'total' => 4900,
        'hosted_invoice_url' => 'https://invoice.stripe.com/test',
        'invoice_pdf' => null,
        'created' => now()->subDay()->getTimestamp(),
    ];

    $action = app(SyncBillingInvoice::class);

    $first = $action->handle($payload, $workspace);
    $second = $action->handle([...$payload, 'status' => 'paid'], $workspace);

    expect(BillingInvoice::query()->where('stripe_invoice_id', 'in_test_123')->count())->toBe(1)
        ->and($first->id)->toBe($second->id)
        ->and($second->status)->toBe('paid')
        ->and($second->currency)->toBe('GBP')
        ->and($second->workspace_id)->toBe($workspace->id);
});

test('invoice sync ignores payloads it cannot attribute to a workspace', function () {
    $action = app(SyncBillingInvoice::class);

    expect($action->handle(['id' => 'in_unknown', 'customer' => 'cus_missing']))->toBeNull()
        ->and($action->handle(['customer' => 'cus_missing']))->toBeNull()
        ->and(BillingInvoice::query()->count())->toBe(0);
});

test('admin subscriptions and invoices are read only platform surfaces', function () {
    [, $workspace] = billingOwner();
    billingSubscription($workspace, 4);
    billingConnectedScreen($workspace, 'Lobby');
    BillingInvoice::factory()->create([
        'workspace_id' => $workspace->id,
        'number' => 'INV-ADMIN',
    ]);

    $staff = User::factory()->create(['platform_role' => PlatformRole::PlatformAdmin]);

    $this->actingAs($staff)
        ->get(route('admin.subscriptions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/subscriptions')
            ->where('subscriptions.data.0.workspace.name', $workspace->name)
            ->where('subscriptions.data.0.quantity', 4)
            ->where('subscriptions.data.0.used', 1));

    $this->actingAs($staff)
        ->get(route('admin.invoices'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/invoices')
            ->where('invoices.data.0.number', 'INV-ADMIN'));

    $subscription = Subscription::query()->firstOrFail();

    $this->actingAs($staff)
        ->get(route('admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workspace.id', $workspace->id));
});

test('admin billing surfaces are forbidden for customers', function () {
    [$user] = billingOwner();

    $this->actingAs($user)->get(route('admin.subscriptions'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.invoices'))->assertForbidden();
});

test('admin workspace detail exposes a billing snapshot', function () {
    [, $workspace] = billingOwner();
    billingSubscription($workspace, 3);

    $staff = User::factory()->create(['platform_role' => PlatformRole::PlatformAdmin]);

    $this->actingAs($staff)
        ->get(route('admin.workspaces.show', $workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('billing.status', 'active')
            ->where('billing.licensed', 3)
            ->where('billing.subscription_url', route('admin.subscriptions.show', Subscription::query()->firstOrFail())));
});
