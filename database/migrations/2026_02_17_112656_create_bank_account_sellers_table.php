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
        Schema::create('bank_account_sellers', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->unsignedBigInteger('user_id');
            $table->string('account_name');
            $table->string('account_no');
            $table->string('type');
            $table->string('address');
            $table->string('route')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_account_sellers');
    }
};
