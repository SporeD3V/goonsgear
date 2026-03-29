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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method', 30)->default('manual')->after('status');
            }

            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('pending')->after('payment_method');
            }

            if (! Schema::hasColumn('orders', 'paypal_order_id')) {
                $table->string('paypal_order_id')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'paypal_capture_id')) {
                $table->string('paypal_capture_id')->nullable()->after('paypal_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['paypal_capture_id', 'paypal_order_id', 'payment_status', 'payment_method'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
