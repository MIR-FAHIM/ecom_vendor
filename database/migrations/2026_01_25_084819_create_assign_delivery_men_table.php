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
        Schema::create('assign_delivery_men', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('delivery_man_id');
            $table->unsignedBigInteger('order_id');

            $table->string('status')->default('assigned');
            $table->text('note')->nullable();

            $table->timestamps();

            // Optional but recommended foreign keys
            // $table->foreign('delivery_man_id')->references('id')->on('delivery_men')->onDelete('cascade');
            // $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assign_delivery_men');
    }
};
