<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();

            // Link token to existing users table
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Store ONLY hashed token (never plaintext)
            $table->string('token_hash', 64)->unique();

            // Optional: name the token for audit ("Mobile App", "PGW Integration")
            $table->string('name')->nullable();

            // Permissions / scopes
            $table->json('scopes')->nullable();

            // Token lifecycle
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);

            // Security & audit
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip')->nullable();

            $table->timestamps();

            // Helpful indexes
            $table->index(['user_id', 'is_revoked']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
