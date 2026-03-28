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
        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name', 'categories_name_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('name', 'products_name_unique');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'name'], 'product_variants_product_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_name_unique');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_product_id_name_unique');
        });
    }
};
