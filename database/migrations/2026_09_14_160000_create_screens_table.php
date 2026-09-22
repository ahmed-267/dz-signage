<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('orientation')->nullable();
            $table->string('operational_status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'operational_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
