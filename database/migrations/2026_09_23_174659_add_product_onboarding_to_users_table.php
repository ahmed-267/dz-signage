<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_started_at')->nullable()->after('remember_token');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_started_at');
            $table->timestamp('onboarding_skipped_at')->nullable()->after('onboarding_completed_at');
            $table->unsignedTinyInteger('onboarding_step')->nullable()->after('onboarding_skipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_started_at',
                'onboarding_completed_at',
                'onboarding_skipped_at',
                'onboarding_step',
            ]);
        });
    }
};
