<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 10)->default('BDT');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->unsignedInteger('trial_days')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_orders_per_month')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->decimal('commission_rate', 8, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'billing_cycle']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
