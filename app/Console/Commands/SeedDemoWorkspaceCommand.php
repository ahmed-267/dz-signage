<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoWorkspaceSeeder;
use Database\Seeders\LocalDevSeeder;
use Illuminate\Console\Command;

class SeedDemoWorkspaceCommand extends Command
{
    protected $signature = 'dz:seed-demo {--fresh-demo : Rebuild known demo-owned content}';

    protected $description = 'Seed the North & Bean demo workspace plus ~80 days of analytics history';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('dz:seed-demo refuses to run in production.');

            return self::FAILURE;
        }

        $owner = User::query()->where('email', 'owner@dz.local')->first();
        if ($owner === null) {
            $this->warn('owner@dz.local missing — running LocalDevSeeder first…');
            $this->call(LocalDevSeeder::class);

            // LocalDevSeeder already calls DemoWorkspaceSeeder; still run history
            // again so --fresh-demo applies when LocalDev was the bootstrap path.
        }

        /** @var DemoWorkspaceSeeder $seeder */
        $seeder = $this->getLaravel()->make(DemoWorkspaceSeeder::class);
        $seeder->freshDemo = (bool) $this->option('fresh-demo');
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('Demo workspace + history seeding complete.');

        return self::SUCCESS;
    }
}
