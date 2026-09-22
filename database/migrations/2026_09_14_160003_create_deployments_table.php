<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->foreignId('screen_design_id')->constrained('screen_designs')->restrictOnDelete();
            $table->foreignId('screen_design_version_id')->constrained('screen_design_versions')->restrictOnDelete();
            $table->string('status');
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['screen_id', 'status']);
            $table->index(['workspace_id', 'screen_id']);
            $table->index('screen_design_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
