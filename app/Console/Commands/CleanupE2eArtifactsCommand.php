<?php

namespace App\Console\Commands;

use App\Support\Demo\E2eArtifactCleaner;
use Illuminate\Console\Command;

class CleanupE2eArtifactsCommand extends Command
{
    protected $signature = 'dz:cleanup-e2e';

    protected $description = 'Remove disposable E2E / Playwright fixtures (E2E-prefixed records)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('dz:cleanup-e2e refuses to run in production.');

            return self::FAILURE;
        }

        E2eArtifactCleaner::run();
        $this->info('E2E artifacts cleaned.');

        return self::SUCCESS;
    }
}
