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
        Schema::table('bundle_discounts', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->decimal('bundle_price', 10, 2)->nullable()->after('description');
        });

        Schema::table('bundle_discount_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('bundle_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_discount_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });

        Schema::table('bundle_discounts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'bundle_price']);
        });
    }
};
