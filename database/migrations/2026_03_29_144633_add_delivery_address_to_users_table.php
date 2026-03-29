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
        Schema::table('users', function (Blueprint $table) {
            $table->string('delivery_phone', 40)->nullable()->after('notify_cart_low_stock');
            $table->string('delivery_country', 2)->nullable()->after('delivery_phone');
            $table->string('delivery_state', 120)->nullable()->after('delivery_country');
            $table->string('delivery_city', 120)->nullable()->after('delivery_state');
            $table->string('delivery_postal_code', 20)->nullable()->after('delivery_city');
            $table->string('delivery_street_name', 200)->nullable()->after('delivery_postal_code');
            $table->string('delivery_street_number', 20)->nullable()->after('delivery_street_name');
            $table->string('delivery_apartment_block', 50)->nullable()->after('delivery_street_number');
            $table->string('delivery_entrance', 50)->nullable()->after('delivery_apartment_block');
            $table->string('delivery_floor', 20)->nullable()->after('delivery_entrance');
            $table->string('delivery_apartment_number', 20)->nullable()->after('delivery_floor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_phone',
                'delivery_country',
                'delivery_state',
                'delivery_city',
                'delivery_postal_code',
                'delivery_street_name',
                'delivery_street_number',
                'delivery_apartment_block',
                'delivery_entrance',
                'delivery_floor',
                'delivery_apartment_number',
            ]);
        });
    }
};
