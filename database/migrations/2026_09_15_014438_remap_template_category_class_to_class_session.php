<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('templates') || ! Schema::hasColumn('templates', 'category')) {
            return;
        }

        // Undo any temporary remap; the stored category value remains `class`.
        DB::table('templates')
            ->where('category', 'class_session')
            ->update(['category' => 'class']);
    }

    public function down(): void
    {
        //
    }
};
