<?php

namespace App\Console\Commands;

use App\Models\ScreenHeartbeat;
use Illuminate\Console\Command;

class PruneScreenHeartbeats extends Command
{
    protected $signature = 'screens:prune-heartbeats {--days= : Override retention days}';

    protected $description = 'Delete ScreenHeartbeat rows older than the configured retention window';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('screens.heartbeat_retention_days', 7)));
        $cutoff = now()->subDays($days);

        $deleted = ScreenHeartbeat::query()
            ->where('recorded_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} heartbeat row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
