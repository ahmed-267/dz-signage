<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('app.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the customer dashboard', function () {
    $user = User::factory()->create();
    attachWorkspace($user);

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->has('recentDeployments')
            ->has('recentScreens')
            ->has('contentActivity')
        );
});

test('unverified users are redirected to email verification', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('authenticated users without a workspace are redirected to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

test('app root redirects to the customer dashboard', function () {
    $user = User::factory()->create();
    attachWorkspace($user);

    $this->actingAs($user)
        ->get('/app')
        ->assertRedirect('/app/dashboard');
});
