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
        Schema::create('o_t_p_sms', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number', 20);
            $table->string('otp', 10);
            $table->boolean('is_expired')->default(false);
            $table->boolean('status')->default(true);
            $table->integer('validity_time');
            $table->string('type', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('o_t_p_sms');
    }
};
