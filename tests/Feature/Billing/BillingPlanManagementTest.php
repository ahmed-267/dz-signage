<?php

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\WorkspaceBillingOverride;
use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use Database\Seeders\BillingPlanSeeder;
use Laravel\Cashier\Subscription;

beforeEach(function () {
    BillingPlanCatalog::clearCache();
});

function seedPlans(): void
{
    (new BillingPlanSeeder)->run();
    BillingPlanCatalog::clearCache();
}

function superAdmin(): User
{
    return User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
        'is_admin' => true,
    ]);
}

function platformAdmin(): User
{
    return User::factory()->create([
        'platform_role' => PlatformRole::PlatformAdmin,
        'is_admin' => false,
    ]);
}

test('billing plan seeder is idempotent and preserves admin edits', function () {
    seedPlans();

    expect(BillingPlan::query()->count())->toBe(3)
        ->and(BillingPlan::query()->where('key', 'pro')->exists())->toBeFalse();

    $starter = BillingPlan::query()->where('key', 'starter')->firstOrFail();
    $starter->forceFill(['monthly_amount' => 2100, 'name' => 'Starter Plus'])->save();
    BillingPlanCatalog::clearCache();

    (new BillingPlanSeeder)->run();
    BillingPlanCatalog::clearCache();

    $starter->refresh();

    expect($starter->monthly_amount)->toBe(2100)
        ->and($starter->name)->toBe('Starter Plus')
        ->and(BillingPlan::query()->count())->toBe(3);
});

test('billing plan catalog reads from database when seeded', function () {
    seedPlans();

    BillingPlan::query()->where('key', 'starter')->update(['monthly_amount' => 2500]);
    BillingPlanCatalog::clearCache();

    expect(BillingPlanCatalog::plan('starter')['monthly_amount'])->toBe(2500)
        ->and(BillingPlanCatalog::marketingPayload()['plans'][0]['monthly']['amount'])->toBe(2500);
});

test('super admin can update a plan and audit log is written', function () {
    seedPlans();
    $admin = superAdmin();
    $plan = BillingPlan::query()->where('key', 'starter')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.subscriptions.plans.update', $plan), [
            'name' => 'Starter',
            'description' => 'Updated tagline',
            'currency' => 'gbp',
            'monthly_amount' => 2000,
            'annual_amount' => 18000,
            'yearly_monthly_equivalent' => 1500,
            'monthly_stripe_price_id' => null,
            'annual_stripe_price_id' => null,
            'screen_limit' => 6,
            'storage_gb' => 200,
            'team_member_limit' => 5,
            'features' => ['full_template_library', 'brand_kit', 'basic_analytics'],
            'feature_labels' => ['6 connected Screens', 'Brand Kit'],
            'badge' => null,
            'popular' => false,
            'active' => true,
            'public' => true,
            'sort_order' => 1,
            'cta' => 'Get Started',
            'confirm' => true,
        ])
        ->assertRedirect(route('admin.subscriptions.plans.edit', $plan));

    $plan->refresh();
    BillingPlanCatalog::clearCache();

    expect($plan->description)->toBe('Updated tagline')
        ->and($plan->monthly_amount)->toBe(2000)
        ->and($plan->screen_limit)->toBe(6)
        ->and(BillingPlanCatalog::plan('starter')['screen_limit'])->toBe(6)
        ->and(AuditLog::query()->where('action', 'billing_plan.updated')->exists())->toBeTrue();
});

test('platform admin can view plans but cannot update', function () {
    seedPlans();
    $admin = platformAdmin();
    $plan = BillingPlan::query()->where('key', 'business')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.plans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/plans/index')
            ->where('can_manage', false)
            ->has('plans', 3)
        );

    $this->actingAs($admin)
        ->put(route('admin.subscriptions.plans.update', $plan), [
            'name' => 'Hacked',
            'description' => 'nope',
            'currency' => 'gbp',
            'monthly_amount' => 1,
            'annual_amount' => 1,
            'yearly_monthly_equivalent' => 1,
            'screen_limit' => 1,
            'storage_gb' => 1,
            'team_member_limit' => 1,
            'features' => [],
            'feature_labels' => [],
            'popular' => false,
            'active' => true,
            'public' => true,
            'sort_order' => 2,
            'cta' => 'Get Started',
            'confirm' => true,
        ])
        ->assertForbidden();

    expect($plan->fresh()->name)->toBe('Business');
});

