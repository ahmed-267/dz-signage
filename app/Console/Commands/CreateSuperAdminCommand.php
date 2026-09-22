<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformStaffProvisioner;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Production-safe Super Admin creation / promotion.
 *
 * Local: interactive prompts (password never as a CLI flag).
 * Laravel Cloud: --from-env (non-interactive; reads RMSIGNAGE_SUPER_ADMIN_*).
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'rmsignage:create-super-admin
                            {--name= : Staff display name}
                            {--email= : Staff email address}
                            {--from-env : Read name/email/password from RMSIGNAGE_SUPER_ADMIN_* (non-interactive)}
                            {--force : Promote/update without interactive confirmation}
                            {--reset-password : Reset password when updating an existing user}';

    protected $description = 'Create or promote a verified Super Admin (no Business membership)';

    public function handle(PlatformStaffProvisioner $provisioner): int
    {
        return $this->provisionStaff(
            $provisioner,
            PlatformRole::SuperAdmin,
            'Super Admin',
            [
                'name' => 'RMSIGNAGE_SUPER_ADMIN_NAME',
                'email' => 'RMSIGNAGE_SUPER_ADMIN_EMAIL',
                'password' => 'RMSIGNAGE_SUPER_ADMIN_PASSWORD',
            ],
        );
    }

    /**
     * @param  array{name: string, email: string, password: string}  $envKeys
     */
    protected function provisionStaff(
        PlatformStaffProvisioner $provisioner,
        PlatformRole $role,
        string $roleLabel,
        array $envKeys,
    ): int {
        if ($this->option('from-env')) {
            return $this->provisionFromEnv($provisioner, $role, $roleLabel, $envKeys);
        }

        return $this->provisionInteractive($provisioner, $role, $roleLabel);
    }

    /**
     * @param  array{name: string, email: string, password: string}  $envKeys
     */
    private function provisionFromEnv(
        PlatformStaffProvisioner $provisioner,
        PlatformRole $role,
        string $roleLabel,
        array $envKeys,
    ): int {
        $name = trim($this->envValue($envKeys['name']));
        $email = strtolower(trim($this->envValue($envKeys['email'])));
        $password = $this->envValue($envKeys['password']);

        if ($name === '') {
            $this->error("{$envKeys['name']} is not configured.");

            return self::FAILURE;
        }

        if ($email === '') {
            $this->error("{$envKeys['email']} is not configured.");

            return self::FAILURE;
        }

        if ($password === '') {
            $this->error("{$envKeys['password']} is not configured.");

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $current = $existing->platformRole();

            if ($provisioner->wouldDowngrade($current, $role)) {
                $this->error("Refusing to downgrade {$email} from Super Admin to {$roleLabel} via --from-env.");
                $this->line('Demote Super Admins only through the Admin Users UI or an interactive command with explicit confirmation.');

                return self::FAILURE;
            }
        }

        // Env credentials are explicit: always apply the provided password.
        return $this->finishProvision(
            $provisioner,
            $role,
            $roleLabel,
            $name,
            $email,
            $password,
            resetPassword: true,
            previousRole: $existing?->platformRole(),
        );
    }

    private function provisionInteractive(
        PlatformStaffProvisioner $provisioner,
        PlatformRole $role,
        string $roleLabel,
    ): int {
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));

        if ($name === '' || $email === '') {
            $this->error('Name and email are required.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();
        $force = (bool) $this->option('force');
        $resetPassword = (bool) $this->option('reset-password');

        if ($existing !== null) {
            $current = $existing->platformRole();

            if ($provisioner->wouldDowngrade($current, $role)) {
                if (! $force && ! $this->confirm(
                    "{$existing->email} is currently Super Admin. Downgrade to {$roleLabel}?",
                    false,
                )) {
                    $this->warn('Cancelled — no changes made.');

                    return self::SUCCESS;
                }

                if ($provisioner->superAdminCount() <= 1) {
                    $this->error('RMSignage must have at least one Super Admin.');
                    $this->line('Create another Super Admin before removing this role.');

                    return self::FAILURE;
                }
            }

            if (! $force && ! $this->confirm(
                $current === $role
                    ? "Update existing {$roleLabel} {$email}?"
                    : "Promote/update {$email} to {$roleLabel}?",
                true,
            )) {
                $this->warn('Cancelled — no changes made.');

                return self::SUCCESS;
            }

            if (! $resetPassword && ! $force && $this->input->isInteractive()) {
                $resetPassword = $this->confirm('Reset password for this account?', false);
            }
        }

        $password = null;
        if ($existing === null || $resetPassword) {
            $password = (string) $this->secret('Password (hidden)');
            $confirm = (string) $this->secret('Confirm password (hidden)');

            if ($password === '' || $password !== $confirm) {
                $this->error('Passwords do not match or were empty.');

                return self::FAILURE;
            }
        }

        return $this->finishProvision(
            $provisioner,
            $role,
            $roleLabel,
            $name,
            $email,
            $password,
            resetPassword: $existing === null || $resetPassword,
            previousRole: $existing?->platformRole(),
        );
    }

    private function finishProvision(
        PlatformStaffProvisioner $provisioner,
        PlatformRole $role,
        string $roleLabel,
        string $name,
        string $email,
        ?string $password,
        bool $resetPassword,
        ?PlatformRole $previousRole,
    ): int {
        try {
            $result = $provisioner->provision(
                $name,
                $email,
                $role,
                $password,
                $resetPassword,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = $result['user'];

        AuditLogger::record(
            null,
            $result['created'] ? 'user.platform_staff_created' : 'user.platform_staff_updated',
            'user',
            $user->id,
            null,
            [
                'email' => $user->email,
                'previous_role' => $previousRole?->value,
                'new_role' => $role->value,
                'created' => $result['created'],
                'password_updated' => $result['password_updated'],
                'source' => 'artisan:'.$this->getName(),
                'from_env' => (bool) $this->option('from-env'),
            ],
        );

        $verb = $result['created'] ? 'created' : 'updated';
        $this->newLine();
        $this->info("{$roleLabel} {$verb} successfully.");
        $this->newLine();
        $this->line('Name: '.$user->name);
        $this->line('Email: '.$user->email);
        $this->line('Platform Role: '.$roleLabel);
        $this->line('Email Verified: '.($user->email_verified_at !== null ? 'Yes' : 'No'));
        $this->line('Default Portal: /admin');
        $this->line('Business memberships: '.$user->workspaceMemberships()->count());

        return self::SUCCESS;
    }

    private function envValue(string $key): string
    {
        $value = getenv($key);

        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
        }

        return is_string($value) ? $value : '';
    }
}
