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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Self reference for hierarchy (parent -> child)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Identity
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();

            // Presentation
            $table->string('icon')->nullable();
            $table->string('image')->nullable();

            // Ordering / visibility
            $table->integer('sort_order')->nullable();
            $table->string('status')->nullable(); 
            // active, inactive, hidden

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
