<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function settingsUser(WorkspaceRole $role = WorkspaceRole::Owner): User
{
    $user = User::factory()->create();
    attachWorkspace($user, null, $role);

    return $user;
}

test('settings hub defaults to the general tab', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/settings/index')
            ->where('tab', 'general')
            ->where('availableTabs.general', true)
            ->where('availableTabs.workspace', true)
            ->where('availableTabs.billing', true)
            ->where('availableTabs.security', true)
            ->where('mustVerifyEmail', true));
});

test('settings hub shows workspace and billing tabs by permission', function () {
    $owner = settingsUser(WorkspaceRole::Owner);
    $designer = settingsUser(WorkspaceRole::Designer);

    $this->actingAs($owner)
        ->get(route('app.settings.tab', ['tab' => 'workspace']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/settings/index')
            ->where('tab', 'workspace')
            ->has('workspaceSettings.name'));

    $this->actingAs($owner)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/settings/index')
            ->where('tab', 'billing')
            ->where('summary.status', 'none')
            ->where('permissions.can_manage', true));

    $this->actingAs($designer)
        ->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs.workspace', false)
            ->where('availableTabs.billing', false)
            ->where('availableTabs.general', true)
            ->where('availableTabs.security', true));

    $this->actingAs($designer)
        ->get(route('app.settings.tab', ['tab' => 'billing']))
        ->assertForbidden();

    $this->actingAs($designer)
        ->get(route('app.settings.tab', ['tab' => 'workspace']))
        ->assertForbidden();
});

test('legacy billing and workspace settings urls redirect into the hub', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->get(route('app.billing'))
        ->assertRedirect(route('app.settings.tab', ['tab' => 'billing']));

    $this->actingAs($user)
        ->get(route('app.workspace_settings.edit'))
        ->assertRedirect(route('app.settings.tab', ['tab' => 'workspace']));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('app.settings.tab', ['tab' => 'general']));

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('app.settings.security'));
});

test('settings security tab requires password confirmation', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->get(route('app.settings.security'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('app.settings.security'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/settings/index')
            ->where('tab', 'security')
            ->has('passwordRules'));
});
