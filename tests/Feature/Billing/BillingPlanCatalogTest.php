<?php

use App\Enums\PlatformRole;
use App\Models\User;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use Laravel\Cashier\Subscription;

test('billing plan catalog exposes starter business and enterprise without pro', function () {
    $keys = array_column(BillingPlanCatalog::plans(), 'key');

    expect($keys)->toBe(['starter', 'business', 'enterprise'])
        ->and(BillingPlanCatalog::plan('pro'))->toBeNull()
        ->and(BillingPlanCatalog::plan('starter')['monthly_amount'])->toBe(1900)
        ->and(BillingPlanCatalog::plan('starter')['screen_limit'])->toBe(5)
        ->and(BillingPlanCatalog::plan('business')['monthly_amount'])->toBe(4900)
        ->and(BillingPlanCatalog::plan('business')['screen_limit'])->toBe(20)
        ->and(BillingPlanCatalog::plan('enterprise')['enterprise'])->toBeTrue();
});

test('admin subscription index includes plan catalog for super admin', function () {
    $admin = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/subscriptions')
            ->has('plans')
            ->where('can_manage_billing', true)
            ->where('plans.0.key', 'starter')
            ->where('plans.1.key', 'business')
        );
});

test('admin subscription update is forbidden for platform admin', function () {
    $platform = User::factory()->create([
        'platform_role' => PlatformRole::PlatformAdmin,
        'is_admin' => false,
    ]);
    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner);
    $workspace->forceFill(['stripe_id' => 'cus_test_admin'])->save();

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => BillingEntitlement::subscriptionType(),
        'stripe_id' => 'sub_test_admin',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 5,
    ]);

    $this->actingAs($platform)
        ->patch(route('admin.subscriptions.update', $subscription), [
            'plan' => 'business',
            'interval' => 'monthly',
            'confirm' => true,
        ])
        ->assertForbidden();
});
