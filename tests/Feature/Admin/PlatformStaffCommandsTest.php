<?php

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Support\Facades\Hash;

const STAFF_PASSWORD = 'Staff-Passw0rd!Secure';

test('create-super-admin creates verified staff without business membership', function () {
    $this->artisan('rmsignage:create-super-admin', [
        '--name' => 'Ahmed',
        '--email' => 'super@example.com',
        '--force' => true,
    ])
        ->expectsQuestion('Password (hidden)', STAFF_PASSWORD)
        ->expectsQuestion('Confirm password (hidden)', STAFF_PASSWORD)
        ->assertSuccessful();

    $user = User::query()->where('email', 'super@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ahmed')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->platformRole())->toBe(PlatformRole::SuperAdmin)
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check(STAFF_PASSWORD, $user->password))->toBeTrue()
        ->and($user->workspaceMemberships()->count())->toBe(0);
});

test('create-platform-admin creates verified operational staff without business membership', function () {
    $this->artisan('rmsignage:create-platform-admin', [
        '--name' => 'Sarah',
        '--email' => 'platform@example.com',
        '--force' => true,
    ])
        ->expectsQuestion('Password (hidden)', STAFF_PASSWORD)
        ->expectsQuestion('Confirm password (hidden)', STAFF_PASSWORD)
        ->assertSuccessful();

    $user = User::query()->where('email', 'platform@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->platformRole())->toBe(PlatformRole::PlatformAdmin)
        ->and($user->is_admin)->toBeFalse()
        ->and($user->workspaceMemberships()->count())->toBe(0);
});

test('staff commands are idempotent and keep password unless reset is requested', function () {
    $this->artisan('rmsignage:create-super-admin', [
        '--name' => 'Ahmed',
        '--email' => 'idempotent@example.com',
        '--force' => true,
    ])
        ->expectsQuestion('Password (hidden)', STAFF_PASSWORD)
        ->expectsQuestion('Confirm password (hidden)', STAFF_PASSWORD)
        ->assertSuccessful();

    $hash = User::query()->where('email', 'idempotent@example.com')->value('password');

    $this->artisan('rmsignage:create-super-admin', [
        '--name' => 'Ahmed Updated',
        '--email' => 'idempotent@example.com',
        '--force' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'idempotent@example.com')->firstOrFail();

    expect(User::query()->where('email', 'idempotent@example.com')->count())->toBe(1)
        ->and($user->name)->toBe('Ahmed Updated')
        ->and($user->password)->toBe($hash)
        ->and($user->workspaceMemberships()->count())->toBe(0);
});

test('super admin and platform admin login redirect to admin portal', function () {
    $super = User::factory()->admin()->create([
        'email' => 'super-login@example.com',
        'password' => Hash::make('password'),
    ]);
    $platform = User::factory()->platformAdmin()->create([
        'email' => 'platform-login@example.com',
        'password' => Hash::make('password'),
    ]);
    $owner = User::factory()->create([
        'email' => 'owner-login@example.com',
        'password' => Hash::make('password'),
    ]);
    attachWorkspace($owner, null, WorkspaceRole::Owner);

    $this->post('/login', [
        'email' => 'super-login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    auth()->logout();

    $this->post('/login', [
        'email' => 'platform-login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    auth()->logout();

    $this->post('/login', [
        'email' => 'owner-login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('app.dashboard'));
});

test('platform admin cannot manage plans or elevate platform roles', function () {
    $admin = User::factory()->platformAdmin()->create();
    $target = User::factory()->create();

    expect(PlatformPermissions::canManageBillingPlans($admin))->toBeFalse()
        ->and(PlatformPermissions::canManagePlatformRoles($admin))->toBeFalse()
        ->and(PlatformPermissions::canManageFeatureFlags($admin))->toBeFalse()
        ->and(PlatformPermissions::canDeleteWorkspace($admin))->toBeFalse()
        ->and(PlatformPermissions::canAccessAdmin($admin))->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.users.platform_role', $target), [
            'platform_role' => PlatformRole::SuperAdmin->value,
        ])
        ->assertForbidden();
});

test('super admin can manage plans and platform roles', function () {
    $super = User::factory()->admin()->create();
    $target = User::factory()->create();

    expect(PlatformPermissions::canManageBillingPlans($super))->toBeTrue()
        ->and(PlatformPermissions::canManagePlatformRoles($super))->toBeTrue();

    $this->actingAs($super)
        ->post(route('admin.users.platform_role', $target), [
            'platform_role' => PlatformRole::PlatformAdmin->value,
        ])
        ->assertRedirect();

    expect($target->fresh()->platformRole())->toBe(PlatformRole::PlatformAdmin);
});

test('business admin cannot access admin portal', function () {
    $user = User::factory()->create();
    attachWorkspace($user, null, WorkspaceRole::Admin);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('final super admin cannot be demoted', function () {
    $super = User::factory()->admin()->create();

    $this->actingAs($super)
        ->post(route('admin.users.platform_role', $super), [
            'platform_role' => PlatformRole::PlatformAdmin->value,
        ])
        ->assertSessionHasErrors('platform_role');

    expect($super->fresh()->platformRole())->toBe(PlatformRole::SuperAdmin);
});

test('platform-only staff do not appear on business team page', function () {
    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);
    $staff = User::factory()->platformAdmin()->create();

    expect(WorkspaceMember::query()->where('user_id', $staff->id)->count())->toBe(0);

    $this->actingAs($owner)
        ->get(route('app.team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/team')
            ->where('members', fn ($members) => collect($members)->every(
                fn ($row) => ($row['email'] ?? null) !== $staff->email,
            )));
});
