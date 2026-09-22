<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 32)->nullable()->after('is_admin');
            $table->index('platform_role');
        });

        DB::table('users')
            ->where('is_admin', true)
            ->whereNull('platform_role')
            ->update(['platform_role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['platform_role']);
            $table->dropColumn('platform_role');
        });
    }
};
