<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->string('device_identifier')->unique();
            $table->string('device_token_hash')->unique();
            $table->string('device_name')->nullable();
            $table->jsonb('platform_meta')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('screen_id');
            $table->index('device_token_hash');
            $table->index('device_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_devices');
    }
};
