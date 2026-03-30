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
            $table->string('billing_first_name')->nullable()->after('tax_total');
            $table->string('billing_last_name')->nullable()->after('billing_first_name');
            $table->string('billing_country', 2)->nullable()->after('billing_last_name');
            $table->string('billing_state')->nullable()->after('billing_country');
            $table->string('billing_city')->nullable()->after('billing_state');
            $table->string('billing_postal_code')->nullable()->after('billing_city');
            $table->string('billing_street_name')->nullable()->after('billing_postal_code');
            $table->string('billing_street_number')->nullable()->after('billing_street_name');
            $table->string('billing_apartment_block')->nullable()->after('billing_street_number');
            $table->string('billing_entrance')->nullable()->after('billing_apartment_block');
            $table->string('billing_floor')->nullable()->after('billing_entrance');
            $table->string('billing_apartment_number')->nullable()->after('billing_floor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'billing_first_name',
                'billing_last_name',
                'billing_country',
                'billing_state',
                'billing_city',
                'billing_postal_code',
                'billing_street_name',
                'billing_street_number',
                'billing_apartment_block',
                'billing_entrance',
                'billing_floor',
                'billing_apartment_number',
            ]);
        });
    }
};
