<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Support\Platform\PlatformStaffProvisioner;

/**
 * Production-safe Platform Admin creation / promotion.
 *
 * Local: interactive prompts (password never as a CLI flag).
 * Laravel Cloud: --from-env (non-interactive; reads RMSIGNAGE_PLATFORM_ADMIN_*).
 */
class CreatePlatformAdminCommand extends CreateSuperAdminCommand
{
    protected $signature = 'rmsignage:create-platform-admin
                            {--name= : Staff display name}
                            {--email= : Staff email address}
                            {--from-env : Read name/email/password from RMSIGNAGE_PLATFORM_ADMIN_* (non-interactive)}
                            {--force : Promote/update without interactive confirmation}
                            {--reset-password : Reset password when updating an existing user}';

    protected $description = 'Create or promote a verified Platform Admin (no Business membership)';

    public function handle(PlatformStaffProvisioner $provisioner): int
    {
        return $this->provisionStaff(
            $provisioner,
            PlatformRole::PlatformAdmin,
            'Platform Admin',
            [
                'name' => 'RMSIGNAGE_PLATFORM_ADMIN_NAME',
                'email' => 'RMSIGNAGE_PLATFORM_ADMIN_EMAIL',
                'password' => 'RMSIGNAGE_PLATFORM_ADMIN_PASSWORD',
            ],
        );
    }
}
