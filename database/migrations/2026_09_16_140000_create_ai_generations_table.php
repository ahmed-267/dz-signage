<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->string('provider', 64);
            $table->string('model', 128)->nullable();
            $table->text('prompt');
            $table->jsonb('options')->nullable();
            $table->jsonb('output')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('temp_disk', 64)->nullable();
            $table->string('temp_path', 512)->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('screen_design_id')->nullable()->constrained('screen_designs')->nullOnDelete();
            $table->unsignedInteger('usage_input_tokens')->nullable();
            $table->unsignedInteger('usage_output_tokens')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'type', 'status']);
            $table->unique(['workspace_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
