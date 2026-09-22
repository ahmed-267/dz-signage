<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairing_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained('screens')->nullOnDelete();
            $table->jsonb('device_meta')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('pending_device_token_ciphertext')->nullable();
            $table->timestamps();

            $table->index('public_id');
            $table->index('code_hash');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairing_sessions');
    }
};
