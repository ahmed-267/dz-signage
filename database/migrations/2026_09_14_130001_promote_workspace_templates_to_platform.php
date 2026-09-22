<?php

use App\Enums\TemplateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correct Template ownership: Templates are platform-owned only.
 * Existing workspace templates are promoted to published platform templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('templates')
            ->whereNotNull('workspace_id')
            ->update([
                'workspace_id' => null,
                'status' => TemplateStatus::Published->value,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible ownership correction — no-op.
    }
};
