<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('industry')->nullable();
            $table->string('orientation');
            $table->unsignedInteger('canvas_width');
            $table->unsignedInteger('canvas_height');
            $table->string('theme');
            $table->string('status')->default('draft');
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('orientation');
            $table->index('category');
            $table->index('industry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
