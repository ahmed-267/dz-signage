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
            // User-facing: Admin = RMSignage staff who work with/for Super Admin.
            // Enum value remains platform_admin. Workspace Admin is a separate Workspace role.
            self::PlatformAdmin => 'Admin',
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
