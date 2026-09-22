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
 * Password is never accepted as a CLI flag (shell history risk).
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'rmsignage:create-super-admin
                            {--name= : Staff display name}
                            {--email= : Staff email address}
                            {--force : Promote/update without interactive confirmation}
                            {--reset-password : Reset password when updating an existing user}';

    protected $description = 'Create or promote a verified Super Admin (no Business membership)';

    public function handle(PlatformStaffProvisioner $provisioner): int
    {
        return $this->provisionStaff(
            $provisioner,
            PlatformRole::SuperAdmin,
            'Super Admin',
        );
    }

    protected function provisionStaff(
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

        try {
            $result = $provisioner->provision(
                $name,
                $email,
                $role,
                $password,
                $existing === null || $resetPassword,
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
                'platform_role' => $role->value,
                'created' => $result['created'],
                'password_updated' => $result['password_updated'],
                'source' => 'artisan:'.$this->getName(),
            ],
        );

        $verb = $result['created'] ? 'created' : 'updated';
        $this->newLine();
        $this->info("{$roleLabel} {$verb} successfully.");
        $this->newLine();
        $this->line('Name: '.$user->name);
        $this->line('Email: '.$user->email);
        $this->line('Platform Role: '.$roleLabel);
        $this->line('Default Portal: /admin');
        $this->line('Business memberships: '.$user->workspaceMemberships()->count());

        return self::SUCCESS;
    }
}
