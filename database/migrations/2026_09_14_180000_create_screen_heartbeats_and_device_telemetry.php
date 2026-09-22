<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_devices', function (Blueprint $table) {
            $table->string('player_version')->nullable()->after('last_seen_at');
            $table->unsignedInteger('viewport_width')->nullable()->after('player_version');
            $table->unsignedInteger('viewport_height')->nullable()->after('viewport_width');
            $table->string('reported_orientation')->nullable()->after('viewport_height');
            $table->string('playback_state')->nullable()->after('reported_orientation');
            $table->string('last_error_code')->nullable()->after('playback_state');
            $table->unsignedBigInteger('reported_deployment_id')->nullable()->after('last_error_code');

            $table->index('last_seen_at');
            $table->index('playback_state');
        });

        Schema::create('screen_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->string('player_version')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('viewport_width')->nullable();
            $table->unsignedInteger('viewport_height')->nullable();
            $table->string('orientation')->nullable();
            $table->unsignedBigInteger('deployment_id')->nullable();
            $table->unsignedBigInteger('screen_design_version_id')->nullable();
            $table->string('playback_state')->nullable();
            $table->string('error_code')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['screen_id', 'recorded_at']);
            $table->index(['screen_device_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_heartbeats');

        Schema::table('screen_devices', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['playback_state']);
            $table->dropColumn([
                'player_version',
                'viewport_width',
                'viewport_height',
                'reported_orientation',
                'playback_state',
                'last_error_code',
                'reported_deployment_id',
            ]);
        });
    }
};
