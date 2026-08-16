<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->constrained('subscription_packages')->restrictOnDelete();
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 10)->default('BDT');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->enum('payment_status', ['unpaid', 'paid', 'failed', 'refunded'])->default('unpaid');
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['subscription_package_id', 'status']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscriptions');
    }
};
