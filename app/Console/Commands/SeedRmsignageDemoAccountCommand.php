<?php

namespace App\Console\Commands;

use App\Support\Demo\ProductionDemoAccount;
use App\Support\Demo\ProductionDemoAccountProvisioner;
use Illuminate\Console\Command;

/**
 * Production-safe, idempotent demo account seeder.
 *
 * Requires RMSIGNAGE_DEMO_EMAIL + RMSIGNAGE_DEMO_PASSWORD.
 * In production, --allow-production is mandatory.
 */
class SeedRmsignageDemoAccountCommand extends Command
{
    protected $signature = 'rmsignage:seed-demo-account
                            {--allow-production : Explicitly allow running when APP_ENV=production}';

    protected $description = 'Seed the isolated North & Bean Café production demo account (scoped, idempotent)';

    public function handle(ProductionDemoAccountProvisioner $provisioner): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Refusing to seed the demo account in production.');
            $this->line('Re-run with --allow-production if this is intentional.');

            return self::FAILURE;
        }

        $email = ProductionDemoAccount::email();
        $passwordEnv = (string) config('rmsignage_demo.password_env', 'RMSIGNAGE_DEMO_PASSWORD');
        $password = (string) (getenv($passwordEnv) ?: ($_ENV[$passwordEnv] ?? ''));

        if ($password === '') {
            $this->error('RMSIGNAGE_DEMO_PASSWORD is missing. Set it in the environment before seeding.');

            return self::FAILURE;
        }

        if ($email === '') {
            $this->error('RMSIGNAGE_DEMO_EMAIL is empty. Set a designated demo email before seeding.');

            return self::FAILURE;
        }

        $summary = $provisioner->provision(
            $email,
            $password,
            function (string $level, string $message): void {
                if ($level === 'warn') {
                    $this->warn($message);

                    return;
                }

                $this->info($message);
            },
        );

        $this->newLine();
        $this->info('RMSignage demo account seeded successfully.');
        $this->newLine();
        $this->line('Email: '.$summary['email']);
        $this->line('Business: '.$summary['business']);
        $this->newLine();
        $this->line('Created:');
        $this->line($summary['locations'].' Locations');
        $this->line($summary['tvs'].' TVs');
        $this->line($summary['screens'].' Screens');
        $this->line($summary['playlists'].' Playlists');
        $this->line($summary['schedules'].' Schedules');
        $this->line($summary['analytics_days'].' days analytics');

        return self::SUCCESS;
    }
}
