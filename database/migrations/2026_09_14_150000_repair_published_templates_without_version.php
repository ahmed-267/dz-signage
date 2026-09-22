<?php

use App\Enums\TemplateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair platform templates marked published without a published_version_id
 * (e.g. after promoting legacy workspace templates).
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = DB::table('templates')
            ->whereNull('workspace_id')
            ->where('status', TemplateStatus::Published->value)
            ->whereNull('published_version_id')
            ->get(['id']);

        foreach ($templates as $template) {
            $version = DB::table('template_versions')
                ->where('template_id', $template->id)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first();

            if ($version === null) {
                DB::table('templates')
                    ->where('id', $template->id)
                    ->update([
                        'status' => TemplateStatus::Draft->value,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if ($version->published_at === null) {
                DB::table('template_versions')
                    ->where('id', $version->id)
                    ->update(['published_at' => now()]);
            }

            DB::table('templates')
                ->where('id', $template->id)
                ->update([
                    'published_version_id' => $version->id,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Data repair — irreversible.
    }
};
