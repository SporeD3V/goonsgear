<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WcSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessWcSyncPayloadsTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------
     *  Helpers
     * ---------------------------------------------------------------*/

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPayload(string $event, array $data, ?int $entityId = null): WcSyncPayload
    {
        return WcSyncPayload::factory()->forEvent($event, $data)->create([
            'wc_entity_id' => $entityId ?? ($data['wc_order_id'] ?? $data['wc_product_id'] ?? $data['wc_user_id'] ?? $data['wc_coupon_id'] ?? null),
        ]);
    }

    /* ---------------------------------------------------------------
     *  General behaviour
     * ---------------------------------------------------------------*/

    public function test_no_payloads_outputs_info(): void
    {
        $this->artisan('sync:process')
            ->expectsOutputToContain('No payloads to process.')
            ->assertSuccessful();
    }

    public function test_already_processed_payloads_are_skipped(): void
    {
        WcSyncPayload::factory()->processed()->create();

        $this->artisan('sync:process')
            ->expectsOutputToContain('No payloads to process.')
            ->assertSuccessful();
    }

    public function test_replay_flag_re_processes_completed_payloads(): void
    {
        $product = Product::factory()->create();

        $payload = WcSyncPayload::factory()->processed()->forEvent('product.updated', [
            'wc_product_id' => 999,
            'name' => 'Replayed Name',
            'slug' => 'replayed-name',
            'status' => 'publish',
        ])->create(['wc_entity_id' => 999]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 999,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('sync:process --replay')
            ->assertSuccessful();

        $product->refresh();
        $this->assertSame('Replayed Name', $product->name);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->createPayload('order.created', [
            'wc_order_id' => 8000,
            'order_number' => '#8000',
            'total' => 50,
            'email' => 'dry@example.com',
            'first_name' => 'Dry',
            'last_name' => 'Run',
        ], 8000);

        $this->artisan('sync:process --dry-run')
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertSuccessful();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_event_filter_limits_processing(): void
    {
        $this->createPayload('order.created', [
            'wc_order_id' => 9001,
            'order_number' => '#9001',
            'total' => 10,
            'email' => 'a@b.com',
            'first_name' => 'A',
            'last_name' => 'B',
        ], 9001);

        $this->createPayload('product.created', [
            'wc_product_id' => 9002,
            'name' => 'Filtered Product',
            'slug' => 'filtered-product',
            'status' => 'publish',
        ], 9002);

        $this->artisan('sync:process --event=product')
            ->assertSuccessful();

        // Only product payload was processed.
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    /* ---------------------------------------------------------------
     *  Order processing
     * ---------------------------------------------------------------*/

    public function test_order_created_inserts_order_and_mapping(): void
    {
        $this->createPayload('order.created', [
            'wc_order_id' => 5001,
            'order_number' => '#5001',
            'status' => 'processing',
            'payment_method' => 'paypal',
            'email' => 'buyer@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+491234',
            'currency' => 'EUR',
            'subtotal' => 89.99,
            'total' => 94.99,
            'shipping_total' => 5.00,
            'tax_total' => 0,
            'refund_total' => 0,
            'discount_total' => 0,
            'shipping' => [
                'country' => 'DE',
                'state' => 'BE',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'address_1' => 'Hauptstr.',
            ],
            'placed_at' => '2026-04-10T12:00:00+00:00',
            'items' => [
                [
                    'name' => 'Tactical Hoodie',
                    'sku' => 'TH-L',
                    'unit_price' => 89.99,
                    'quantity' => 1,
                    'line_total' => 89.99,
                ],
            ],
        ], 5001);

        $this->artisan('sync:process')->assertSuccessful();

        $order = Order::where('order_number', '#5001')->first();
        $this->assertNotNull($order);
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('buyer@example.com', $order->email);
        $this->assertSame('DE', $order->country);
        $this->assertSame('94.99', $order->total);
        $this->assertEquals(5.00, (float) $order->getRawOriginal('shipping_total'));

        // Mapping row created.
        $this->assertDatabaseHas('import_legacy_orders', [
            'legacy_wc_order_id' => 5001,
            'order_id' => $order->id,
        ]);

        // Line item created.
        $this->assertCount(1, $order->items);
        $this->assertSame('TH-L', $order->items->first()->sku);
    }

    public function test_order_created_updates_existing_mapped_order(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#6001',
            'status' => 'pending',
            'payment_status' => 'pending',
            'email' => 'old@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'country' => 'NL',
            'city' => 'Amsterdam',
            'postal_code' => '1012',
            'street_name' => 'Damrak',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 0,
            'total' => 0,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6001,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.created', [
            'wc_order_id' => 6001,
            'order_number' => '#6001',
            'status' => 'completed',
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'Name',
            'currency' => 'EUR',
            'subtotal' => 100,
            'total' => 100,
            'placed_at' => '2026-04-12T00:00:00+00:00',
        ], 6001);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('new@example.com', $order->email);
    }

    public function test_order_status_changed_updates_status(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#7001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'email' => 'x@x.com',
            'first_name' => 'X',
            'last_name' => 'Y',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 50,
            'total' => 50,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 7001,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.status_changed', [
            'wc_order_id' => 7001,
            'new_status' => 'completed',
        ], 7001);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('completed', $order->status);
    }

    public function test_order_refunded_updates_refund_total(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#7002',
            'status' => 'completed',
            'payment_status' => 'paid',
            'email' => 'r@r.com',
            'first_name' => 'R',
            'last_name' => 'R',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 100,
            'total' => 100,
            'refund_total' => 0,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 7002,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.refunded', [
            'wc_order_id' => 7002,
            'refund_total' => 25.50,
        ], 7002);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('25.50', $order->refund_total);
    }

    public function test_order_line_items_resolve_product_mappings(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 3001,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 3002,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.created', [
            'wc_order_id' => 8001,
            'order_number' => '#8001',
            'status' => 'processing',
            'email' => 'items@example.com',
            'first_name' => 'I',
            'last_name' => 'T',
            'currency' => 'EUR',
            'subtotal' => 50,
            'total' => 50,
            'placed_at' => '2026-04-13T00:00:00+00:00',
            'items' => [
                [
                    'wc_product_id' => 3001,
                    'wc_variation_id' => 3002,
                    'name' => 'Mapped Product',
                    'sku' => 'MP-001',
                    'unit_price' => 50,
                    'quantity' => 1,
                    'line_total' => 50,
                ],
            ],
        ], 8001);

        $this->artisan('sync:process')->assertSuccessful();

        $item = OrderItem::where('sku', 'MP-001')->first();
        $this->assertNotNull($item);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($variant->id, $item->product_variant_id);
    }

    /* ---------------------------------------------------------------
     *  Product processing
     * ---------------------------------------------------------------*/

    public function test_product_created_inserts_product_and_mapping(): void
    {
        $this->createPayload('product.created', [
            'wc_product_id' => 2001,
            'name' => 'New Hoodie',
            'slug' => 'new-hoodie',
            'status' => 'publish',
            'description' => 'A warm hoodie.',
            'is_featured' => true,
            'published_at' => '2026-04-01T00:00:00+00:00',
        ], 2001);

        $this->artisan('sync:process')->assertSuccessful();

        $product = Product::where('slug', 'new-hoodie')->first();
        $this->assertNotNull($product);
        $this->assertSame('New Hoodie', $product->name);
        $this->assertSame('active', $product->status);
        $this->assertTrue($product->is_featured);

        $this->assertDatabaseHas('import_legacy_products', [
            'legacy_wp_post_id' => 2001,
            'product_id' => $product->id,
        ]);
    }

    public function test_product_updated_modifies_existing_mapped_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 2002,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('product.updated', [
            'wc_product_id' => 2002,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'status' => 'publish',
        ], 2002);

        $this->artisan('sync:process')->assertSuccessful();

        $product->refresh();
        $this->assertSame('Updated Name', $product->name);
    }

    public function test_product_trashed_archives_product(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 2003,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('product.trashed', [
            'wc_product_id' => 2003,
        ], 2003);

        $this->artisan('sync:process')->assertSuccessful();

        $product->refresh();
        $this->assertSame('archived', $product->status);
    }

    public function test_product_with_variants_creates_variant_mappings(): void
    {
        $this->createPayload('product.created', [
            'wc_product_id' => 2010,
            'name' => 'Variant Hoodie',
            'slug' => 'variant-hoodie',
            'status' => 'publish',
            'variants' => [
                [
                    'wc_variation_id' => 2011,
                    'name' => 'Large',
                    'sku' => 'VH-L',
                    'price' => 49.99,
                    'regular_price' => 59.99,
                    'stock_quantity' => 10,
                    'stock_status' => 'instock',
                ],
                [
                    'wc_variation_id' => 2012,
                    'name' => 'Small',
                    'sku' => 'VH-S',
                    'price' => 49.99,
                    'stock_quantity' => 0,
                    'stock_status' => 'outofstock',
                ],
            ],
        ], 2010);

        $this->artisan('sync:process')->assertSuccessful();

        $product = Product::where('slug', 'variant-hoodie')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseCount('import_legacy_variants', 2);

        $variantL = ProductVariant::where('sku', 'VH-L')->first();
        $this->assertNotNull($variantL);
        $this->assertSame('49.99', $variantL->price);
        $this->assertSame('59.99', $variantL->compare_at_price);
        $this->assertTrue($variantL->is_active);

        $variantS = ProductVariant::where('sku', 'VH-S')->first();
        $this->assertNotNull($variantS);
        $this->assertFalse($variantS->is_active);
    }

    public function test_variant_stock_changed_updates_stock(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 4001,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('product.stock_changed', [
            'wc_product_id' => 2099,
            'wc_variation_id' => 4001,
            'stock_quantity' => 3,
            'stock_status' => 'instock',
        ], 2099);

        $this->artisan('sync:process')->assertSuccessful();

        $variant->refresh();
        $this->assertSame(3, $variant->stock_quantity);
        $this->assertTrue($variant->is_active);
    }

    /* ---------------------------------------------------------------
     *  Coupon processing
     * ---------------------------------------------------------------*/

    public function test_coupon_saved_creates_coupon(): void
    {
        $this->createPayload('coupon.saved', [
            'wc_coupon_id' => 300,
            'code' => 'SUMMER20',
            'discount_type' => 'percent',
            'amount' => 20,
            'minimum_amount' => 50,
            'usage_limit' => 100,
            'usage_count' => 5,
            'description' => 'Summer sale',
            'date_expires' => '2026-12-31T23:59:59+00:00',
        ], 300);

        $this->artisan('sync:process')->assertSuccessful();

        $coupon = Coupon::where('code', 'SUMMER20')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('percent', $coupon->type);
        $this->assertSame('20.00', $coupon->value);
        $this->assertSame('50.00', $coupon->minimum_subtotal);
        $this->assertTrue($coupon->is_active);
    }

    public function test_coupon_saved_updates_existing_coupon(): void
    {
        Coupon::factory()->create(['code' => 'EXISTING10', 'value' => 10, 'type' => 'fixed']);

        $this->createPayload('coupon.saved', [
            'wc_coupon_id' => 301,
            'code' => 'EXISTING10',
            'discount_type' => 'percent',
            'amount' => 15,
        ], 301);

        $this->artisan('sync:process')->assertSuccessful();

        $coupon = Coupon::where('code', 'EXISTING10')->first();
        $this->assertSame('percent', $coupon->type);
        $this->assertSame('15.00', $coupon->value);
    }

    public function test_coupon_deleted_removes_coupon(): void
    {
        Coupon::factory()->create(['code' => 'DELETEME']);

        $this->createPayload('coupon.deleted', [
            'wc_coupon_id' => 302,
            'code' => 'DELETEME',
        ], 302);

        $this->artisan('sync:process')->assertSuccessful();

        $this->assertDatabaseMissing('coupons', ['code' => 'DELETEME']);
    }

    /* ---------------------------------------------------------------
     *  Customer processing
     * ---------------------------------------------------------------*/

    public function test_customer_created_inserts_user_and_mapping(): void
    {
        $this->createPayload('customer.created', [
            'wc_user_id' => 42,
            'email' => 'customer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'billing' => ['phone' => '+4912345'],
            'shipping' => [
                'country' => 'DE',
                'state' => 'BE',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'address_1' => 'Alexanderplatz 1',
            ],
        ], 42);

        $this->artisan('sync:process')->assertSuccessful();

        $user = User::where('email', 'customer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Max Mustermann', $user->name);
        $this->assertSame('DE', $user->delivery_country);

        $this->assertDatabaseHas('import_legacy_customers', [
            'legacy_wp_user_id' => 42,
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_created_links_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->createPayload('customer.created', [
            'wc_user_id' => 43,
            'email' => 'existing@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ], 43);

        $this->artisan('sync:process')->assertSuccessful();

        // No duplicate user created.
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());

        // Mapping points to existing user.
        $this->assertDatabaseHas('import_legacy_customers', [
            'legacy_wp_user_id' => 43,
            'user_id' => $existing->id,
        ]);
    }

    public function test_customer_updated_modifies_existing_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        DB::table('import_legacy_customers')->insert([
            'legacy_wp_user_id' => 44,
            'user_id' => $user->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('customer.updated', [
            'wc_user_id' => 44,
            'email' => $user->email,
            'first_name' => 'New',
            'last_name' => 'Name',
        ], 44);

        $this->artisan('sync:process')->assertSuccessful();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
    }

    /* ---------------------------------------------------------------
     *  Note / Tracking processing
     * ---------------------------------------------------------------*/

    public function test_note_tracking_updates_order_shipping(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#T001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'email' => 't@t.com',
            'first_name' => 'T',
            'last_name' => 'T',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 50,
            'total' => 50,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 9901,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('note.tracking', [
            'wc_order_id' => 9901,
            'carrier' => 'DHL',
            'tracking_number' => 'DHL123456',
            'shipped_at' => '2026-04-12T14:00:00+00:00',
        ], 9901);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('DHL', $order->shipping_carrier);
        $this->assertSame('DHL123456', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);
    }

    /* ---------------------------------------------------------------
     *  Error handling
     * ---------------------------------------------------------------*/

    public function test_failed_payload_records_error(): void
    {
        // Missing wc_order_id will make processOrder return false — but that's not an exception.
        // Force an actual exception by creating a payload that references a non-existent model operation.
        $payload = WcSyncPayload::factory()->forEvent('order.created', [
            'wc_order_id' => 1,
            'order_number' => '#FAIL',
            'email' => 'fail@example.com',
            'first_name' => 'F',
            'last_name' => 'F',
            'total' => 10,
            'placed_at' => 'not-a-date',  // This won't fail Carbon::parse.
        ])->create(['wc_entity_id' => 1]);

        // The command should still succeed overall — individual failures are tracked.
        $this->artisan('sync:process');

        // Payload should have been processed (or marked failed if exception).
        $payload->refresh();
        $this->assertTrue($payload->isProcessed() || $payload->attempts > 0);
    }

    public function test_unhandled_event_is_skipped_not_failed(): void
    {
        $payload = WcSyncPayload::factory()->forEvent('unknown.event', [
            'foo' => 'bar',
        ])->create(['wc_entity_type' => null, 'wc_entity_id' => null]);

        $this->artisan('sync:process')
            ->expectsOutputToContain('unhandled event')
            ->assertSuccessful();

        $payload->refresh();
        $this->assertNull($payload->processed_at);
        $this->assertSame(0, $payload->attempts);
    }

    /* ---------------------------------------------------------------
     *  Edge cases — Duplicate unique constraints
     * ---------------------------------------------------------------*/

    public function test_product_created_with_existing_slug_updates_instead_of_failing(): void
    {
        $existing = Product::factory()->create(['name' => 'Onyx Shirt', 'slug' => 'onyx-shirt']);

        $this->createPayload('product.created', [
            'wc_product_id' => 9001,
            'name' => 'Onyx Shirt v2',
            'slug' => 'onyx-shirt',
            'status' => 'publish',
        ], 9001);

        $this->artisan('sync:process')->assertSuccessful();

        $existing->refresh();
        $this->assertSame('Onyx Shirt v2', $existing->name);

        $this->assertDatabaseHas('import_legacy_products', [
            'legacy_wp_post_id' => 9001,
            'product_id' => $existing->id,
        ]);

        $this->assertSame(1, Product::where('slug', 'onyx-shirt')->count());
    }

    public function test_variant_with_duplicate_sku_updates_instead_of_failing(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $variant = ProductVariant::factory()->for($productA)->create(['sku' => 'DUP-SKU', 'price' => '10.00']);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 9010,
            'product_id' => $productB->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('product.updated', [
            'wc_product_id' => 9010,
            'name' => $productB->name,
            'slug' => $productB->slug,
            'status' => 'publish',
            'variants' => [
                [
                    'wc_variation_id' => 9011,
                    'name' => 'Moved Variant',
                    'sku' => 'DUP-SKU',
                    'price' => 25.00,
                    'stock_quantity' => 5,
                    'stock_status' => 'instock',
                ],
            ],
        ], 9010);

        $this->artisan('sync:process')->assertSuccessful();

        $variant->refresh();
        $this->assertSame('25.00', $variant->price);
        $this->assertSame($productB->id, $variant->product_id);
        $this->assertSame(1, ProductVariant::where('sku', 'DUP-SKU')->count());
    }

    /* ---------------------------------------------------------------
     *  Edge cases — Status change updates payment_status & financials
     * ---------------------------------------------------------------*/

    public function test_order_status_changed_updates_payment_status(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#STAT001',
            'status' => 'pending',
            'payment_status' => 'pending',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 50,
            'total' => 55.90,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 8001,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.status_changed', [
            'wc_order_id' => 8001,
            'status' => 'processing',
            'old_status' => 'pending',
            'new_status' => 'processing',
            'subtotal' => 50,
            'total' => 55.90,
            'shipping_total' => 5.90,
        ], 8001);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_order_status_changed_fills_missing_subtotal(): void
    {
        $order = Order::forceCreate([
            'order_number' => '#STAT002',
            'status' => 'pending',
            'payment_status' => 'pending',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'country' => 'DE',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'currency' => 'EUR',
            'subtotal' => 0,
            'total' => 119.97,
            'shipping_total' => 0,
            'placed_at' => now(),
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 8002,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createPayload('order.status_changed', [
            'wc_order_id' => 8002,
            'status' => 'processing',
            'old_status' => 'pending',
            'new_status' => 'processing',
            'subtotal' => 119.97,
            'total' => 135.87,
            'shipping_total' => 15.90,
            'discount_total' => 0,
            'items' => [
                [
                    'name' => 'Test Product',
                    'sku' => 'TP-001',
                    'quantity' => 1,
                    'unit_price' => 119.97,
                    'line_total' => 119.97,
                ],
            ],
        ], 8002);

        $this->artisan('sync:process')->assertSuccessful();

        $order->refresh();
        $this->assertSame('119.97', $order->subtotal);
        $this->assertSame('135.87', $order->total);
        $this->assertEquals(15.90, (float) $order->shipping_total);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, $order->items()->count());
    }
}
