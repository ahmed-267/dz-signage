<?php

namespace App\Console\Commands;

use App\Actions\ScreenDesigns\PruneAbandonedScreenDesigns;
use App\Models\Workspace;
use Illuminate\Console\Command;

class PruneAbandonedScreenDesignsCommand extends Command
{
    protected $signature = 'screen-designs:prune-abandoned
                            {--workspace= : Limit to a single workspace id}
                            {--force : Allow running outside local/testing}';

    protected $description = 'Delete abandoned empty Screen Design drafts (Untitled / blank / never edited)';

    public function handle(PruneAbandonedScreenDesigns $action): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Only local/testing by default. Pass --force to override.');

            return self::FAILURE;
        }

        $query = Workspace::query()->orderBy('id');
        if ($this->option('workspace') !== null && $this->option('workspace') !== '') {
            $query->whereKey((int) $this->option('workspace'));
        }

        $total = 0;
        foreach ($query->cursor() as $workspace) {
            $removed = $action->handle($workspace);
            if ($removed > 0) {
                $this->line("Workspace {$workspace->id} ({$workspace->name}): removed {$removed}");
            }
            $total += $removed;
        }

        $this->info("Pruned {$total} abandoned Screen Design draft(s).");

        return self::SUCCESS;
    }
}
