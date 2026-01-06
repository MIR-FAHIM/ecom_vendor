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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            // Relation
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Image data
            $table->string('image')->nullable();        // image path or URL
            $table->string('alt_text')->nullable();     // accessibility / SEO
            $table->integer('sort_order')->nullable();  // gallery ordering

            // Control
            $table->boolean('is_primary')->nullable();  // main image flag
            $table->string('status')->nullable();       // active, inactive

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
