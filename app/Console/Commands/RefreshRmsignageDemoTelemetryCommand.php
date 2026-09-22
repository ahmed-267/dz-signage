<?php

namespace App\Console\Commands;

use App\Support\Demo\ProductionDemoAccount;
use App\Support\Demo\ProductionDemoAccountProvisioner;
use Illuminate\Console\Command;

/**
 * Demo-only telemetry refresher — keeps the production demo Dashboard looking alive.
 * Strictly scoped to the designated demo Business slug.
 */
class RefreshRmsignageDemoTelemetryCommand extends Command
{
    protected $signature = 'rmsignage:refresh-demo-telemetry';

    protected $description = 'Refresh heartbeats for the designated RMSignage demo Business only';

    public function handle(): int
    {
        $workspace = ProductionDemoAccount::findWorkspace();
        if ($workspace === null) {
            return self::SUCCESS;
        }

        if (! ProductionDemoAccount::isDemoWorkspace($workspace)) {
            $this->error('Refusing to refresh telemetry outside the designated demo Business.');

            return self::FAILURE;
        }

        $updated = ProductionDemoAccountProvisioner::refreshTelemetry();

        if ($this->output->isVerbose()) {
            $this->info("Refreshed demo telemetry for {$updated} TV device(s).");
        }

        return self::SUCCESS;
    }
}
