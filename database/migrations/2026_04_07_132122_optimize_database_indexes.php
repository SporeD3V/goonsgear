<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop redundant indexes — the unique constraint already provides an index
        Schema::table('import_legacy_categories', function (Blueprint $table) {
            $table->dropIndex('import_legacy_categories_legacy_term_id_index');
        });

        Schema::table('import_legacy_customers', function (Blueprint $table) {
            $table->dropIndex('import_legacy_customers_legacy_wp_user_id_index');
        });

        Schema::table('import_legacy_orders', function (Blueprint $table) {
            $table->dropIndex('import_legacy_orders_legacy_wc_order_id_index');
        });

        Schema::table('import_legacy_products', function (Blueprint $table) {
            $table->dropIndex('import_legacy_products_legacy_wp_post_id_index');
        });

        Schema::table('import_legacy_tags', function (Blueprint $table) {
            $table->dropIndex('import_legacy_tags_legacy_term_id_index');
        });

        Schema::table('import_legacy_variants', function (Blueprint $table) {
            $table->dropIndex('import_legacy_variants_legacy_wp_post_id_index');
        });

        // Add compound index for admin order listing (WHERE status = ? ORDER BY placed_at)
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'placed_at'], 'orders_status_placed_at_index');
        });

        // Replace low-selectivity is_primary standalone index with a compound index
        // used by "find primary image for product" queries
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropIndex('product_media_is_primary_index');
            $table->index(['product_id', 'is_primary', 'position'], 'product_media_product_primary_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropIndex('product_media_product_primary_position_index');
            $table->index('is_primary', 'product_media_is_primary_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_placed_at_index');
        });

        Schema::table('import_legacy_variants', function (Blueprint $table) {
            $table->index('legacy_wp_post_id', 'import_legacy_variants_legacy_wp_post_id_index');
        });

        Schema::table('import_legacy_tags', function (Blueprint $table) {
            $table->index('legacy_term_id', 'import_legacy_tags_legacy_term_id_index');
        });

        Schema::table('import_legacy_products', function (Blueprint $table) {
            $table->index('legacy_wp_post_id', 'import_legacy_products_legacy_wp_post_id_index');
        });

        Schema::table('import_legacy_orders', function (Blueprint $table) {
            $table->index('legacy_wc_order_id', 'import_legacy_orders_legacy_wc_order_id_index');
        });

        Schema::table('import_legacy_customers', function (Blueprint $table) {
            $table->index('legacy_wp_user_id', 'import_legacy_customers_legacy_wp_user_id_index');
        });

        Schema::table('import_legacy_categories', function (Blueprint $table) {
            $table->index('legacy_term_id', 'import_legacy_categories_legacy_term_id_index');
        });
    }
};
