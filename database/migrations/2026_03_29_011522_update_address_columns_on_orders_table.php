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
            if (! Schema::hasColumn('orders', 'street_name')) {
                $table->string('street_name')->default('')->after('postal_code');
                $table->string('street_number')->default('')->after('street_name');
                $table->string('apartment_block')->nullable()->after('street_number');
                $table->string('entrance')->nullable()->after('apartment_block');
                $table->string('floor')->nullable()->after('entrance');
                $table->string('apartment_number')->nullable()->after('floor');
            }

            if (Schema::hasColumn('orders', 'address_line_1')) {
                $table->dropColumn(['address_line_1', 'address_line_2']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'address_line_1')) {
                $table->string('address_line_1')->default('');
                $table->string('address_line_2')->nullable();
            }

            if (Schema::hasColumn('orders', 'street_name')) {
                $table->dropColumn(['street_name', 'street_number', 'apartment_block', 'entrance', 'floor', 'apartment_number']);
            }
        });
    }
};
