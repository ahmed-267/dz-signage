<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('gbp');
            $table->unsignedInteger('monthly_amount')->nullable();
            $table->unsignedInteger('annual_amount')->nullable();
            $table->unsignedInteger('yearly_monthly_equivalent')->nullable();
            $table->string('monthly_stripe_price_id')->nullable();
            $table->string('annual_stripe_price_id')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->json('legacy_stripe_price_ids')->nullable();
            $table->unsignedInteger('screen_limit')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->unsignedInteger('team_member_limit')->nullable();
            $table->json('features')->nullable();
            $table->json('feature_labels')->nullable();
            $table->string('badge')->nullable();
            $table->boolean('popular')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enterprise')->default(false);
            $table->string('cta')->default('Get Started');
            $table->timestamps();
        });

        Schema::create('workspace_billing_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('screen_limit')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->unsignedInteger('team_limit')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_billing_overrides');
        Schema::dropIfExists('billing_plans');
    }
};
