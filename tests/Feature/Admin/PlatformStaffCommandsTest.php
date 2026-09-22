<?php

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

const STAFF_PASSWORD = 'Staff-Passw0rd!Secure';

function setStaffEnv(string $prefix, string $name, string $email, string $password): void
{
    putenv("{$prefix}_NAME={$name}");
    putenv("{$prefix}_EMAIL={$email}");
    putenv("{$prefix}_PASSWORD={$password}");
    $_ENV["{$prefix}_NAME"] = $name;
    $_ENV["{$prefix}_EMAIL"] = $email;
    $_ENV["{$prefix}_PASSWORD"] = $password;
    $_SERVER["{$prefix}_NAME"] = $name;
    $_SERVER["{$prefix}_EMAIL"] = $email;
    $_SERVER["{$prefix}_PASSWORD"] = $password;
}

function clearStaffEnv(string $prefix): void
{
    foreach (['NAME', 'EMAIL', 'PASSWORD'] as $suffix) {
        $key = "{$prefix}_{$suffix}";
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

afterEach(function () {
    clearStaffEnv('RMSIGNAGE_SUPER_ADMIN');
    clearStaffEnv('RMSIGNAGE_PLATFORM_ADMIN');
});

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

test('create-super-admin --from-env works non-interactively', function () {
    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', 'cloud-super@example.com', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->expectsOutputToContain('Super Admin created successfully')
        ->expectsOutputToContain('cloud-super@example.com')
        ->expectsOutputToContain('Email Verified: Yes')
        ->expectsOutputToContain('Default Portal: /admin')
        ->doesntExpectOutputToContain(STAFF_PASSWORD)
        ->assertSuccessful();

    $user = User::query()->where('email', 'cloud-super@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ahmed')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->platformRole())->toBe(PlatformRole::SuperAdmin)
        ->and(Hash::check(STAFF_PASSWORD, $user->password))->toBeTrue()
        ->and($user->workspaceMemberships()->count())->toBe(0);

    $this->post('/login', [
        'email' => 'cloud-super@example.com',
        'password' => STAFF_PASSWORD,
    ])->assertRedirect(route('admin.dashboard'));
});

test('create-super-admin --from-env is idempotent and updates password', function () {
    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', 'idempotent-env@example.com', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])->assertSuccessful();

    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed Cloud', 'idempotent-env@example.com', 'Another-Passw0rd!Secure');

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->expectsOutputToContain('Super Admin updated successfully')
        ->assertSuccessful();

    $user = User::query()->where('email', 'idempotent-env@example.com')->firstOrFail();

    expect(User::query()->where('email', 'idempotent-env@example.com')->count())->toBe(1)
        ->and($user->name)->toBe('Ahmed Cloud')
        ->and(Hash::check('Another-Passw0rd!Secure', $user->password))->toBeTrue()
        ->and($user->workspaceMemberships()->count())->toBe(0);
});

test('create-platform-admin --from-env works non-interactively', function () {
    setStaffEnv('RMSIGNAGE_PLATFORM_ADMIN', 'Sarah', 'cloud-platform@example.com', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-platform-admin', ['--from-env' => true])
        ->expectsOutputToContain('Platform Admin created successfully')
        ->expectsOutputToContain('Email Verified: Yes')
        ->doesntExpectOutputToContain(STAFF_PASSWORD)
        ->assertSuccessful();

    $user = User::query()->where('email', 'cloud-platform@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->platformRole())->toBe(PlatformRole::PlatformAdmin)
        ->and($user->is_admin)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->workspaceMemberships()->count())->toBe(0);

    $this->post('/login', [
        'email' => 'cloud-platform@example.com',
        'password' => STAFF_PASSWORD,
    ])->assertRedirect(route('admin.dashboard'));
});

test('from-env fails clearly when password env is missing', function () {
    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', 'missing-pass@example.com', '');
    putenv('RMSIGNAGE_SUPER_ADMIN_PASSWORD');
    unset($_ENV['RMSIGNAGE_SUPER_ADMIN_PASSWORD'], $_SERVER['RMSIGNAGE_SUPER_ADMIN_PASSWORD']);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->expectsOutputToContain('RMSIGNAGE_SUPER_ADMIN_PASSWORD is not configured')
        ->assertFailed();

    expect(User::query()->where('email', 'missing-pass@example.com')->exists())->toBeFalse();
});

test('from-env fails clearly when name or email env is missing', function () {
    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', '', 'has-email@example.com', STAFF_PASSWORD);
    putenv('RMSIGNAGE_SUPER_ADMIN_NAME');
    unset($_ENV['RMSIGNAGE_SUPER_ADMIN_NAME'], $_SERVER['RMSIGNAGE_SUPER_ADMIN_NAME']);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->expectsOutputToContain('RMSIGNAGE_SUPER_ADMIN_NAME is not configured')
        ->assertFailed();

    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', '', STAFF_PASSWORD);
    putenv('RMSIGNAGE_SUPER_ADMIN_EMAIL');
    unset($_ENV['RMSIGNAGE_SUPER_ADMIN_EMAIL'], $_SERVER['RMSIGNAGE_SUPER_ADMIN_EMAIL']);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->expectsOutputToContain('RMSIGNAGE_SUPER_ADMIN_EMAIL is not configured')
        ->assertFailed();
});

test('from-env rejects invalid email', function () {
    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', 'not-an-email', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->assertFailed();

    expect(User::query()->where('email', 'not-an-email')->exists())->toBeFalse();
});

test('from-env rejects weak password under application policy', function () {
    Password::defaults(fn () => Password::min(12)
        ->mixedCase()
        ->numbers()
        ->symbols());

    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Ahmed', 'weak-pass@example.com', 'short');

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])
        ->assertFailed();

    expect(User::query()->where('email', 'weak-pass@example.com')->exists())->toBeFalse();

    Password::defaults(fn (): ?Illuminate\Validation\Rules\Password => null);
});

test('from-env refuses silent Super Admin downgrade', function () {
    $super = User::factory()->admin()->create(['email' => 'keep-super@example.com']);
    setStaffEnv('RMSIGNAGE_PLATFORM_ADMIN', 'Keep Super', 'keep-super@example.com', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-platform-admin', ['--from-env' => true])
        ->expectsOutputToContain('Refusing to downgrade')
        ->assertFailed();

    expect($super->fresh()->platformRole())->toBe(PlatformRole::SuperAdmin);
});

test('from-env promotes an existing business user without creating a membership', function () {
    $user = User::factory()->create(['email' => 'promote-me@example.com']);
    attachWorkspace($user, null, WorkspaceRole::Owner);
    $membershipCount = $user->workspaceMemberships()->count();

    setStaffEnv('RMSIGNAGE_SUPER_ADMIN', 'Promoted', 'promote-me@example.com', STAFF_PASSWORD);

    $this->artisan('rmsignage:create-super-admin', ['--from-env' => true])->assertSuccessful();

    expect(User::query()->where('email', 'promote-me@example.com')->count())->toBe(1)
        ->and($user->fresh()->platformRole())->toBe(PlatformRole::SuperAdmin)
        ->and($user->fresh()->workspaceMemberships()->count())->toBe($membershipCount);
});
