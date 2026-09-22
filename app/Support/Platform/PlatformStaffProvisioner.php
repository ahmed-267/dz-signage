<?php

namespace App\Support\Platform;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates / updates platform staff accounts without Business membership.
 */
final class PlatformStaffProvisioner
{
    /**
     * @return array{user: User, created: bool, password_updated: bool, role_changed: bool}
     */
    public function provision(
        string $name,
        string $email,
        PlatformRole $role,
        ?string $password = null,
        bool $resetPassword = false,
    ): array {
        $email = strtolower(trim($email));
        $name = trim($name);

        $this->validateIdentity($name, $email);

        $existing = User::query()->where('email', $email)->first();
        $created = $existing === null;
        $passwordUpdated = false;
        $previousRole = $existing?->platformRole();
        $roleChanged = $previousRole !== $role;

        if ($existing !== null && $password !== null && $resetPassword) {
            $this->validatePassword($password);
        } elseif ($created) {
            if ($password === null || $password === '') {
                throw ValidationException::withMessages([
                    'password' => 'A password is required when creating a new staff account.',
                ]);
            }
            $this->validatePassword($password);
        }

        if ($created) {
            $user = new User([
                'name' => $name,
                'email' => $email,
            ]);
            $user->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $user->save();
            $user->assignPlatformRole($role);
            $passwordUpdated = true;
        } else {
            $user = $existing;
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if ($resetPassword && $password !== null && $password !== '') {
                $user->forceFill(['password' => Hash::make($password)]);
                $passwordUpdated = true;
            }

            $user->save();
            $user->assignPlatformRole($role);
        }

        return [
            'user' => $user->fresh() ?? $user,
            'created' => $created,
            'password_updated' => $passwordUpdated,
            'role_changed' => $roleChanged,
        ];
    }

    public function wouldDowngrade(?PlatformRole $current, PlatformRole $target): bool
    {
        return $current === PlatformRole::SuperAdmin
            && $target === PlatformRole::PlatformAdmin;
    }

    /**
     * Active Super Admin count (includes legacy is_admin).
     */
    public function superAdminCount(): int
    {
        return User::query()
            ->where(function ($q): void {
                $q->where('platform_role', PlatformRole::SuperAdmin->value)
                    ->orWhere('is_admin', true);
            })
            ->count();
    }

    private function validateIdentity(string $name, string $email): void
    {
        Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
            ],
        )->validate();
    }

    private function validatePassword(string $password): void
    {
        Validator::make(
            ['password' => $password],
            [
                'password' => ['required', 'string', Password::defaults()],
            ],
        )->validate();
    }
}
