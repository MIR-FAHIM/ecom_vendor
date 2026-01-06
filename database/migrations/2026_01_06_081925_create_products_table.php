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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('shop_id')
                ->nullable()
                ->constrained('shops')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignId('sub_category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            // Self reference (related products)
            $table->foreignId('related_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Identity
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('sku')->nullable()->unique();

            // Descriptions
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // Media
            $table->string('thumbnail')->nullable();

            // Pricing
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();

            // Inventory
            $table->integer('stock')->nullable();
            $table->boolean('track_stock')->nullable();
            $table->boolean('is_active')->nullable();

            // Status / flags
            $table->string('status')->nullable(); 
            // example: draft, active, inactive, archived

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
