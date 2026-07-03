<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The legacy import mapped wc-completed orders to payment_status 'completed'
 * while the webhook sync maps them to 'paid'. Dashboards tolerate both via
 * PAID_STATUSES, but the admin payment dropdown only offers 'paid' — so
 * normalize the imported rows. Idempotent; import + sync now both emit 'paid'.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_status', 'completed')
            ->update(['payment_status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible by design: 'completed' and 'paid' are semantically
        // identical and the pre-migration distinction carried no information.
    }
};
