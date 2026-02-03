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
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logo_id')->nullable();
            $table->unsignedBigInteger('banner_id')->nullable();
            $table->string('website_name')->nullable();
            $table->string('slogan')->nullable();
            $table->text('description')->nullable();
            $table->text('short_details')->nullable();
            $table->unsignedBigInteger('photo_id')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
