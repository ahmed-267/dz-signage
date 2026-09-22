<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_design_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_design_id')->constrained('screen_designs')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->jsonb('schema');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['screen_design_id', 'version_number']);
            $table->index(['screen_design_id', 'published_at']);
        });

        Schema::table('screen_designs', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('screen_design_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screen_designs', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
        });

        Schema::dropIfExists('screen_design_versions');
    }
};
