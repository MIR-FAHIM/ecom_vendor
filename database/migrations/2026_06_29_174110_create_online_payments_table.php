<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('order_id');

            $table->foreignId('user_id')
                ->nullable();
                

            // Gateway Information
            $table->string('gateway', 50); // aamarpay

            $table->string('merchant_transaction_id')->unique();
            $table->string('gateway_transaction_id')->nullable()->index();

            // Payment Amount
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BDT');

            // Payment Status
            $table->enum('status', [
                'initiated',
                'pending',
                'success',
                'failed',
                'cancelled',
                'refunded',
            ])->default('initiated');

            // Gateway Charges
            $table->decimal('gateway_fee', 12, 2)->nullable();

            // Complete callback/request response
            $table->json('gateway_response')->nullable();

            // Times
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Useful indexes
            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};