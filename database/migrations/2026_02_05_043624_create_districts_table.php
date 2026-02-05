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
        Schema::create('districts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedTinyInteger('division_id');
            $table->string('name', 50);
            $table->string('bn_name', 50);
            $table->string('lat', 15);
            $table->string('lon', 15);
            $table->string('url', 50);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
