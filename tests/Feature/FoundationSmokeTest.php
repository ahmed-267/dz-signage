<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

test('the application boots with expected configuration', function () {
    expect(app()->isBooted())->toBeTrue();
    expect(config('app.name'))->toBe('DZ Signage');
    expect(config('database.default'))->toBe('pgsql');
});

test('the public homepage responds successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('the login page responds successfully', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

test('the registration page responds when registration is enabled', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is disabled.');
    }

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/register'));
});

test('unauthenticated users cannot access the customer dashboard', function () {
    $this->get(route('app.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated normal users can access the customer dashboard', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);
    attachWorkspace($user);

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/dashboard'));
});

test('normal users cannot access the admin dashboard', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('postgresql backed persistence works for users', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $user = User::factory()->create([
        'email' => 'postgres-smoke@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'postgres-smoke@example.com',
        'is_admin' => false,
    ]);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});
