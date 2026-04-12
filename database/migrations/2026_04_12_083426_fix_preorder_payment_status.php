<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WC pre-orders have payment collected upfront.
     * The import incorrectly mapped wc-pre-ordered → payment_status 'pending'.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'pre-ordered')
            ->where('payment_status', 'pending')
            ->where('order_number', 'like', 'WC-%')
            ->update(['payment_status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'pre-ordered')
            ->where('payment_status', 'paid')
            ->where('order_number', 'like', 'WC-%')
            ->update(['payment_status' => 'pending']);
    }
};
