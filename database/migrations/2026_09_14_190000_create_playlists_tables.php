<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('orientation')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('orientation');
        });

        Schema::create('playlist_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['playlist_id', 'version_number']);
            $table->index(['playlist_id', 'published_at']);
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('playlist_versions')
                ->nullOnDelete();
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_version_id')->constrained('playlist_versions')->cascadeOnDelete();
            $table->foreignId('screen_design_id')->constrained('screen_designs')->restrictOnDelete();
            $table->foreignId('screen_design_version_id')->constrained('screen_design_versions')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('duration_seconds');
            $table->string('transition')->default('fade');
            $table->string('transition_speed')->default('normal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['playlist_version_id', 'position']);
            $table->index('screen_design_version_id');
            $table->index('screen_design_id');
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->string('content_type')->default('screen_design')->after('screen_id');
            $table->foreignId('playlist_id')->nullable()->after('screen_design_version_id')
                ->constrained('playlists')->nullOnDelete();
            $table->unsignedBigInteger('playlist_version_id')->nullable()->after('playlist_id');

            $table->foreign('playlist_version_id')
                ->references('id')
                ->on('playlist_versions')
                ->nullOnDelete();

            $table->index('content_type');
            $table->index('playlist_id');
            $table->index('playlist_version_id');
        });

        // Allow design-only or playlist-only deployments (PostgreSQL).
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropForeign(['screen_design_id']);
            $table->dropForeign(['screen_design_version_id']);
        });

        DB::statement('ALTER TABLE deployments ALTER COLUMN screen_design_id DROP NOT NULL');
        DB::statement('ALTER TABLE deployments ALTER COLUMN screen_design_version_id DROP NOT NULL');

        Schema::table('deployments', function (Blueprint $table) {
            $table->foreign('screen_design_id')
                ->references('id')
                ->on('screen_designs')
                ->nullOnDelete();
            $table->foreign('screen_design_version_id')
                ->references('id')
                ->on('screen_design_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropForeign(['playlist_id']);
            $table->dropForeign(['playlist_version_id']);
            $table->dropForeign(['screen_design_id']);
            $table->dropForeign(['screen_design_version_id']);
            $table->dropColumn(['content_type', 'playlist_id', 'playlist_version_id']);
        });

        DB::statement('UPDATE deployments SET screen_design_id = 0 WHERE screen_design_id IS NULL');
        DB::statement('UPDATE deployments SET screen_design_version_id = 0 WHERE screen_design_version_id IS NULL');
        DB::statement('ALTER TABLE deployments ALTER COLUMN screen_design_id SET NOT NULL');
        DB::statement('ALTER TABLE deployments ALTER COLUMN screen_design_version_id SET NOT NULL');

        Schema::table('deployments', function (Blueprint $table) {
            $table->foreign('screen_design_id')->references('id')->on('screen_designs')->restrictOnDelete();
            $table->foreign('screen_design_version_id')->references('id')->on('screen_design_versions')->restrictOnDelete();
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
        });

        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlist_versions');
        Schema::dropIfExists('playlists');
    }
};
