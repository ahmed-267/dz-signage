<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('primary_color', 16)->default('#0F172A');
            $table->string('secondary_color', 16)->default('#334155');
            $table->string('accent_color', 16)->default('#0D9488');
            $table->string('background_color', 16)->default('#FFFFFF');
            $table->string('text_color', 16)->default('#0F172A');
            $table->string('heading_font')->default('Outfit');
            $table->string('body_font')->default('system-ui');
            $table->foreignId('logo_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->foreignId('secondary_logo_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_kits');
    }
};
