<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_success_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('api_token_id')->nullable()->index();
            $table->string('login_type', 50)->index();
            $table->string('identifier')->nullable();
            $table->string('token_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('user_type', 50)->nullable()->index();
            $table->string('ip_address', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
            $table->index(['login_type', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_success_logs');
    }
};
