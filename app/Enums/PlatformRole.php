<?php

namespace App\Enums;

enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case PlatformAdmin = 'platform_admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            // Distinct from Business Admin (workspace role). Enum value stays platform_admin.
            self::PlatformAdmin => 'Platform Admin',
        };
    }

    public function canAccessAdmin(): bool
    {
        return true;
    }

    public function canManagePlatformTemplates(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::PlatformAdmin => true,
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }
}
