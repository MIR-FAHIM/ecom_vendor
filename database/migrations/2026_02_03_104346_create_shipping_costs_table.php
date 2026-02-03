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
        Schema::create('shipping_costs', function (Blueprint $table) {
            $table->id();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->boolean('is_shop_wise')->default(false);
            $table->boolean('is_distance_wise')->default(false);
            $table->boolean('is_product_wise')->default(false);
            $table->decimal('per_shop_cost', 10, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_costs');
    }
};
