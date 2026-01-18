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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();

            // Media
            $table->string('logo')->nullable();

            // Control
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
        Schema::dropIfExists('brands');
    }
};
