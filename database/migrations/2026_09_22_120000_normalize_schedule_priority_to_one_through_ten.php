<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise schedule priorities onto the product 1–10 scale.
 *
 * Mapping (documented):
 * - 1–9 stay as-is
 * - 10 (legacy default) → 5 (new default)
 * - 11–1000 → linearly onto 6–10 so former "above default" stays above 5
 * - ≤0 → 5
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        DB::table('schedules')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $next = $this->normalize((int) $row->priority);
                if ($next === (int) $row->priority) {
                    continue;
                }

                DB::table('schedules')->where('id', $row->id)->update(['priority' => $next]);
            }
        });

        // Refresh column default for new rows when the driver supports it.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedules ALTER COLUMN priority SET DEFAULT 5');
        }
    }

    public function down(): void
    {
        // Irreversible data normalisation — priorities stay in 1–10.
        if (Schema::hasTable('schedules') && Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedules ALTER COLUMN priority SET DEFAULT 10');
        }
    }

    private function normalize(int $old): int
    {
        if ($old <= 0) {
            return 5;
        }

        if ($old <= 9) {
            return $old;
        }

        if ($old === 10) {
            return 5;
        }

        $clamped = min(1000, max(11, $old));

        return (int) max(6, min(10, (int) round(6 + (($clamped - 11) / (1000 - 11)) * 4)));
    }
};
