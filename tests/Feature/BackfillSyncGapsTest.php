<?php

namespace Tests\Feature;

use App\Models\AdminNote;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillSyncGapsTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = database_path('testing-legacy-backfill.sqlite');

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

    // ─── Refund Backfill ───────────────────────────────────────────

    public function test_backfill_refunds_populates_refund_total(): void
    {
        $order = Order::factory()->create(['refund_total' => 0]);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 9001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // WC refund post
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 9901,
            'post_parent' => 9001,
            'post_author' => 0,
            'post_type' => 'shop_order_refund',
            'post_status' => 'wc-completed',
            'post_title' => 'Refund',
            'post_name' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 9901, 'meta_key' => '_refund_amount', 'meta_value' => '25.50'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'refunds'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('25.50', $order->refund_total);
    }

    public function test_backfill_refunds_sums_multiple_refunds(): void
    {
        $order = Order::factory()->create(['refund_total' => 0]);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 9002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Two partial refunds
        foreach ([['id' => 9902, 'amount' => '10.00'], ['id' => 9903, 'amount' => '5.50']] as $refund) {
            DB::connection('legacy')->table('wp_posts')->insert([
                'ID' => $refund['id'],
                'post_parent' => 9002,
                'post_author' => 0,
                'post_type' => 'shop_order_refund',
                'post_status' => 'wc-completed',
                'post_title' => 'Refund',
                'post_name' => '',
                'post_date' => now()->toDateTimeString(),
            ]);

            DB::connection('legacy')->table('wp_postmeta')->insert([
                ['post_id' => $refund['id'], 'meta_key' => '_refund_amount', 'meta_value' => $refund['amount']],
            ]);
        }

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'refunds'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('15.50', $order->refund_total);
    }

    // ─── Shipping Tax Backfill ─────────────────────────────────────

    public function test_backfill_tax_adds_shipping_tax_and_recalculates_subtotal(): void
    {
        // Order: total=50, shipping=5, tax=3 (current, missing shipping tax), discount=10
        // WC has: order_tax=3, shipping_tax=0.94
        // Expected new tax_total = 3.94, subtotal = 50 - 5 - 3.94 + 10 = 51.06
        $order = Order::factory()->create([
            'total' => 50.00,
            'shipping_total' => 5.00,
            'tax_total' => 3.00,
            'subtotal' => 52.00,
            'discount_total' => 10.00,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 8001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 8001, 'meta_key' => '_order_tax', 'meta_value' => '3.00'],
            ['post_id' => 8001, 'meta_key' => '_order_shipping_tax', 'meta_value' => '0.94'],
            ['post_id' => 8001, 'meta_key' => '_cart_discount', 'meta_value' => '10.00'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tax'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertEquals(3.94, (float) $order->tax_total);
        $this->assertEquals(51.06, (float) $order->subtotal);
    }

    public function test_backfill_tax_skips_orders_without_shipping_tax(): void
    {
        $order = Order::factory()->create([
            'total' => 30.00,
            'shipping_total' => 0,
            'tax_total' => 0,
            'subtotal' => 30.00,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 8002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 8002, 'meta_key' => '_order_tax', 'meta_value' => '0'],
            ['post_id' => 8002, 'meta_key' => '_order_shipping_tax', 'meta_value' => '0'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tax'])
            ->assertSuccessful()
            ->expectsOutputToContain('unchanged');

        $order->refresh();
        $this->assertEquals(0, (float) $order->tax_total);
        $this->assertEquals(30.00, (float) $order->subtotal);
    }

    // ─── Coupon Usages Backfill ────────────────────────────────────

    public function test_backfill_coupons_populates_order_coupon_usages(): void
    {
        $order = Order::factory()->create(['coupon_code' => 'HIPHOP5']);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 7001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 501,
            'order_id' => 7001,
            'order_item_name' => 'HIPHOP5',
            'order_item_type' => 'coupon',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 501, 'meta_key' => 'discount_amount', 'meta_value' => '4.50'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'coupons'])
            ->assertSuccessful();

        $this->assertDatabaseHas('order_coupon_usages', [
            'order_id' => $order->id,
            'coupon_code' => 'HIPHOP5',
            'discount_total' => 4.50,
        ]);
    }

    public function test_backfill_coupons_skips_duplicates(): void
    {
        $order = Order::factory()->create(['coupon_code' => 'CAMO']);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 7002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pre-existing usage
        DB::table('order_coupon_usages')->insert([
            'order_id' => $order->id,
            'coupon_code' => 'CAMO',
            'discount_total' => 3.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 502,
            'order_id' => 7002,
            'order_item_name' => 'CAMO',
            'order_item_type' => 'coupon',
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'coupons'])
            ->assertSuccessful();

        $this->assertSame(1, DB::table('order_coupon_usages')
            ->where('order_id', $order->id)
            ->count());
    }

    // ─── Missing Order Items Backfill ──────────────────────────────

    public function test_backfill_items_recreates_missing_items(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $order = Order::factory()->create();

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 301,
            'product_id' => $product->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 302,
            'product_variant_id' => $variant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // WC order has 2 line items but GG has 0
        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            [
                'order_item_id' => 601,
                'order_id' => 6001,
                'order_item_name' => 'Vinyl Record',
                'order_item_type' => 'line_item',
            ],
            [
                'order_item_id' => 602,
                'order_id' => 6001,
                'order_item_name' => 'T-Shirt',
                'order_item_type' => 'line_item',
            ],
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 601, 'meta_key' => '_product_id', 'meta_value' => '301'],
            ['order_item_id' => 601, 'meta_key' => '_variation_id', 'meta_value' => '302'],
            ['order_item_id' => 601, 'meta_key' => '_qty', 'meta_value' => '2'],
            ['order_item_id' => 601, 'meta_key' => '_line_subtotal', 'meta_value' => '40.00'],
            ['order_item_id' => 601, 'meta_key' => '_line_total', 'meta_value' => '40.00'],
            ['order_item_id' => 601, 'meta_key' => '_sku', 'meta_value' => 'VIN-001'],
            ['order_item_id' => 602, 'meta_key' => '_product_id', 'meta_value' => '301'],
            ['order_item_id' => 602, 'meta_key' => '_variation_id', 'meta_value' => '0'],
            ['order_item_id' => 602, 'meta_key' => '_qty', 'meta_value' => '1'],
            ['order_item_id' => 602, 'meta_key' => '_line_subtotal', 'meta_value' => '25.00'],
            ['order_item_id' => 602, 'meta_key' => '_line_total', 'meta_value' => '25.00'],
            ['order_item_id' => 602, 'meta_key' => '_sku', 'meta_value' => 'TSH-001'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'items'])
            ->assertSuccessful();

        $items = OrderItem::where('order_id', $order->id)->get();
        $this->assertCount(2, $items);
        $this->assertSame('20.00', $items->firstWhere('sku', 'VIN-001')->unit_price);
        $this->assertSame(2, $items->firstWhere('sku', 'VIN-001')->quantity);
    }

    // ─── Phone Numbers Backfill ────────────────────────────────────

    public function test_backfill_phones_populates_order_phone(): void
    {
        $order = Order::factory()->create(['phone' => null]);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 5001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5001, 'meta_key' => '_billing_phone', 'meta_value' => '+49 171 1234567'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'phones'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('+49 171 1234567', $order->phone);
    }

    public function test_backfill_phones_does_not_overwrite_existing(): void
    {
        $order = Order::factory()->create(['phone' => '+49 999 000']);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 5002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5002, 'meta_key' => '_billing_phone', 'meta_value' => '+49 171 9999999'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'phones'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('+49 999 000', $order->phone);
    }

    // ─── Product Dimensions Backfill ───────────────────────────────

    public function test_backfill_dimensions_populates_weight(): void
    {
        $product = Product::factory()->create();
        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 401,
            'product_id' => $product->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 401, 'meta_key' => '_weight', 'meta_value' => '0.350'],
            ['post_id' => 401, 'meta_key' => '_length', 'meta_value' => '32.0'],
            ['post_id' => 401, 'meta_key' => '_width', 'meta_value' => '32.0'],
            ['post_id' => 401, 'meta_key' => '_height', 'meta_value' => '1.5'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'dimensions'])
            ->assertSuccessful();

        $product->refresh();
        $this->assertEquals(0.35, (float) $product->weight);
        $this->assertEquals(32.0, (float) $product->length);
        $this->assertEquals(32.0, (float) $product->width);
        $this->assertEquals(1.5, (float) $product->height);
    }

    // ─── Pre-Order Flags Backfill ──────────────────────────────────

    public function test_backfill_preorders_flags_variants_and_products(): void
    {
        $product = Product::factory()->create(['is_preorder' => false]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_preorder' => false,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 501,
            'product_variant_id' => $variant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 501, 'meta_key' => '_is_pre_order', 'meta_value' => 'yes'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'preorders'])
            ->assertSuccessful();

        $variant->refresh();
        $product->refresh();
        $this->assertTrue($variant->is_preorder);
        $this->assertTrue($product->is_preorder);
    }

    // ─── Dry Run ───────────────────────────────────────────────────

    public function test_dry_run_does_not_write_changes(): void
    {
        $order = Order::factory()->create(['refund_total' => 0]);
        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 4901,
            'post_parent' => 4001,
            'post_author' => 0,
            'post_type' => 'shop_order_refund',
            'post_status' => 'wc-completed',
            'post_title' => 'Refund',
            'post_name' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4901, 'meta_key' => '_refund_amount', 'meta_value' => '15.00'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'refunds', '--dry-run' => true])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('0.00', $order->refund_total);
    }

    // ─── Only Option ───────────────────────────────────────────────

    public function test_invalid_only_option_fails(): void
    {
        $this->artisan('app:backfill-sync-gaps', ['--only' => 'nonexistent'])
            ->assertFailed();
    }

    // ─── WC Coupon Import ──────────────────────────────────────────

    public function test_import_wc_coupons_creates_percent_coupon(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'post_title' => 'hiphop5',
            'post_name' => 'hiphop5',
            'post_excerpt' => 'Hip hop discount',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5001, 'meta_key' => 'discount_type', 'meta_value' => 'percent'],
            ['post_id' => 5001, 'meta_key' => 'coupon_amount', 'meta_value' => '5'],
            ['post_id' => 5001, 'meta_key' => 'usage_count', 'meta_value' => '227'],
            ['post_id' => 5001, 'meta_key' => 'usage_limit', 'meta_value' => '0'],
            ['post_id' => 5001, 'meta_key' => 'date_expires', 'meta_value' => '1693432800'],
            ['post_id' => 5001, 'meta_key' => 'minimum_amount', 'meta_value' => ''],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'wc-coupons'])
            ->assertSuccessful();

        $coupon = Coupon::where('code', 'HIPHOP5')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('percent', $coupon->type);
        $this->assertEquals(5.00, (float) $coupon->value);
        $this->assertSame(227, $coupon->used_count);
        $this->assertTrue($coupon->is_active);
        $this->assertNotNull($coupon->ends_at);
        $this->assertNull($coupon->minimum_subtotal);
    }

    public function test_import_wc_coupons_creates_fixed_cart_coupon(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5002,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'post_title' => 'flat10',
            'post_name' => 'flat10',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5002, 'meta_key' => 'discount_type', 'meta_value' => 'fixed_cart'],
            ['post_id' => 5002, 'meta_key' => 'coupon_amount', 'meta_value' => '10'],
            ['post_id' => 5002, 'meta_key' => 'usage_count', 'meta_value' => '50'],
            ['post_id' => 5002, 'meta_key' => 'minimum_amount', 'meta_value' => '25.00'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'wc-coupons'])
            ->assertSuccessful();

        $coupon = Coupon::where('code', 'FLAT10')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('fixed', $coupon->type);
        $this->assertEquals(10.00, (float) $coupon->value);
        $this->assertEquals(25.00, (float) $coupon->minimum_subtotal);
    }

    public function test_import_wc_coupons_skips_existing(): void
    {
        Coupon::factory()->create(['code' => 'EXISTING5']);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5003,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'post_title' => 'EXISTING5',
            'post_name' => 'existing5',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5003, 'meta_key' => 'discount_type', 'meta_value' => 'percent'],
            ['post_id' => 5003, 'meta_key' => 'coupon_amount', 'meta_value' => '5'],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'wc-coupons'])
            ->assertSuccessful();

        $this->assertSame(1, Coupon::where('code', 'EXISTING5')->count());
    }

    // ─── Shipment Tracking Backfill ─────────────────────────────────

    public function test_backfill_tracking_populates_carrier_and_tracking_number(): void
    {
        $order = Order::factory()->create([
            'shipping_carrier' => null,
            'tracking_number' => null,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Serialized WC tracking data — deutsche-post-dhl carrier
        $trackingData = serialize([[
            'tracking_provider' => 'deutsche-post-dhl',
            'custom_tracking_provider' => '',
            'custom_tracking_link' => '',
            'tracking_number' => 'LB852179686DE',
            'tracking_product_code' => '',
            'date_shipped' => '1632268800',
            'products_list' => '',
            'status_shipped' => '1',
        ]]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4001, 'meta_key' => '_wc_shipment_tracking_items', 'meta_value' => $trackingData],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tracking'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('dhl', $order->shipping_carrier);
        $this->assertSame('LB852179686DE', $order->tracking_number);
    }

    public function test_backfill_tracking_normalizes_spaced_tracking_numbers(): void
    {
        $order = Order::factory()->create([
            'shipping_carrier' => null,
            'tracking_number' => null,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trackingData = serialize([[
            'tracking_provider' => 'deutsche-post',
            'custom_tracking_provider' => '',
            'custom_tracking_link' => '',
            'tracking_number' => 'L E 4 3 7 9 3 7 8 2 5 D E',
            'tracking_product_code' => '',
            'date_shipped' => '1695254400',
        ]]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4002, 'meta_key' => '_wc_shipment_tracking_items', 'meta_value' => $trackingData],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tracking'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('dhl', $order->shipping_carrier);
        $this->assertSame('LE437937825DE', $order->tracking_number);
    }

    public function test_backfill_tracking_skips_orders_that_already_have_tracking(): void
    {
        $order = Order::factory()->create([
            'shipping_carrier' => 'dhl',
            'tracking_number' => 'EXISTING123',
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4003,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trackingData = serialize([[
            'tracking_provider' => 'usps',
            'tracking_number' => 'NEWTRACKING456',
        ]]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4003, 'meta_key' => '_wc_shipment_tracking_items', 'meta_value' => $trackingData],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tracking'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('dhl', $order->shipping_carrier);
        $this->assertSame('EXISTING123', $order->tracking_number);
    }

    public function test_backfill_tracking_maps_carrier_names(): void
    {
        $order = Order::factory()->create(['shipping_carrier' => null, 'tracking_number' => null]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4004,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trackingData = serialize([[
            'tracking_provider' => 'hermes',
            'tracking_number' => 'H123456789DE',
        ]]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4004, 'meta_key' => '_wc_shipment_tracking_items', 'meta_value' => $trackingData],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tracking'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('hermes', $order->shipping_carrier);
        $this->assertSame('H123456789DE', $order->tracking_number);
    }

    public function test_backfill_tracking_handles_non_zero_indexed_array(): void
    {
        $order = Order::factory()->create(['shipping_carrier' => null, 'tracking_number' => null]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 4005,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // WC sometimes serializes with key 1 instead of 0: a:1:{i:1;...}
        $trackingData = serialize([1 => [
            'tracking_provider' => 'deutsche-post',
            'tracking_number' => 'LB 82 438 353 6DE',
            'tracking_product_code' => '',
            'date_shipped' => '1614211200',
            'tracking_id' => '3723f672337ada0d5dabf6a98f3dda40',
        ]]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 4005, 'meta_key' => '_wc_shipment_tracking_items', 'meta_value' => $trackingData],
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'tracking'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('dhl', $order->shipping_carrier);
        $this->assertSame('LB824383536DE', $order->tracking_number);
    }

    // ─── Human Order Notes Import ──────────────────────────────────

    public function test_import_order_notes_creates_admin_notes_for_human_notes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::factory()->create(['order_number' => 'WC-1234']);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 3001,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_comments')->insert([
            'comment_ID' => 101,
            'comment_post_ID' => 3001,
            'comment_author' => 'Manuel Rückert',
            'comment_content' => 'SIGNED ALL!!',
            'comment_type' => 'order_note',
            'comment_date' => '2026-03-23 22:12:03',
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'order-notes'])
            ->assertSuccessful();

        $this->assertDatabaseHas('admin_notes', [
            'user_id' => $admin->id,
            'content' => 'SIGNED ALL!!',
            'context' => "order:{$order->id}",
            'context_label' => 'Order #WC-1234',
        ]);
    }

    public function test_import_order_notes_skips_woocommerce_system_notes(): void
    {
        User::factory()->create(['is_admin' => true]);
        $order = Order::factory()->create();

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 3002,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // System note from WooCommerce
        DB::connection('legacy')->table('wp_comments')->insert([
            'comment_ID' => 102,
            'comment_post_ID' => 3002,
            'comment_author' => 'WooCommerce',
            'comment_content' => 'Order status changed from Processing to Completed.',
            'comment_type' => 'order_note',
            'comment_date' => '2026-03-23 10:00:00',
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'order-notes'])
            ->assertSuccessful();

        $this->assertSame(0, AdminNote::count());
    }

    public function test_import_order_notes_skips_duplicate_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::factory()->create();

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 3003,
            'order_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pre-existing note
        AdminNote::create([
            'user_id' => $admin->id,
            'content' => 'NUR NOCH VINYL!',
            'context' => "order:{$order->id}",
            'context_label' => 'Order #test',
        ]);

        DB::connection('legacy')->table('wp_comments')->insert([
            'comment_ID' => 103,
            'comment_post_ID' => 3003,
            'comment_author' => 'Manuel Rückert',
            'comment_content' => 'NUR NOCH VINYL!',
            'comment_type' => 'order_note',
            'comment_date' => '2026-03-19 10:34:25',
        ]);

        $this->artisan('app:backfill-sync-gaps', ['--only' => 'order-notes'])
            ->assertSuccessful();

        $this->assertSame(1, AdminNote::where('context', "order:{$order->id}")->count());
    }

    // ─── Schema Helper ─────────────────────────────────────────────

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

        $schema->create('wp_wc_order_coupon_lookup', function ($table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('coupon_id');
            $table->decimal('discount_amount', 10, 2);
        });

        $schema->create('wp_comments', function ($table) {
            $table->unsignedBigInteger('comment_ID')->primary();
            $table->unsignedBigInteger('comment_post_ID');
            $table->string('comment_author');
            $table->text('comment_content');
            $table->string('comment_type');
            $table->dateTime('comment_date');
        });
    }
}
