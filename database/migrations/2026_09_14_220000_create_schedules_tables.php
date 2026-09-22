<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Drafts may not have picked content yet; activation requires both.
            // The version is pinned so publishing playlist v3 never silently
            // changes what an existing schedule plays.
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->restrictOnDelete();
            $table->foreignId('playlist_version_id')->nullable()->constrained('playlist_versions')->restrictOnDelete();
            $table->string('timezone');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->jsonb('days_of_week');
            $table->unsignedInteger('priority')->default(10);
            $table->string('status')->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'priority']);
            $table->index('start_date');
            $table->index('end_date');
            $table->index('playlist_id');
            $table->index('playlist_version_id');
            $table->index('activated_at');
        });

        Schema::create('schedule_screen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['schedule_id', 'screen_id']);
            $table->index('screen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_screen');
        Schema::dropIfExists('schedules');
    }
};
