<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncFromWordPressTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = database_path('testing-legacy-sync.sqlite');

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

    // ─── Prices & Stock ──────────────────────────────────────────────

    public function test_sync_updates_variant_price_and_stock_for_mapped_variant(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 10.00,
            'compare_at_price' => null,
            'stock_quantity' => 5,
            'is_preorder' => false,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 1001,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 1001,
            'post_parent' => 100,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => '',
            'post_name' => '',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 1001, 'meta_key' => '_price', 'meta_value' => '24.95'],
            ['post_id' => 1001, 'meta_key' => '_regular_price', 'meta_value' => '29.95'],
            ['post_id' => 1001, 'meta_key' => '_stock', 'meta_value' => '42'],
            ['post_id' => 1001, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'prices'])
            ->assertSuccessful();

        $variant->refresh();
        $this->assertSame('24.95', $variant->price);
        $this->assertSame('29.95', $variant->compare_at_price);
        $this->assertSame(42, $variant->stock_quantity);
        $this->assertTrue($variant->track_inventory);
    }

    public function test_sync_skips_unchanged_variants(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 24.95,
            'compare_at_price' => null,
            'stock_quantity' => 10,
            'is_preorder' => false,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 1002,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 1002,
            'post_parent' => 100,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => '',
            'post_name' => '',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 1002, 'meta_key' => '_price', 'meta_value' => '24.95'],
            ['post_id' => 1002, 'meta_key' => '_regular_price', 'meta_value' => '24.95'],
            ['post_id' => 1002, 'meta_key' => '_stock', 'meta_value' => '10'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'prices'])
            ->assertSuccessful()
            ->expectsOutputToContain('unchanged');

        $variant->refresh();
        $this->assertSame('24.95', $variant->price);
        $this->assertSame(10, $variant->stock_quantity);
    }

    public function test_sync_updates_preorder_fields_from_parent_product_meta(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 49.99,
            'compare_at_price' => null,
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 1003,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Parent product with pre-order date
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 100,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Preorder Parent',
            'post_name' => 'preorder-parent',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 100, 'meta_key' => '_pre_order_date', 'meta_value' => '2027-06-01'],
        ]);

        // Variation without its own pre-order date
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 1003,
            'post_parent' => 100,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => '',
            'post_name' => '',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 1003, 'meta_key' => '_price', 'meta_value' => '49.99'],
            ['post_id' => 1003, 'meta_key' => '_regular_price', 'meta_value' => '49.99'],
            ['post_id' => 1003, 'meta_key' => '_stock', 'meta_value' => '0'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'prices'])
            ->assertSuccessful();

        $variant->refresh();
        $this->assertTrue($variant->is_preorder);
        $this->assertSame('2027-06-01 00:00:00', $variant->preorder_available_from?->format('Y-m-d H:i:s'));
    }

    public function test_dry_run_does_not_persist_price_changes(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 10.00,
            'stock_quantity' => 5,
            'is_preorder' => false,
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 1004,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 1004,
            'post_parent' => 100,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => '',
            'post_name' => '',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 1004, 'meta_key' => '_price', 'meta_value' => '99.99'],
            ['post_id' => 1004, 'meta_key' => '_regular_price', 'meta_value' => '99.99'],
            ['post_id' => 1004, 'meta_key' => '_stock', 'meta_value' => '100'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'prices', '--dry-run' => true])
            ->assertSuccessful();

        $variant->refresh();
        $this->assertSame('10.00', $variant->price);
        $this->assertSame(5, $variant->stock_quantity);
    }

    // ─── New Products ────────────────────────────────────────────────

    public function test_sync_imports_new_simple_product_with_default_variant(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'New WC Tee',
            'post_name' => 'new-wc-tee',
            'post_excerpt' => 'Brand new product',
            'post_content' => 'Full description',
            'post_date' => '2026-04-15 10:00:00',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2001, 'meta_key' => '_price', 'meta_value' => '34.99'],
            ['post_id' => 2001, 'meta_key' => '_regular_price', 'meta_value' => '39.99'],
            ['post_id' => 2001, 'meta_key' => '_sku', 'meta_value' => 'NEW-TEE-01'],
            ['post_id' => 2001, 'meta_key' => '_stock', 'meta_value' => '50'],
            ['post_id' => 2001, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'new-wc-tee')->first();
        $this->assertNotNull($product);
        $this->assertSame('New WC Tee', $product->name);
        $this->assertSame('active', $product->status);

        $variant = ProductVariant::where('product_id', $product->id)->first();
        $this->assertNotNull($variant);
        $this->assertSame('Default', $variant->name);
        $this->assertSame('34.99', $variant->price);
        $this->assertSame('39.99', $variant->compare_at_price);
        $this->assertSame(50, $variant->stock_quantity);

        $this->assertDatabaseHas('import_legacy_products', [
            'legacy_wp_post_id' => 2001,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('import_legacy_variants', [
            'legacy_wp_post_id' => 2001,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_sync_skips_product_already_in_mapping_table(): void
    {
        $product = Product::factory()->create(['slug' => 'already-mapped']);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 2002,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2002,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Already Mapped',
            'post_name' => 'already-mapped',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_sync_skips_product_with_existing_slug(): void
    {
        Product::factory()->create(['slug' => 'slug-conflict']);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2003,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Slug Conflict',
            'post_name' => 'slug-conflict',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_sync_imports_variable_product_with_variations(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2010,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'New Variable Hoodie',
            'post_name' => 'new-variable-hoodie',
            'post_excerpt' => 'Variable product',
            'post_content' => 'Full desc',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2010, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 2010, 'meta_key' => '_regular_price', 'meta_value' => '0'],
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 20101,
            'post_parent' => 2010,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'Size M',
            'post_name' => 'new-variable-hoodie-m',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 20102,
            'post_parent' => 2010,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'Size L',
            'post_name' => 'new-variable-hoodie-l',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 20101, 'meta_key' => '_price', 'meta_value' => '59.95'],
            ['post_id' => 20101, 'meta_key' => '_regular_price', 'meta_value' => '59.95'],
            ['post_id' => 20101, 'meta_key' => '_sku', 'meta_value' => 'VH-M'],
            ['post_id' => 20101, 'meta_key' => '_stock', 'meta_value' => '10'],
            ['post_id' => 20101, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 20102, 'meta_key' => '_price', 'meta_value' => '59.95'],
            ['post_id' => 20102, 'meta_key' => '_regular_price', 'meta_value' => '64.95'],
            ['post_id' => 20102, 'meta_key' => '_sku', 'meta_value' => 'VH-L'],
            ['post_id' => 20102, 'meta_key' => '_stock', 'meta_value' => '8'],
            ['post_id' => 20102, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'new-variable-hoodie')->first();
        $this->assertNotNull($product);
        $this->assertSame(2, ProductVariant::where('product_id', $product->id)->count());

        $variantM = ProductVariant::where('sku', 'VH-M')->first();
        $this->assertNotNull($variantM);
        $this->assertSame('59.95', $variantM->price);
        $this->assertNull($variantM->compare_at_price);
        $this->assertSame(10, $variantM->stock_quantity);

        $variantL = ProductVariant::where('sku', 'VH-L')->first();
        $this->assertNotNull($variantL);
        $this->assertSame('59.95', $variantL->price);
        $this->assertSame('64.95', $variantL->compare_at_price);
        $this->assertSame(8, $variantL->stock_quantity);

        $this->assertDatabaseCount('import_legacy_variants', 2);
    }

    public function test_sync_normalizes_variant_option_values_keys_to_lowercase(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2020,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Color Tee',
            'post_name' => 'color-tee',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2020, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 2020, 'meta_key' => '_regular_price', 'meta_value' => '0'],
        ]);

        // Variation with pa_colour attribute (multilingual alias)
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 20201,
            'post_parent' => 2020,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'Black',
            'post_name' => 'color-tee-black',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        // Set up wp_terms + wp_term_taxonomy for pa_colour
        DB::connection('legacy')->table('wp_terms')->insert([
            'term_id' => 800,
            'name' => 'Black',
            'slug' => 'black',
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            'term_taxonomy_id' => 800,
            'term_id' => 800,
            'taxonomy' => 'pa_colour',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 20201, 'meta_key' => '_price', 'meta_value' => '29.95'],
            ['post_id' => 20201, 'meta_key' => '_regular_price', 'meta_value' => '29.95'],
            ['post_id' => 20201, 'meta_key' => '_sku', 'meta_value' => 'CT-BLK'],
            ['post_id' => 20201, 'meta_key' => '_stock', 'meta_value' => '5'],
            ['post_id' => 20201, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 20201, 'meta_key' => 'attribute_pa_colour', 'meta_value' => 'black'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $variant = ProductVariant::where('sku', 'CT-BLK')->first();
        $this->assertNotNull($variant);

        // Key must be lowercase 'color' (normalized from 'pa_colour'), NOT 'Colour'
        $this->assertArrayHasKey('color', $variant->option_values);
        $this->assertSame('Black', $variant->option_values['color']);
        $this->assertSame('color', $variant->variant_type);
    }

    public function test_sync_normalizes_german_size_attribute_to_size_key(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2030,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'German Size Hoodie',
            'post_name' => 'german-size-hoodie',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2030, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 2030, 'meta_key' => '_regular_price', 'meta_value' => '0'],
        ]);

        // Variation with pa_groesse (German for 'size')
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 20301,
            'post_parent' => 2030,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'XL',
            'post_name' => 'german-size-hoodie-xl',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_terms')->insert([
            'term_id' => 801,
            'name' => 'XL',
            'slug' => 'xl',
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            'term_taxonomy_id' => 801,
            'term_id' => 801,
            'taxonomy' => 'pa_groesse',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 20301, 'meta_key' => '_price', 'meta_value' => '49.95'],
            ['post_id' => 20301, 'meta_key' => '_regular_price', 'meta_value' => '49.95'],
            ['post_id' => 20301, 'meta_key' => '_sku', 'meta_value' => 'GSH-XL'],
            ['post_id' => 20301, 'meta_key' => '_stock', 'meta_value' => '3'],
            ['post_id' => 20301, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 20301, 'meta_key' => 'attribute_pa_groesse', 'meta_value' => 'xl'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $variant = ProductVariant::where('sku', 'GSH-XL')->first();
        $this->assertNotNull($variant);

        // 'pa_groesse' must normalize to 'size' key, value uppercased
        $this->assertArrayHasKey('size', $variant->option_values);
        $this->assertSame('XL', $variant->option_values['size']);
        $this->assertSame('size', $variant->variant_type);
    }

    public function test_sync_sets_custom_type_for_multi_option_variants(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2040,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Multi Option Tee',
            'post_name' => 'multi-option-tee',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2040, 'meta_key' => '_price', 'meta_value' => '0'],
            ['post_id' => 2040, 'meta_key' => '_regular_price', 'meta_value' => '0'],
        ]);

        // Variation with both size + color
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 20401,
            'post_parent' => 2040,
            'post_author' => 0,
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_title' => 'M, Red',
            'post_name' => 'multi-option-tee-m-red',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_terms')->insert([
            ['term_id' => 810, 'name' => 'M', 'slug' => 'm'],
            ['term_id' => 811, 'name' => 'Red', 'slug' => 'red'],
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            ['term_taxonomy_id' => 810, 'term_id' => 810, 'taxonomy' => 'pa_size'],
            ['term_taxonomy_id' => 811, 'term_id' => 811, 'taxonomy' => 'pa_color'],
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 20401, 'meta_key' => '_price', 'meta_value' => '39.95'],
            ['post_id' => 20401, 'meta_key' => '_regular_price', 'meta_value' => '39.95'],
            ['post_id' => 20401, 'meta_key' => '_sku', 'meta_value' => 'MO-M-RED'],
            ['post_id' => 20401, 'meta_key' => '_stock', 'meta_value' => '7'],
            ['post_id' => 20401, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
            ['post_id' => 20401, 'meta_key' => 'attribute_pa_size', 'meta_value' => 'm'],
            ['post_id' => 20401, 'meta_key' => 'attribute_pa_color', 'meta_value' => 'red'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $variant = ProductVariant::where('sku', 'MO-M-RED')->first();
        $this->assertNotNull($variant);

        // Both size and color → variant_type must be 'custom'
        $this->assertArrayHasKey('size', $variant->option_values);
        $this->assertArrayHasKey('color', $variant->option_values);
        $this->assertSame('M', $variant->option_values['size']);
        $this->assertSame('Red', $variant->option_values['color']);
        $this->assertSame('custom', $variant->variant_type);
    }

    public function test_sync_assigns_tags_to_new_product_via_legacy_mapping(): void
    {
        // Create tags that are already in our system (from prior import)
        $tagHipHop = Tag::factory()->create(['name' => 'Hip-Hop', 'type' => 'genre']);
        $tagRap = Tag::factory()->create(['name' => 'Rap', 'type' => 'standard']);

        // Map them to WC term IDs via import_legacy_tags
        DB::table('import_legacy_tags')->insert([
            ['legacy_term_id' => 500, 'tag_id' => $tagHipHop->id, 'synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['legacy_term_id' => 501, 'tag_id' => $tagRap->id, 'synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Set up WC term relationships
        DB::connection('legacy')->table('wp_terms')->insert([
            ['term_id' => 500, 'name' => 'Hip-Hop', 'slug' => 'hip-hop'],
            ['term_id' => 501, 'name' => 'Rap', 'slug' => 'rap'],
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            ['term_taxonomy_id' => 500, 'term_id' => 500, 'taxonomy' => 'product_cat'],
            ['term_taxonomy_id' => 501, 'term_id' => 501, 'taxonomy' => 'product_tag'],
        ]);

        // Product with those term relationships
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2050,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Tagged Product',
            'post_name' => 'tagged-product',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2050, 'meta_key' => '_price', 'meta_value' => '19.99'],
            ['post_id' => 2050, 'meta_key' => '_regular_price', 'meta_value' => '19.99'],
            ['post_id' => 2050, 'meta_key' => '_sku', 'meta_value' => 'TAG-01'],
        ]);

        DB::connection('legacy')->table('wp_term_relationships')->insert([
            ['object_id' => 2050, 'term_taxonomy_id' => 500],
            ['object_id' => 2050, 'term_taxonomy_id' => 501],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'tagged-product')->first();
        $this->assertNotNull($product);

        $tagIds = $product->tags()->pluck('tags.id')->sort()->values()->toArray();
        $expectedTagIds = collect([$tagHipHop->id, $tagRap->id])->sort()->values()->toArray();
        $this->assertSame($expectedTagIds, $tagIds);
    }

    public function test_sync_skips_tags_with_no_legacy_mapping(): void
    {
        // WC term that has NO entry in import_legacy_tags
        DB::connection('legacy')->table('wp_terms')->insert([
            'term_id' => 600,
            'name' => 'Unmapped Tag',
            'slug' => 'unmapped-tag',
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            'term_taxonomy_id' => 600,
            'term_id' => 600,
            'taxonomy' => 'product_tag',
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 2060,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'No Tags Product',
            'post_name' => 'no-tags-product',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 2060, 'meta_key' => '_price', 'meta_value' => '9.99'],
            ['post_id' => 2060, 'meta_key' => '_regular_price', 'meta_value' => '9.99'],
        ]);

        DB::connection('legacy')->table('wp_term_relationships')->insert([
            ['object_id' => 2060, 'term_taxonomy_id' => 600],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'no-tags-product')->first();
        $this->assertNotNull($product);
        $this->assertSame(0, $product->tags()->count());
    }

    // ─── New Customers ───────────────────────────────────────────────

    public function test_sync_imports_new_customer(): void
    {
        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 50,
            'user_email' => 'newcustomer@example.com',
            'user_login' => 'newcustomer',
            'user_registered' => '2026-03-15 10:00:00',
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'customers'])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'newcustomer@example.com']);
        $this->assertDatabaseHas('import_legacy_customers', [
            'legacy_wp_user_id' => 50,
        ]);
    }

    public function test_sync_maps_existing_customer_by_email_instead_of_duplicating(): void
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 51,
            'user_email' => 'existing@example.com',
            'user_login' => 'existinguser',
            'user_registered' => '2025-01-01 00:00:00',
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'customers'])
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('import_legacy_customers', [
            'legacy_wp_user_id' => 51,
            'user_id' => $existingUser->id,
        ]);
    }

    // ─── New Orders ──────────────────────────────────────────────────

    public function test_sync_imports_new_order_with_line_items(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SYNC-V1',
        ]);

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => 3001,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 300,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 5001',
            'post_name' => 'order-5001',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => '2026-04-15 14:30:00',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5001, 'meta_key' => '_billing_email', 'meta_value' => 'buyer@example.com'],
            ['post_id' => 5001, 'meta_key' => '_billing_first_name', 'meta_value' => 'John'],
            ['post_id' => 5001, 'meta_key' => '_billing_last_name', 'meta_value' => 'Doe'],
            ['post_id' => 5001, 'meta_key' => '_shipping_first_name', 'meta_value' => 'John'],
            ['post_id' => 5001, 'meta_key' => '_shipping_last_name', 'meta_value' => 'Doe'],
            ['post_id' => 5001, 'meta_key' => '_shipping_country', 'meta_value' => 'DE'],
            ['post_id' => 5001, 'meta_key' => '_shipping_city', 'meta_value' => 'Berlin'],
            ['post_id' => 5001, 'meta_key' => '_shipping_postcode', 'meta_value' => '10115'],
            ['post_id' => 5001, 'meta_key' => '_shipping_address_1', 'meta_value' => 'Hauptstr.'],
            ['post_id' => 5001, 'meta_key' => '_shipping_address_2', 'meta_value' => '42'],
            ['post_id' => 5001, 'meta_key' => '_order_total', 'meta_value' => '54.94'],
            ['post_id' => 5001, 'meta_key' => '_order_shipping', 'meta_value' => '4.95'],
            ['post_id' => 5001, 'meta_key' => '_order_tax', 'meta_value' => '0'],
            ['post_id' => 5001, 'meta_key' => '_payment_method', 'meta_value' => 'paypal'],
            ['post_id' => 5001, 'meta_key' => '_date_completed', 'meta_value' => (string) now()->timestamp],
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 9101,
            'order_id' => 5001,
            'order_item_name' => 'Sync Product',
            'order_item_type' => 'line_item',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 9101, 'meta_key' => '_product_id', 'meta_value' => '300'],
            ['order_item_id' => 9101, 'meta_key' => '_variation_id', 'meta_value' => '3001'],
            ['order_item_id' => 9101, 'meta_key' => '_qty', 'meta_value' => '2'],
            ['order_item_id' => 9101, 'meta_key' => '_line_subtotal', 'meta_value' => '49.99'],
            ['order_item_id' => 9101, 'meta_key' => '_line_total', 'meta_value' => '49.99'],
            ['order_item_id' => 9101, 'meta_key' => '_sku', 'meta_value' => 'SYNC-V1'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order = Order::where('order_number', 'WC-5001')->first();
        $this->assertNotNull($order);
        $this->assertSame('completed', $order->status);
        $this->assertSame('completed', $order->payment_status);
        $this->assertSame('buyer@example.com', $order->email);
        $this->assertSame('paypal', $order->payment_method);
        $this->assertSame('54.94', $order->total);
        $this->assertEquals(4.95, $order->shipping_total);
        $this->assertSame('49.99', $order->subtotal);
        $this->assertNotNull($order->shipped_at);

        $this->assertDatabaseCount('order_items', 1);

        $orderItem = OrderItem::where('order_id', $order->id)->first();
        $this->assertSame($product->id, $orderItem->product_id);
        $this->assertSame($variant->id, $orderItem->product_variant_id);
        $this->assertSame(2, $orderItem->quantity);
        // WC _line_subtotal=49.99, qty=2 → unit_price = 49.99/2 = 25.00
        $this->assertSame('25.00', $orderItem->unit_price);
        $this->assertSame('49.99', $orderItem->line_total);

        $this->assertDatabaseHas('import_legacy_orders', [
            'legacy_wc_order_id' => 5001,
            'order_id' => $order->id,
        ]);
    }

    public function test_sync_skips_order_already_in_mapping_table(): void
    {
        $order = Order::factory()->create(['order_number' => 'WC-5002']);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 5002,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5002,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 5002',
            'post_name' => 'order-5002',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_sync_maps_existing_order_by_order_number_when_mapping_is_missing(): void
    {
        $order = Order::factory()->create(['order_number' => 'WC-5003']);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5003,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'post_title' => 'Order 5003',
            'post_name' => 'order-5003',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5003, 'meta_key' => '_billing_email', 'meta_value' => 'test@example.com'],
            ['post_id' => 5003, 'meta_key' => '_order_total', 'meta_value' => '25.00'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('import_legacy_orders', [
            'legacy_wc_order_id' => 5003,
            'order_id' => $order->id,
        ]);
    }

    public function test_sync_payment_status_maps_correctly(): void
    {
        $this->insertLegacyOrder(5010, 'wc-processing');
        $this->insertLegacyOrder(5011, 'wc-on-hold');
        $this->insertLegacyOrder(5012, 'wc-refunded');
        $this->insertLegacyOrder(5013, 'wc-pre-ordered');

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $this->assertSame('paid', Order::where('order_number', 'WC-5010')->value('payment_status'));
        $this->assertSame('pending', Order::where('order_number', 'WC-5011')->value('payment_status'));
        $this->assertSame('refunded', Order::where('order_number', 'WC-5012')->value('payment_status'));
        $this->assertSame('paid', Order::where('order_number', 'WC-5013')->value('payment_status'));
    }

    public function test_sync_imports_order_refund_total(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5020,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 5020',
            'post_name' => 'order-5020',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5020, 'meta_key' => '_billing_email', 'meta_value' => 'refund@example.com'],
            ['post_id' => 5020, 'meta_key' => '_order_total', 'meta_value' => '100.00'],
        ]);

        // Refund child post
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5021,
            'post_parent' => 5020,
            'post_author' => 0,
            'post_type' => 'shop_order_refund',
            'post_status' => 'wc-completed',
            'post_title' => 'Refund',
            'post_name' => 'refund-5021',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5021, 'meta_key' => '_refund_amount', 'meta_value' => '25.00'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order = Order::where('order_number', 'WC-5020')->first();
        $this->assertNotNull($order);
        $this->assertSame('25.00', $order->refund_total);
    }

    public function test_sync_extracts_coupon_code_from_order(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 5030,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 5030',
            'post_name' => 'order-5030',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 5030, 'meta_key' => '_billing_email', 'meta_value' => 'coupon@example.com'],
            ['post_id' => 5030, 'meta_key' => '_order_total', 'meta_value' => '80.00'],
            ['post_id' => 5030, 'meta_key' => '_cart_discount', 'meta_value' => '20.00'],
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 9201,
            'order_id' => 5030,
            'order_item_name' => 'SUMMER20',
            'order_item_type' => 'coupon',
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order = Order::where('order_number', 'WC-5030')->first();
        $this->assertNotNull($order);
        $this->assertSame('SUMMER20', $order->coupon_code);
        $this->assertSame('20.00', $order->discount_total);
    }

    // ─── Order Status Updates ────────────────────────────────────────

    public function test_sync_updates_status_and_payment_status_for_existing_mapped_order(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-6001',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6001,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 6001',
            'post_name' => 'order-6001',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 6001, 'meta_key' => '_date_completed', 'meta_value' => (string) now()->subDay()->timestamp],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('completed', $order->payment_status);
        $this->assertNotNull($order->shipped_at);
    }

    public function test_sync_skips_order_whose_status_has_not_changed(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-6002',
            'status' => 'completed',
            'payment_status' => 'completed',
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6002,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6002,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 6002',
            'post_name' => 'order-6002',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $updatedAt = $order->updated_at;

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful()
            ->expectsOutputToContain('unchanged');

        $order->refresh();
        $this->assertSame('completed', $order->status);
    }

    public function test_sync_updates_refund_total_for_existing_order(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-6003',
            'status' => 'completed',
            'payment_status' => 'completed',
        ]);
        $order->forceFill(['refund_total' => 0])->save();

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6003,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6003,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 6003',
            'post_name' => 'order-6003',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        // Refund child posts
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 60031,
            'post_parent' => 6003,
            'post_author' => 0,
            'post_type' => 'shop_order_refund',
            'post_status' => 'wc-completed',
            'post_title' => 'Refund',
            'post_name' => 'refund-60031',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 60031, 'meta_key' => '_refund_amount', 'meta_value' => '15.50'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('15.50', $order->refund_total);
    }

    public function test_sync_updates_payment_status_from_pending_to_paid_for_processing_order(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-6004',
            'status' => 'on-hold',
            'payment_status' => 'pending',
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6004,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6004,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'post_title' => 'Order 6004',
            'post_name' => 'order-6004',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_dry_run_does_not_persist_status_changes(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-6005',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 6005,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6005,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 6005',
            'post_name' => 'order-6005',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders', '--dry-run' => true])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    // ─── Full Sync ───────────────────────────────────────────────────

    public function test_full_sync_runs_all_entities(): void
    {
        // Product
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 7001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Full Sync Product',
            'post_name' => 'full-sync-product',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 7001, 'meta_key' => '_price', 'meta_value' => '29.99'],
            ['post_id' => 7001, 'meta_key' => '_regular_price', 'meta_value' => '29.99'],
            ['post_id' => 7001, 'meta_key' => '_sku', 'meta_value' => 'FS-001'],
            ['post_id' => 7001, 'meta_key' => '_stock', 'meta_value' => '10'],
            ['post_id' => 7001, 'meta_key' => '_manage_stock', 'meta_value' => 'yes'],
        ]);

        // Customer
        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 60,
            'user_email' => 'fullsync@example.com',
            'user_login' => 'fullsyncuser',
            'user_registered' => now()->toDateTimeString(),
        ]);

        // Order
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 7100,
            'post_parent' => 0,
            'post_author' => 60,
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'post_title' => 'Order 7100',
            'post_name' => 'order-7100',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 7100, 'meta_key' => '_billing_email', 'meta_value' => 'fullsync@example.com'],
            ['post_id' => 7100, 'meta_key' => '_order_total', 'meta_value' => '29.99'],
        ]);

        $this->artisan('sync:wordpress')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', ['slug' => 'full-sync-product']);
        $this->assertDatabaseHas('users', ['email' => 'fullsync@example.com']);
        $this->assertDatabaseHas('orders', ['order_number' => 'WC-7100']);
    }

    public function test_only_option_restricts_sync_entities(): void
    {
        // Product that would be imported
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 8001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Should Not Import',
            'post_name' => 'should-not-import',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 8001, 'meta_key' => '_price', 'meta_value' => '10.00'],
            ['post_id' => 8001, 'meta_key' => '_sku', 'meta_value' => 'SKIP-ME'],
        ]);

        // Order that should be imported
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 8100,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 8100',
            'post_name' => 'order-8100',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 8100, 'meta_key' => '_billing_email', 'meta_value' => 'only@example.com'],
            ['post_id' => 8100, 'meta_key' => '_order_total', 'meta_value' => '10.00'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['slug' => 'should-not-import']);
        $this->assertDatabaseHas('orders', ['order_number' => 'WC-8100']);
    }

    public function test_invalid_only_option_returns_failure(): void
    {
        $this->artisan('sync:wordpress', ['--only' => 'invalid'])
            ->assertFailed();
    }

    // ─── Data Quality & Safety ───────────────────────────────────────

    public function test_sync_does_not_trigger_product_observer_events(): void
    {
        // Create a tag, a follower, and a tag follow so the observer WOULD send email
        $tag = Tag::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        TagFollow::create([
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'notify_new_drops' => true,
            'notify_discounts' => true,
        ]);

        // Map the tag so the new product gets it
        DB::table('import_legacy_tags')->insert([
            'legacy_term_id' => 900,
            'tag_id' => $tag->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_terms')->insert([
            'term_id' => 900,
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        DB::connection('legacy')->table('wp_term_taxonomy')->insert([
            'term_taxonomy_id' => 900,
            'term_id' => 900,
            'taxonomy' => 'product_tag',
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 9001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Observer Test Product',
            'post_name' => 'observer-test-product',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 9001, 'meta_key' => '_price', 'meta_value' => '19.99'],
            ['post_id' => 9001, 'meta_key' => '_regular_price', 'meta_value' => '19.99'],
            ['post_id' => 9001, 'meta_key' => '_sku', 'meta_value' => 'OBS-01'],
        ]);

        DB::connection('legacy')->table('wp_term_relationships')->insert([
            ['object_id' => 9001, 'term_taxonomy_id' => 900],
        ]);

        Mail::fake();

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        // Product was created and tagged
        $product = Product::where('slug', 'observer-test-product')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->tags->contains($tag->id));

        // No emails were sent (observer was suppressed)
        Mail::assertNothingQueued();
    }

    public function test_sync_saves_product_dimensions(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 9010,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Dimension Product',
            'post_name' => 'dimension-product',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 9010, 'meta_key' => '_price', 'meta_value' => '15.00'],
            ['post_id' => 9010, 'meta_key' => '_regular_price', 'meta_value' => '15.00'],
            ['post_id' => 9010, 'meta_key' => '_weight', 'meta_value' => '0.450'],
            ['post_id' => 9010, 'meta_key' => '_length', 'meta_value' => '30.000'],
            ['post_id' => 9010, 'meta_key' => '_width', 'meta_value' => '20.000'],
            ['post_id' => 9010, 'meta_key' => '_height', 'meta_value' => '5.000'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'dimension-product')->first();
        $this->assertNotNull($product);
        $this->assertEquals(0.450, $product->weight);
        $this->assertEquals(30.000, $product->length);
        $this->assertEquals(20.000, $product->width);
        $this->assertEquals(5.000, $product->height);
    }

    public function test_sync_warns_on_unknown_wc_order_status(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 9020,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-custom-unknown',
            'post_title' => 'Order 9020',
            'post_name' => 'order-9020',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 9020, 'meta_key' => '_billing_email', 'meta_value' => 'unknown@example.com'],
            ['post_id' => 9020, 'meta_key' => '_order_total', 'meta_value' => '10.00'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful()
            ->expectsOutputToContain('unknown WC status');

        $order = Order::where('order_number', 'WC-9020')->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_sync_calculates_unit_price_per_unit_not_line_total(): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 9030,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'post_title' => 'Order 9030',
            'post_name' => 'order-9030',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 9030, 'meta_key' => '_billing_email', 'meta_value' => 'unit@example.com'],
            ['post_id' => 9030, 'meta_key' => '_order_total', 'meta_value' => '75.00'],
        ]);

        // 3 items at €25 each = _line_subtotal = 75.00
        DB::connection('legacy')->table('wp_woocommerce_order_items')->insert([
            'order_item_id' => 9301,
            'order_id' => 9030,
            'order_item_name' => 'Bulk Item',
            'order_item_type' => 'line_item',
        ]);

        DB::connection('legacy')->table('wp_woocommerce_order_itemmeta')->insert([
            ['order_item_id' => 9301, 'meta_key' => '_qty', 'meta_value' => '3'],
            ['order_item_id' => 9301, 'meta_key' => '_line_subtotal', 'meta_value' => '75.00'],
            ['order_item_id' => 9301, 'meta_key' => '_line_total', 'meta_value' => '75.00'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order = Order::where('order_number', 'WC-9030')->first();
        $orderItem = OrderItem::where('order_id', $order->id)->first();

        // unit_price = 75.00 / 3 = 25.00 (not 75.00)
        $this->assertSame('25.00', $orderItem->unit_price);
        $this->assertSame(3, $orderItem->quantity);
        $this->assertSame('75.00', $orderItem->line_total);
    }

    // ─── Subtotal & Discounts ────────────────────────────────────────

    public function test_sync_order_subtotal_adds_discount_back(): void
    {
        // Order: total=50 (post-discount), shipping=5, tax=3+0.94(shipping_tax), discount=10
        // Expected subtotal = 50 - 5 - 3.94 + 10 = 51.06 (pre-discount product value)
        $this->insertLegacyOrder(7001, 'wc-completed');
        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 7001, 'meta_key' => '_order_shipping', 'meta_value' => '5.00'],
            ['post_id' => 7001, 'meta_key' => '_order_tax', 'meta_value' => '3.00'],
            ['post_id' => 7001, 'meta_key' => '_order_shipping_tax', 'meta_value' => '0.94'],
            ['post_id' => 7001, 'meta_key' => '_cart_discount', 'meta_value' => '10.00'],
        ]);
        // Override the total set by insertLegacyOrder
        DB::connection('legacy')->table('wp_postmeta')
            ->where('post_id', 7001)
            ->where('meta_key', '_order_total')
            ->update(['meta_value' => '50.00']);

        $this->artisan('sync:wordpress', ['--only' => 'orders'])
            ->assertSuccessful();

        $order = Order::where('order_number', 'WC-7001')->first();
        $this->assertNotNull($order);
        $this->assertEquals(51.06, (float) $order->subtotal);
        $this->assertEquals(3.94, (float) $order->tax_total);
        $this->assertSame('10.00', $order->discount_total);
    }

    // ─── Product Status Sync ─────────────────────────────────────────

    public function test_sync_imports_private_wc_product_as_delisted(): void
    {
        // Create a private WC product
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6001,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'private',
            'post_title' => 'Discontinued Hoodie',
            'post_name' => 'discontinued-hoodie',
            'post_excerpt' => 'Was available once',
            'post_content' => '',
            'post_date' => '2025-01-01 00:00:00',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => 6001, 'meta_key' => '_price', 'meta_value' => '39.99'],
            ['post_id' => 6001, 'meta_key' => '_regular_price', 'meta_value' => '39.99'],
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product = Product::where('slug', 'discontinued-hoodie')->first();
        $this->assertNotNull($product);
        $this->assertSame('delisted', $product->status);
        $this->assertSame('Discontinued Hoodie', $product->name);

        $this->assertDatabaseHas('import_legacy_products', [
            'legacy_wp_post_id' => 6001,
            'product_id' => $product->id,
        ]);
    }

    public function test_sync_delists_product_when_wc_status_changes_to_private(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 6002,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6002,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'private',
            'post_title' => 'Was Public',
            'post_name' => 'was-public',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product->refresh();
        $this->assertSame('delisted', $product->status);
    }

    public function test_sync_relists_product_when_wc_status_changes_to_publish(): void
    {
        $product = Product::factory()->create(['status' => 'delisted']);
        DB::table('import_legacy_products')->insert([
            'legacy_wp_post_id' => 6003,
            'product_id' => $product->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => 6003,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Back In Stock',
            'post_name' => 'back-in-stock',
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        $this->artisan('sync:wordpress', ['--only' => 'products'])
            ->assertSuccessful();

        $product->refresh();
        $this->assertSame('active', $product->status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function insertLegacyOrder(int $id, string $status): void
    {
        DB::connection('legacy')->table('wp_posts')->insert([
            'ID' => $id,
            'post_parent' => 0,
            'post_author' => 0,
            'post_type' => 'shop_order',
            'post_status' => $status,
            'post_title' => "Order {$id}",
            'post_name' => "order-{$id}",
            'post_excerpt' => '',
            'post_content' => '',
            'post_date' => now()->toDateTimeString(),
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            ['post_id' => $id, 'meta_key' => '_billing_email', 'meta_value' => "order{$id}@example.com"],
            ['post_id' => $id, 'meta_key' => '_order_total', 'meta_value' => '10.00'],
        ]);
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
