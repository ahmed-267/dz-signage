<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Seed the cached Stripe price so the configured branch can be exercised
 * without calling Stripe.
 */
function fakeLandingPrice(string $interval, string $priceId, int $amount): void
{
    config([
        'cashier.secret' => 'sk_test_landing',
        "billing.prices.{$interval}" => $priceId,
        "billing_plans.plans.starter.prices.{$interval}" => $priceId,
    ]);

    Cache::put("billing.price.{$interval}.{$priceId}", [
        'interval' => $interval,
        'price_id' => $priceId,
        'unit_amount' => $amount,
        'currency' => 'GBP',
        'formatted' => '£'.number_format($amount / 100, 2),
        'source' => 'stripe',
    ]);
}

test('the public landing page renders the marketing home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketing/home')
            ->has('seo.title')
            ->has('seo.description')
            ->has('planCatalog.plans', 3)
            ->where('planCatalog.plans.0.key', 'starter')
            ->where('planCatalog.plans.0.monthly.formatted', '£19.00')
            ->where('planCatalog.plans.1.key', 'business')
            ->where('planCatalog.plans.1.monthly.formatted', '£49.00')
            ->where('planCatalog.plans.2.key', 'enterprise')
            ->has('planCatalog.comparison')
            ->where('pricing.configured', true)
            ->has('pricing.prices')
        );
});

test('guests see the registration call to action', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canRegister', true)
            ->where('auth.user', null)
        );
});

test('authenticated visitors are given the dashboard call to action path', function () {
    $user = User::factory()->create();
    attachWorkspace($user);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketing/home')
            ->where('auth.user.id', $user->id)
        );
});

test('the landing page never exposes stripe credentials', function () {
    fakeLandingPrice('monthly', 'price_landing_monthly', 1900);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricing.configured', true)
            ->missing('pricing.prices.0.price_id')
            ->missing('planCatalog.plans.0.prices')
        );
});

test('configured billing still passes legacy starter prices for compat', function () {
    fakeLandingPrice('monthly', 'price_landing_monthly', 1900);
    fakeLandingPrice('yearly', 'price_landing_yearly', 18000);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricing.configured', true)
            ->has('pricing.prices', 2)
            ->where('pricing.prices.0.interval', 'monthly')
            ->where('pricing.prices.0.formatted', '£19.00')
            ->where('pricing.prices.0.unit_amount', 1900)
            ->where('pricing.prices.0.currency', 'GBP')
            ->where('pricing.prices.1.interval', 'yearly')
            ->where('pricing.prices.1.formatted', '£180.00')
            ->where('planCatalog.plans.0.monthly.formatted', '£19.00')
            ->where('planCatalog.plans.1.monthly.formatted', '£49.00')
        );
});

test('the licence range stays inside the marketing calculator bounds', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricing.min_licenses', fn (int $min) => $min >= 1)
            ->where('pricing.max_licenses', fn (int $max) => $max > 1 && $max <= 50)
            ->etc()
        );
});

test('plan catalog has no pro plan', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('planCatalog.plans', fn ($plans) => collect($plans)->every(
                fn ($plan) => ($plan['key'] ?? null) !== 'pro'
            ))
        );
});
