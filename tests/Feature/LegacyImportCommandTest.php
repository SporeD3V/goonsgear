<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = database_path('testing-legacy.sqlite');

        if (file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        touch($this->legacyDatabasePath);

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => $this->legacyDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('legacy');

        $this->createLegacySchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('legacy');

        if (file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        parent::tearDown();
    }

    public function test_simple_product_default_variant_is_mapped_without_updating_when_mapping_is_missing(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 101,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Legacy Hoodie',
            'post_name' => 'legacy-hoodie',
            'post_excerpt' => 'Archive hoodie',
            'post_content' => '<strong>Imported</strong> content',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 101, 'meta_key' => '_price', 'meta_value' => '14.95'],
            ['post_id' => 101, 'meta_key' => '_regular_price', 'meta_value' => '19.95'],
            ['post_id' => 101, 'meta_key' => '_sku', 'meta_value' => 'simple-101'],
            ['post_id' => 101, 'meta_key' => '_stock', 'meta_value' => '3'],
            ['post_id' => 101, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        $product = Product::factory()->create([
            'slug' => 'legacy-hoodie',
            'name' => 'Legacy Hoodie',
        ]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 101,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'sku' => 'simple-101',
            'option_values' => ['size' => 'L'],
            'price' => 1.00,
            'compare_at_price' => null,
            'stock_quantity' => 0,
            'track_inventory' => false,
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseHas('import_legacy_variants', [
            'legacy_wp_post_id' => 101,
            'product_variant_id' => $variant->id,
        ]);

        $variant->refresh();

        // Additive import: existing variant is mapped but NOT updated
        $this->assertSame('1.00', $variant->price);
        $this->assertNull($variant->compare_at_price);
        $this->assertSame(0, $variant->stock_quantity);
        $this->assertFalse($variant->track_inventory);
    }

    public function test_future_legacy_preorder_date_is_imported_to_product_and_default_variant(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 202,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Future Vinyl',
            'post_name' => 'future-vinyl',
            'post_excerpt' => 'Preorder release',
            'post_content' => 'Ships later',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 202, 'meta_key' => '_price', 'meta_value' => '34.99'],
            ['post_id' => 202, 'meta_key' => '_regular_price', 'meta_value' => '34.99'],
            ['post_id' => 202, 'meta_key' => '_sku', 'meta_value' => 'future-vinyl-202'],
            ['post_id' => 202, 'meta_key' => '_stock', 'meta_value' => '26'],
            ['post_id' => 202, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 202, 'meta_key' => '_pre_order_date', 'meta_value' => '2026-05-29'],
            ['post_id' => 202, 'meta_key' => '_pre_order_stock_status', 'meta_value' => 'global'],
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $product = Product::query()->where('slug', 'future-vinyl')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->is_preorder);
        $this->assertSame('2026-05-29 00:00:00', optional($product->preorder_available_from)?->format('Y-m-d H:i:s'));

        $variant = ProductVariant::query()->where('product_id', $product->id)->where('name', 'Default')->first();
        $this->assertNotNull($variant);
        $this->assertTrue($variant->is_preorder);
        $this->assertSame('2026-05-29 00:00:00', optional($variant->preorder_available_from)?->format('Y-m-d H:i:s'));
    }

    public function test_simple_product_price_is_recovered_from_legacy_order_history_when_meta_price_is_missing(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 230,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Recovered Price Tee',
            'post_name' => 'recovered-price-tee',
            'post_excerpt' => 'Recovered price',
            'post_content' => 'Recovered from order history',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 230, 'meta_key' => '_sku', 'meta_value' => 'recovered-230'],
            ['post_id' => 230, 'meta_key' => '_stock', 'meta_value' => '4'],
            ['post_id' => 230, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 910,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 910',
            'post_name' => 'order-910',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->subDay()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 9001,
            'order_id' => 910,
            'order_item_name' => 'Recovered Price Tee',
            'order_item_type' => 'line_item',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 9001, 'meta_key' => '_product_id', 'meta_value' => '230'],
            ['order_item_id' => 9001, 'meta_key' => '_qty', 'meta_value' => '2'],
            ['order_item_id' => 9001, 'meta_key' => '_line_subtotal', 'meta_value' => '39.90'],
            ['order_item_id' => 9001, 'meta_key' => '_line_total', 'meta_value' => '39.90'],
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $product = Product::query()->where('slug', 'recovered-price-tee')->first();
        $this->assertNotNull($product);

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('name', 'Default')
            ->first();

        $this->assertNotNull($variant);
        $this->assertSame('19.95', $variant->price);
        $this->assertSame(4, $variant->stock_quantity);
        $this->assertTrue($variant->track_inventory);
    }

    public function test_existing_order_is_mapped_without_creating_a_duplicate(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 501,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 501',
            'post_name' => 'order-501',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => '2024-01-10 12:00:00',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 501, 'meta_key' => '_billing_email', 'meta_value' => 'legacy@example.com'],
            ['post_id' => 501, 'meta_key' => '_billing_first_name', 'meta_value' => 'Legacy'],
            ['post_id' => 501, 'meta_key' => '_billing_last_name', 'meta_value' => 'Customer'],
            ['post_id' => 501, 'meta_key' => '_order_total', 'meta_value' => '49.95'],
        ]);

        $order = Order::factory()->create([
            'order_number' => 'WC-501',
            'email' => 'legacy@example.com',
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('import_legacy_orders', [
            'legacy_wc_order_id' => 501,
            'order_id' => $order->id,
        ]);
    }

    public function test_mapped_product_keeps_existing_name_when_legacy_name_conflicts(): void
    {
        $existingNameOwner = Product::factory()->create([
            'name' => 'Conflicting Legacy Name',
            'slug' => 'conflicting-legacy-name-owner',
        ]);

        $mappedProduct = Product::factory()->create([
            'name' => 'Mapped Existing Name',
            'slug' => 'mapped-existing-name',
        ]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 303,
            'product_id' => $mappedProduct->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 303,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Conflicting Legacy Name',
            'post_name' => 'legacy-conflicting-name',
            'post_excerpt' => 'Conflict demo',
            'post_content' => 'Conflict demo body',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 303, 'meta_key' => '_price', 'meta_value' => '19.95'],
            ['post_id' => 303, 'meta_key' => '_regular_price', 'meta_value' => '24.95'],
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $mappedProduct->refresh();
        $existingNameOwner->refresh();

        $this->assertSame('Mapped Existing Name', $mappedProduct->name);
        $this->assertSame('Conflicting Legacy Name', $existingNameOwner->name);
    }

    public function test_variation_attributes_are_imported_into_option_values(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 410,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'SnowFlake Hoodie',
            'post_name' => 'snowflake-hoodie',
            'post_excerpt' => 'Legacy variable product',
            'post_content' => 'Legacy variable content',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 34382,
            'post_parent' => 410,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'Red / M',
            'post_name' => 'snowflake-hoodie-red-m',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 410, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 410, 'meta_key' => '_regular_price', 'meta_value' => '0'],
            ['post_id' => 34382, 'meta_key' => '_sku', 'meta_value' => 'SNOW-RED-M'],
            ['post_id' => 34382, 'meta_key' => '_price', 'meta_value' => '59.95'],
            ['post_id' => 34382, 'meta_key' => '_regular_price', 'meta_value' => '64.95'],
            ['post_id' => 34382, 'meta_key' => '_stock', 'meta_value' => '12'],
            ['post_id' => 34382, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 34382, 'meta_key' => 'attribute_pa_color', 'meta_value' => 'red'],
            ['post_id' => 34382, 'meta_key' => 'attribute_pa_size', 'meta_value' => 'm'],
        ]);

        DB::connection('legacy')->table('wp_terms')->insert([
            ['term_id' => 801, 'name' => 'Red', 'slug' => 'red'],
            ['term_id' => 802, 'name' => 'M', 'slug' => 'm'],
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            ['term_taxonomy_id' => 901, 'term_id' => 801, 'taxonomy' => 'pa_color', 'parent' => 0],
            ['term_taxonomy_id' => 902, 'term_id' => 802, 'taxonomy' => 'pa_size', 'parent' => 0],
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $variant = ProductVariant::query()
            ->where('sku', 'SNOW-RED-M')
            ->first();

        $this->assertNotNull($variant);
        $this->assertSame('custom', $variant->variant_type);
        $this->assertSame([
            'color' => 'Red',
            'size' => 'M',
        ], $variant->option_values);
    }

    public function test_legacy_order_item_maps_to_imported_variant_id_for_variations(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 510,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Mapped Variant Hoodie',
            'post_name' => 'mapped-variant-hoodie',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5101,
            'post_parent' => 510,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'Black / M',
            'post_name' => 'mapped-variant-hoodie-black-m',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 510, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 510, 'meta_key' => '_regular_price', 'meta_value' => '0'],
            ['post_id' => 5101, 'meta_key' => '_sku', 'meta_value' => 'MAP-BLK-M'],
            ['post_id' => 5101, 'meta_key' => '_price', 'meta_value' => '59.95'],
            ['post_id' => 5101, 'meta_key' => '_regular_price', 'meta_value' => '64.95'],
            ['post_id' => 5101, 'meta_key' => '_stock', 'meta_value' => '5'],
            ['post_id' => 5101, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 5101, 'meta_key' => 'attribute_pa_color', 'meta_value' => 'black'],
            ['post_id' => 5101, 'meta_key' => 'attribute_pa_size', 'meta_value' => 'm'],
        ]);

        DB::connection('legacy')->table('wp_terms')->insert([
            ['term_id' => 811, 'name' => 'Black', 'slug' => 'black'],
            ['term_id' => 812, 'name' => 'M', 'slug' => 'm'],
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            ['term_taxonomy_id' => 911, 'term_id' => 811, 'taxonomy' => 'pa_color', 'parent' => 0],
            ['term_taxonomy_id' => 912, 'term_id' => 812, 'taxonomy' => 'pa_size', 'parent' => 0],
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 920,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 920',
            'post_name' => 'order-920',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 920, 'meta_key' => '_billing_email', 'meta_value' => 'mapped@example.com'],
            ['post_id' => 920, 'meta_key' => '_billing_first_name', 'meta_value' => 'Mapped'],
            ['post_id' => 920, 'meta_key' => '_billing_last_name', 'meta_value' => 'Customer'],
            ['post_id' => 920, 'meta_key' => '_order_total', 'meta_value' => '59.95'],
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 9201,
            'order_id' => 920,
            'order_item_name' => 'Mapped Variant Hoodie',
            'order_item_type' => 'line_item',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 9201, 'meta_key' => '_product_id', 'meta_value' => '510'],
            ['order_item_id' => 9201, 'meta_key' => '_variation_id', 'meta_value' => '5101'],
            ['order_item_id' => 9201, 'meta_key' => '_variation_title', 'meta_value' => 'Black / M'],
            ['order_item_id' => 9201, 'meta_key' => '_sku', 'meta_value' => 'MAP-BLK-M'],
            ['order_item_id' => 9201, 'meta_key' => '_qty', 'meta_value' => '1'],
            ['order_item_id' => 9201, 'meta_key' => '_line_subtotal', 'meta_value' => '59.95'],
            ['order_item_id' => 9201, 'meta_key' => '_line_total', 'meta_value' => '59.95'],
        ]);

        $this->artisan('import:legacy-data')->assertSuccessful();

        $variant = ProductVariant::query()->where('sku', 'MAP-BLK-M')->first();
        $this->assertNotNull($variant);

        $order = Order::query()->where('order_number', 'WC-920')->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => 'MAP-BLK-M',
        ]);
    }

    public function test_completed_order_gets_completed_payment_status(): void
    {
        $this->insertLegacyOrder(601, 'wc-completed');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-601',
            'payment_status' => 'completed',
        ]);
    }

    public function test_processing_order_gets_paid_payment_status(): void
    {
        $this->insertLegacyOrder(602, 'wc-processing');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-602',
            'payment_status' => 'paid',
        ]);
    }

    public function test_refunded_order_gets_refunded_payment_status(): void
    {
        $this->insertLegacyOrder(603, 'wc-refunded');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-603',
            'payment_status' => 'refunded',
        ]);
    }

    public function test_cancelled_order_gets_cancelled_payment_status(): void
    {
        $this->insertLegacyOrder(604, 'wc-cancelled');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-604',
            'payment_status' => 'cancelled',
        ]);
    }

    public function test_order_subtotal_excludes_shipping_and_tax(): void
    {
        $this->insertLegacyOrder(605, 'wc-completed', [
            '_order_total' => '59.95',
            '_order_shipping' => '5.00',
            '_order_tax' => '4.95',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-605')->first();

        $this->assertNotNull($order);
        $this->assertSame('50.00', $order->subtotal);
        $this->assertSame('59.95', $order->total);
    }

    public function test_order_shipping_and_tax_totals_are_persisted(): void
    {
        $this->insertLegacyOrder(606, 'wc-completed', [
            '_order_total' => '79.90',
            '_order_shipping' => '6.50',
            '_order_tax' => '3.40',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-606')->first();

        $this->assertNotNull($order);
        $this->assertEquals(6.50, (float) $order->shipping_total);
        $this->assertEquals(3.40, (float) $order->tax_total);
    }

    public function test_order_billing_fields_are_persisted(): void
    {
        $this->insertLegacyOrder(607, 'wc-completed', [
            '_billing_first_name' => 'John',
            '_billing_last_name' => 'Doe',
            '_billing_country' => 'DE',
            '_billing_city' => 'Berlin',
            '_billing_postcode' => '10115',
            '_billing_address_1' => 'Hauptstraße',
            '_billing_address_2' => '42',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-607',
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_country' => 'DE',
            'billing_city' => 'Berlin',
            'billing_postal_code' => '10115',
            'billing_street_name' => 'Hauptstraße',
            'billing_street_number' => '42',
        ]);
    }

    public function test_order_coupon_code_is_imported_from_wc_items(): void
    {
        $this->insertLegacyOrder(608, 'wc-completed');

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 6081,
            'order_id' => 608,
            'order_item_name' => 'WELCOME10',
            'order_item_type' => 'coupon',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'WC-608',
            'coupon_code' => 'WELCOME10',
        ]);
    }

    public function test_order_without_coupon_has_null_coupon_code(): void
    {
        $this->insertLegacyOrder(609, 'wc-completed');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-609')->first();

        $this->assertNotNull($order);
        $this->assertNull($order->coupon_code);
    }

    public function test_customer_preserves_wp_registration_date(): void
    {
        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 42,
            'user_email' => 'preserved@example.com',
            'user_login' => 'preserved_user',
            'user_registered' => '2022-06-15 10:30:00',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'customers'])->assertSuccessful();

        $user = User::where('email', 'preserved@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('2022-06-15 10:30:00', $user->created_at->format('Y-m-d H:i:s'));
    }

    public function test_completed_order_gets_shipped_at_from_date_completed(): void
    {
        $completedTimestamp = 1680340800; // 2023-04-01 12:00:00 UTC

        $this->insertLegacyOrder(610, 'wc-completed', [
            '_date_completed' => (string) $completedTimestamp,
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-610')->first();

        $this->assertNotNull($order);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('2023-04-01', $order->shipped_at->format('Y-m-d'));
    }

    public function test_non_completed_order_has_null_shipped_at(): void
    {
        $this->insertLegacyOrder(611, 'wc-processing', [
            '_date_completed' => '1680340800',
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-611')->first();

        $this->assertNotNull($order);
        $this->assertNull($order->shipped_at);
    }

    public function test_stale_mapping_is_cleaned_and_order_recreated(): void
    {
        // Recreate mapping table without FK so we can insert a stale reference.
        // In production, stale mappings happen when orders are truncated (bypasses FK).
        Schema::drop('import_legacy_orders');
        Schema::create('import_legacy_orders', function ($table) {
            $table->id();
            $table->unsignedBigInteger('legacy_wc_order_id')->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 700,
            'order_id' => 999999,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertLegacyOrder(700, 'wc-completed');

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $order = Order::where('order_number', 'WC-700')->first();

        $this->assertNotNull($order);
        $this->assertDatabaseHas('import_legacy_orders', [
            'legacy_wc_order_id' => 700,
            'order_id' => $order->id,
        ]);
    }

    public function test_order_item_falls_back_to_product_when_variant_lookup_fails(): void
    {
        $product = Product::factory()->create(['slug' => 'fallback-tee']);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 800,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertLegacyOrder(801, 'wc-completed');

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 8011,
            'order_id' => 801,
            'order_item_name' => 'Fallback Tee',
            'order_item_type' => 'line_item',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 8011, 'meta_key' => '_product_id', 'meta_value' => '800'],
            ['order_item_id' => 8011, 'meta_key' => '_variation_id', 'meta_value' => '99999'],
            ['order_item_id' => 8011, 'meta_key' => '_qty', 'meta_value' => '1'],
            ['order_item_id' => 8011, 'meta_key' => '_line_subtotal', 'meta_value' => '29.95'],
            ['order_item_id' => 8011, 'meta_key' => '_line_total', 'meta_value' => '29.95'],
        ]);

        $this->artisan('import:legacy-data', ['--only' => 'orders'])->assertSuccessful();

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => 'Fallback Tee',
        ]);
    }

    /**
     * Insert a minimal legacy WC order into the test database.
     *
     * @param  array<string, string>  $extraMeta
     */
    private function insertLegacyOrder(int $wcOrderId, string $wcStatus, array $extraMeta = []): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => $wcOrderId,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => $wcStatus,
            'post_title' => "Order {$wcOrderId}",
            'post_name' => "order-{$wcOrderId}",
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => '2024-01-10 12:00:00',
        ]);

        $meta = array_merge([
            '_billing_email' => 'test@example.com',
            '_billing_first_name' => 'Test',
            '_billing_last_name' => 'User',
            '_order_total' => '49.95',
            '_payment_method' => 'paypal',
        ], $extraMeta);

        $rows = [];
        foreach ($meta as $key => $value) {
            $rows[] = ['post_id' => $wcOrderId, 'meta_key' => $key, 'meta_value' => $value];
        }

        DB::connection('legacy')->table('wp_postmeta')->insert($rows);
    }

    private function createLegacySchema(): void
    {
        $schema = Schema::connection('legacy');

        $schema->create('wp_posts', function ($table) {
            $table->unsignedBigInteger('ID')->primary();
            $table->unsignedBigInteger('post_parent')->default(0);
            $table->unsignedBigInteger('post_author')->default(0);
            $table->string('post_type');
            $table->string('post_status');
            $table->string('post_title')->default('');
            $table->string('post_name')->default('');
            $table->text('post_excerpt')->nullable();
            $table->text('post_content')->nullable();
            $table->dateTime('post_date')->nullable();
        });

        $schema->create('wp_postmeta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
        });

        $schema->create('wp_terms', function ($table) {
            $table->unsignedBigInteger('term_id')->primary();
            $table->string('name');
            $table->string('slug');
        });

        $schema->create('wp_term_taxonomy', function ($table) {
            $table->unsignedBigInteger('term_taxonomy_id')->primary();
            $table->unsignedBigInteger('term_id');
            $table->string('taxonomy');
            $table->unsignedBigInteger('parent')->default(0);
        });

        $schema->create('wp_term_relationships', function ($table) {
            $table->unsignedBigInteger('object_id');
            $table->unsignedBigInteger('term_taxonomy_id');
        });

        $schema->create('wp_users', function ($table) {
            $table->unsignedBigInteger('ID')->primary();
            $table->string('user_email');
            $table->string('user_login');
            $table->dateTime('user_registered')->nullable();
        });

        $schema->create('wp_woocommerce_order_items', function ($table) {
            $table->unsignedBigInteger('order_item_id')->primary();
            $table->unsignedBigInteger('order_id');
            $table->string('order_item_name');
            $table->string('order_item_type');
        });

        $schema->create('wp_woocommerce_order_itemmeta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
        });
    }
}
