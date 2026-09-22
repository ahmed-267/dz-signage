<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('orientation');
            $table->unsignedInteger('canvas_width');
            $table->unsignedInteger('canvas_height');
            $table->foreignId('source_template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->unsignedBigInteger('source_template_version_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'orientation']);
            $table->index('source_template_id');
        });

        Schema::table('screen_designs', function (Blueprint $table) {
            $table->foreign('source_template_version_id')
                ->references('id')
                ->on('template_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_designs');
    }
};
