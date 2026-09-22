<?php

namespace App\Console\Commands;

use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Models\AiGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PruneAiGenerations extends Command
{
    protected $signature = 'ai:prune-generations';

    protected $description = 'Delete expired AI temp files and trim old generation history metadata';

    public function handle(): int
    {
        $tempHours = (int) config('ai.temp.retention_hours', 24);
        $historyDays = (int) config('ai.temp.history_days', 30);
        $tempCutoff = Carbon::now()->subHours($tempHours);
        $historyCutoff = Carbon::now()->subDays($historyDays);

        $temps = AiGeneration::query()
            ->where('type', AiGenerationType::Image)
            ->whereNotNull('temp_path')
            ->where(function ($q) use ($tempCutoff) {
                $q->where('created_at', '<', $tempCutoff)
                    ->orWhereNotNull('media_asset_id');
            })
            ->limit(500)
            ->get();

        $removed = 0;
        foreach ($temps as $generation) {
            if ($generation->hasTempFile()) {
                Storage::disk((string) $generation->temp_disk)->delete((string) $generation->temp_path);
                $removed++;
            }
            $generation->forceFill([
                'temp_disk' => null,
                'temp_path' => null,
            ])->save();
        }

        // Keep lightweight rows; strip bulky output after retention window.
        $trimmed = AiGeneration::query()
            ->where('created_at', '<', $historyCutoff)
            ->where('status', AiGenerationStatus::Completed)
            ->whereNull('media_asset_id')
            ->whereNull('screen_design_id')
            ->update([
                'output' => null,
                'prompt' => '[expired]',
            ]);

        $this->info("Removed {$removed} temp files; trimmed {$trimmed} history rows.");

        return self::SUCCESS;
    }
}
