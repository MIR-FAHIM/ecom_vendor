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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            // New fields
            $table->string('mobile')->nullable();            // Mobile number
            $table->string('address')->nullable();           // Address
            $table->string('optional_phone')->nullable();    // Optional phone
            $table->string('fcm_token')->nullable();         // Firebase Cloud Messaging token
            $table->boolean('is_banned')->default(false);    // Flag to indicate if the user is banned
            $table->enum('role', ['admin', 'vendor', 'customer'])->nullable();  // User role
            $table->enum('status', ['active', 'inactive'])->nullable();       // User status
            $table->string('zone')->nullable();              // Zone
            $table->string('district')->nullable();          // District
            $table->string('area')->nullable();              // Area
            $table->decimal('lat', 10, 8)->nullable();       // Latitude
            $table->decimal('lon', 11, 8)->nullable();       // Longitude
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
