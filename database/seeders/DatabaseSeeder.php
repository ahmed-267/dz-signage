<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DatabaseSeeder refuses to run in production.');
            $this->command->line('Use rmsignage:create-super-admin / rmsignage:create-platform-admin for staff accounts.');
            $this->command->line('Use rmsignage:seed-demo-account --allow-production for the isolated demo Business.');

            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(PlatformOpsSeeder::class);

        if (app()->environment('local')) {
            $this->call(LocalDevSeeder::class);
            $this->call(PlatformTemplatesSeeder::class);
        }
    }
}