test('feature and limit propagation respects plan and workspace overrides', function () {
    seedPlans();
    config(['billing.enforce' => true]);

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner);
    $workspace->forceFill(['stripe_id' => 'cus_plan_feat'])->save();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => BillingEntitlement::subscriptionType(),
        'stripe_id' => 'sub_plan_feat',
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_test',
        'quantity' => 5,
    ]);

    BillingPlan::query()->where('key', 'starter')->update([
        'monthly_stripe_price_id' => 'price_starter_test',
        'features' => ['basic_analytics', 'brand_kit'],
        'team_member_limit' => 5,
        'storage_gb' => 200,
    ]);
    BillingPlanCatalog::clearCache();

    expect(BillingEntitlement::hasFeature($workspace, 'advanced_analytics'))->toBeFalse()
        ->and(BillingEntitlement::hasFeature($workspace, 'brand_kit'))->toBeTrue()
        ->and(BillingEntitlement::teamLimit($workspace))->toBe(5)
        ->and(BillingEntitlement::storageLimitBytes($workspace))->toBe(200 * 1024 * 1024 * 1024);

    WorkspaceBillingOverride::query()->create([
        'workspace_id' => $workspace->id,
        'team_limit' => 25,
        'storage_gb' => 800,
        'features' => ['advanced_analytics', 'ai_text'],
    ]);
    $workspace->unsetRelation('billingOverride');

    expect(BillingEntitlement::teamLimit($workspace))->toBe(25)
        ->and(BillingEntitlement::storageLimitBytes($workspace))->toBe(800 * 1024 * 1024 * 1024)
        ->and(BillingEntitlement::hasFeature($workspace, 'advanced_analytics'))->toBeTrue()
        ->and(BillingEntitlement::hasFeature($workspace, 'ai_text'))->toBeTrue();
});

test('local amount edit without stripe does not require price ids', function () {
    seedPlans();
    config(['cashier.secret' => null]);

    $admin = superAdmin();
    $plan = BillingPlan::query()->where('key', 'business')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.subscriptions.plans.update', $plan), [
            'name' => 'Business',
            'description' => $plan->description,
            'currency' => 'gbp',
            'monthly_amount' => 5200,
            'annual_amount' => 46800,
            'yearly_monthly_equivalent' => 3900,
            'monthly_stripe_price_id' => null,
            'annual_stripe_price_id' => null,
            'screen_limit' => 20,
            'storage_gb' => 500,
            'team_member_limit' => 15,
            'features' => $plan->features,
            'feature_labels' => $plan->feature_labels,
            'badge' => $plan->badge,
            'popular' => true,
            'active' => true,
            'public' => true,
            'sort_order' => 2,
            'cta' => 'Get Started',
            'confirm' => true,
        ])
        ->assertRedirect();

    $plan->refresh();

    expect($plan->monthly_amount)->toBe(5200)
        ->and($plan->monthly_stripe_price_id)->toBeNull()
        ->and($plan->stripeSynced())->toBeFalse();
});

test('price change does not auto-migrate existing subscribers', function () {
    seedPlans();
    $admin = superAdmin();
    $plan = BillingPlan::query()->where('key', 'starter')->firstOrFail();
    $plan->forceFill([
        'monthly_stripe_price_id' => 'price_old_monthly',
        'legacy_stripe_price_ids' => [],
    ])->save();

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner);
    $workspace->forceFill(['stripe_id' => 'cus_migrate'])->save();

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => BillingEntitlement::subscriptionType(),
        'stripe_id' => 'sub_migrate',
        'stripe_status' => 'active',
        'stripe_price' => 'price_old_monthly',
        'quantity' => 5,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.subscriptions.plans.update', $plan), [
            'name' => 'Starter',
            'description' => $plan->description,
            'currency' => 'gbp',
            'monthly_amount' => 2100,
            'annual_amount' => 18000,
            'yearly_monthly_equivalent' => 1500,
            'monthly_stripe_price_id' => 'price_old_monthly',
            'annual_stripe_price_id' => null,
            'screen_limit' => 5,
            'storage_gb' => 200,
            'team_member_limit' => 5,
            'features' => $plan->features,
            'feature_labels' => $plan->feature_labels,
            'badge' => null,
            'popular' => false,
            'active' => true,
            'public' => true,
            'sort_order' => 1,
            'cta' => 'Get Started',
            'confirm' => true,
        ])
        ->assertRedirect();

    expect($subscription->fresh()->stripe_price)->toBe('price_old_monthly')
        ->and(AuditLog::query()->where('action', 'billing_plan.subscribers_migrated')->exists())->toBeFalse();
});

test('public pricing reflects plan update after cache clear', function () {
    seedPlans();

    BillingPlan::query()->where('key', 'starter')->update([
        'monthly_amount' => 2200,
        'feature_labels' => ['Updated marketing label'],
    ]);
    BillingPlanCatalog::clearCache();

    $payload = BillingPlanCatalog::marketingPayload();

    expect($payload['plans'][0]['key'])->toBe('starter')
        ->and($payload['plans'][0]['monthly']['amount'])->toBe(2200)
        ->and($payload['plans'][0]['feature_labels'][0])->toBe('Updated marketing label');

    $owner = User::factory()->create();
    attachWorkspace($owner);

    $this->actingAs($owner)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.monthly.amount', 2200)
        );
});

test('admin plans index seeds empty catalog for staff', function () {
    $admin = superAdmin();

    expect(BillingPlan::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.plans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/plans/index')
            ->has('plans', 3)
            ->where('can_manage', true)
        );
});
