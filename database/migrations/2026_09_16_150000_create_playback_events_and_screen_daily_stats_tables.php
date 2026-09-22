<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_device_id')->nullable()->constrained('screen_devices')->nullOnDelete();
            $table->string('type', 48);
            $table->timestamp('occurred_at');
            $table->foreignId('deployment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('screen_design_version_id')->nullable()->constrained('screen_design_versions')->nullOnDelete();
            $table->foreignId('playlist_version_id')->nullable()->constrained('playlist_versions')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'idempotency_key']);
            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['workspace_id', 'screen_id', 'occurred_at']);
            $table->index(['workspace_id', 'type', 'occurred_at']);
            $table->index(['workspace_id', 'screen_design_version_id', 'occurred_at']);
            $table->index(['workspace_id', 'playlist_version_id', 'occurred_at']);
        });

        Schema::create('screen_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('online_seconds')->default(0);
            $table->unsignedInteger('offline_seconds')->default(0);
            $table->unsignedInteger('playback_seconds')->default(0);
            $table->unsignedInteger('content_play_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('heartbeat_count')->default(0);
            $table->timestamps();

            $table->unique(['screen_id', 'stat_date']);
            $table->index(['workspace_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_daily_stats');
        Schema::dropIfExists('playback_events');
    }
};
