<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_errors', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deployment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
            $table->index('resolved_at');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_errors');
    }
};
