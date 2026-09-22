<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Support\Platform\PlatformStaffProvisioner;

/**
 * Production-safe Platform Admin creation / promotion.
 *
 * Password is never accepted as a CLI flag (shell history risk).
 */
class CreatePlatformAdminCommand extends CreateSuperAdminCommand
{
    protected $signature = 'rmsignage:create-platform-admin
                            {--name= : Staff display name}
                            {--email= : Staff email address}
                            {--force : Promote/update without interactive confirmation}
                            {--reset-password : Reset password when updating an existing user}';

    protected $description = 'Create or promote a verified Platform Admin (no Business membership)';

    public function handle(PlatformStaffProvisioner $provisioner): int
    {
        return $this->provisionStaff(
            $provisioner,
            PlatformRole::PlatformAdmin,
            'Platform Admin',
        );
    }
}
