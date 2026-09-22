<?php

namespace App\Support\Platform;

use App\Models\User;

/**
 * Central platform capability checks for /admin.
 * Workspace Admin ≠ Admin (platform staff under Super Admin).
 */
final class PlatformPermissions
{
    public static function canAccessAdmin(User $user): bool
    {
        return $user->isPlatformStaff();
    }

    public static function canManagePlatformRoles(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public static function canManageFeatureFlags(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public static function canManagePlatformSettings(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Mutating customer subscriptions via Cashier is Super Admin only.
     */
    public static function canManageCustomerBilling(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Editing persisted BillingPlan catalog rows is Super Admin only.
     * Platform Admin may view plans read-only.
     */
    public static function canManageBillingPlans(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Final Business deletion is Super Admin only. Platform Admin must not
     * receive this right.
     */
    public static function canDeleteWorkspace(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Admin (platform staff) may inspect ops surfaces (templates, support, errors, audit, health).
     */
    public static function canInspectPlatform(User $user): bool
    {
        return $user->isPlatformStaff();
    }

    /**
     * @return array{
     *     can_access_admin: bool,
     *     can_manage_platform_roles: bool,
     *     can_manage_feature_flags: bool,
     *     can_manage_platform_settings: bool,
     *     can_manage_customer_billing: bool,
     *     can_manage_billing_plans: bool,
     *     can_inspect_platform: bool,
     *     is_super_admin: bool,
     *     is_platform_admin: bool
     * }
     */
    public static function for(User $user): array
    {
        return [
            'can_access_admin' => self::canAccessAdmin($user),
            'can_manage_platform_roles' => self::canManagePlatformRoles($user),
            'can_manage_feature_flags' => self::canManageFeatureFlags($user),
            'can_manage_platform_settings' => self::canManagePlatformSettings($user),
            'can_manage_customer_billing' => self::canManageCustomerBilling($user),
            'can_manage_billing_plans' => self::canManageBillingPlans($user),
            'can_inspect_platform' => self::canInspectPlatform($user),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_platform_admin' => $user->isPlatformAdmin(),
        ];
    }
}
