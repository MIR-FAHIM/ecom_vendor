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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();

            // Owner (user who manages the shop)
            $table->foreignId('user_id')
                ->nullable();
             
            // Basic shop identity
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();

            // Branding
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();

            // Contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Address / location
            $table->string('address')->nullable();
            $table->string('zone')->nullable();
            $table->string('district')->nullable();
            $table->string('area')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();

            // Status control
            $table->string('status')->nullable(); // pending, active, suspended, banned

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
