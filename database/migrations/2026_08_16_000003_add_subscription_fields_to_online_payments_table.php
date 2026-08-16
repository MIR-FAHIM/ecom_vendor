<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('online_payments')) {
            if (Schema::hasColumn('online_payments', 'order_id')) {
                DB::statement('ALTER TABLE `online_payments` MODIFY `order_id` BIGINT UNSIGNED NULL');
            }

            Schema::table('online_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('online_payments', 'payment_type')) {
                    $table->string('payment_type', 50)->default('order')->after('id')->index();
                }

                if (!Schema::hasColumn('online_payments', 'store_subscription_id')) {
                    $table->foreignId('store_subscription_id')
                        ->nullable()
                        ->after('order_ids')
                        ->constrained('store_subscriptions')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('online_payments', 'store_id')) {
                    $table->foreignId('store_id')
                        ->nullable()
                        ->after('store_subscription_id')
                        ->constrained('shops')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('online_payments')) {
            Schema::table('online_payments', function (Blueprint $table) {
                if (Schema::hasColumn('online_payments', 'store_id')) {
                    $table->dropConstrainedForeignId('store_id');
                }

                if (Schema::hasColumn('online_payments', 'store_subscription_id')) {
                    $table->dropConstrainedForeignId('store_subscription_id');
                }

                if (Schema::hasColumn('online_payments', 'payment_type')) {
                    $table->dropColumn('payment_type');
                }
            });

            if (Schema::hasColumn('online_payments', 'order_id')) {
                DB::statement('ALTER TABLE `online_payments` MODIFY `order_id` BIGINT UNSIGNED NOT NULL');
            }
        }
    }
};
