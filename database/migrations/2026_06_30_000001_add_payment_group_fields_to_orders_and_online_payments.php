<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'payment_group_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_group_id', 64)->nullable()->after('order_number')->index();
            });
        }

        if (!Schema::hasColumn('online_payments', 'payment_group_id')) {
            Schema::table('online_payments', function (Blueprint $table) {
                $table->string('payment_group_id', 64)->nullable()->after('order_id')->index();
            });
        }

        if (!Schema::hasColumn('online_payments', 'order_ids')) {
            Schema::table('online_payments', function (Blueprint $table) {
                $table->json('order_ids')->nullable()->after('payment_group_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('online_payments', 'order_ids')) {
            Schema::table('online_payments', function (Blueprint $table) {
                $table->dropColumn('order_ids');
            });
        }

        if (Schema::hasColumn('online_payments', 'payment_group_id')) {
            Schema::table('online_payments', function (Blueprint $table) {
                $table->dropColumn('payment_group_id');
            });
        }

        if (Schema::hasColumn('orders', 'payment_group_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_group_id');
            });
        }
    }
};
