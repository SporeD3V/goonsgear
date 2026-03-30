<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy-data {--skip-cleanup : Skip demo data cleanup}';

    protected $description = 'Import categories, products, variants, customers, and orders from legacy WooCommerce database';

    public function handle(): int
    {
        $this->info('=== WooCommerce Legacy Import ===');

        if (! $this->option('skip-cleanup')) {
            $this->cleanupDemoData();
        }

        try {
            // Import categories (must be first for foreign key constraints)
            $this->importCategories();

            // Import tags
            $this->importTags();

            // Import products (simple only, no variants yet)
            $this->importProducts();

            // Import product variants
            $this->importVariants();

            // Import customers/users
            $this->importCustomers();

            // Import orders and order items
            $this->importOrders();

            $this->info('✓ Import complete.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function cleanupDemoData(): void
    {
        $this->info('Cleaning up demo data...');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        DB::table('category_product')->truncate();
        DB::table('product_tag')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('product_media')->truncate();
        DB::table('product_variants')->truncate();
        DB::table('products')->truncate();
        DB::table('tags')->truncate();
        DB::table('categories')->truncate();

        // Reset auto-increment
        DB::statement('ALTER TABLE categories AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE products AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE product_variants AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE tags AUTO_INCREMENT = 1');

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->info('✓ Demo data cleaned up.');
    }

    private function importCategories(): void
    {
        $this->info('Importing categories...');

        $legacy = DB::connection('legacy');
        $categories = $legacy->table('wp_terms')
            ->join('wp_term_taxonomy', 'wp_terms.term_id', '=', 'wp_term_taxonomy.term_id')
            ->where('wp_term_taxonomy.taxonomy', 'product_cat')
            ->select('wp_terms.term_id', 'wp_terms.name', 'wp_terms.slug', 'wp_term_taxonomy.parent')
            ->orderBy('wp_term_taxonomy.parent')
            ->orderBy('wp_terms.term_id')
            ->get();

        $count = 0;
        foreach ($categories as $legacyCat) {
            $parent = null;
            if ($legacyCat->parent > 0) {
                $parentMapping = DB::table('import_legacy_categories')
                    ->where('legacy_term_id', $legacyCat->parent)
                    ->first();
                if ($parentMapping) {
                    $parent = $parentMapping->category_id;
                }
            }

            $category = Category::updateOrCreate(
                ['slug' => $legacyCat->slug],
                [
                    'name' => $legacyCat->name,
                    'parent_id' => $parent,
                    'is_active' => true,
                ]
            );

            DB::table('import_legacy_categories')->updateOrInsert(
                ['legacy_term_id' => $legacyCat->term_id],
                ['category_id' => $category->id, 'synced_at' => now()]
            );

            $count++;
        }

        $this->info("✓ Imported {$count} categories.");
    }

    private function importTags(): void
    {
        $this->info('Importing tags...');

        $legacy = DB::connection('legacy');
        $tags = $legacy->table('wp_terms')
            ->join('wp_term_taxonomy', 'wp_terms.term_id', '=', 'wp_term_taxonomy.term_id')
            ->where('wp_term_taxonomy.taxonomy', 'product_tag')
            ->select('wp_terms.term_id', 'wp_terms.name', 'wp_terms.slug')
            ->get();

        $count = 0;
        foreach ($tags as $legacyTag) {
            $tag = Tag::updateOrCreate(
                ['slug' => $legacyTag->slug],
                ['name' => $legacyTag->name, 'type' => 'standard']
            );

            DB::table('import_legacy_tags')->updateOrInsert(
                ['legacy_term_id' => $legacyTag->term_id],
                ['tag_id' => $tag->id, 'synced_at' => now()]
            );

            $count++;
        }

        $this->info("✓ Imported {$count} tags.");
    }

    private function importProducts(): void
    {
        $this->info('Importing products...');

        $legacy = DB::connection('legacy');
        $products = $legacy->table('wp_posts')
            ->where('post_type', 'product')
            ->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_name', 'post_excerpt', 'post_content')
            ->get();

        $count = 0;
        foreach ($products as $legacyProd) {
            // Get meta
            $meta = $legacy->table('wp_postmeta')
                ->where('post_id', $legacyProd->ID)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            // Get primary category
            $categoryId = null;
            $catTerm = $legacy->table('wp_term_relationships')
                ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
                ->where('wp_term_relationships.object_id', $legacyProd->ID)
                ->where('wp_term_taxonomy.taxonomy', 'product_cat')
                ->first();

            if ($catTerm) {
                $catMapping = DB::table('import_legacy_categories')
                    ->where('legacy_term_id', $catTerm->term_id)
                    ->first();
                if ($catMapping) {
                    $categoryId = $catMapping->category_id;
                }
            }

            $product = Product::updateOrCreate(
                ['slug' => $legacyProd->post_name],
                [
                    'name' => $legacyProd->post_title,
                    'primary_category_id' => $categoryId,
                    'excerpt' => $legacyProd->post_excerpt,
                    'description' => $legacyProd->post_content,
                    'meta_title' => $meta['_yoast_wpseo_title'] ?? null,
                    'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null,
                    'status' => 'published',
                    'published_at' => now(),
                    'weight' => $meta['_weight'] ?? null,
                    'length' => $meta['_length'] ?? null,
                    'width' => $meta['_width'] ?? null,
                    'height' => $meta['_height'] ?? null,
                ]
            );

            DB::table('import_legacy_products')->updateOrInsert(
                ['legacy_wp_post_id' => $legacyProd->ID],
                ['product_id' => $product->id, 'synced_at' => now()]
            );

            $count++;
            if ($count % 100 === 0) {
                $this->line("  ... {$count} products");
            }
        }

        $this->info("✓ Imported {$count} products.");
    }

    private function importVariants(): void
    {
        $this->info('Importing variants...');

        $legacy = DB::connection('legacy');
        $variants = $legacy->table('wp_posts')
            ->where('post_type', 'product_variation')
            ->where('post_status', 'publish')
            ->select('ID', 'post_parent', 'post_title', 'post_excerpt')
            ->get();

        $count = 0;
        foreach ($variants as $legacyVar) {
            // Get parent product
            $productMapping = DB::table('import_legacy_products')
                ->where('legacy_wp_post_id', $legacyVar->post_parent)
                ->first();

            if (! $productMapping) {
                continue; // Skip orphan variants
            }

            // Get meta
            $meta = $legacy->table('wp_postmeta')
                ->where('post_id', $legacyVar->ID)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            $sku = $meta['_sku'] ?? "var-{$legacyVar->ID}";
            $price = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
            $salePrice = (float) ($meta['_sale_price'] ?? null);
            $stock = (int) ($meta['_stock'] ?? 0);

            $variant = ProductVariant::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $productMapping->product_id,
                    'name' => $legacyVar->post_title ?: "Variant {$legacyVar->ID}",
                    'price' => $price,
                    'compare_at_price' => $salePrice && $salePrice < $price ? $price : null,
                    'stock_quantity' => $stock,
                    'track_inventory' => $meta['_manage_stock'] === 'yes',
                    'allow_backorder' => $meta['_backorders'] === 'yes',
                    'is_active' => true,
                ]
            );

            DB::table('import_legacy_variants')->updateOrInsert(
                ['legacy_wp_post_id' => $legacyVar->ID],
                ['product_variant_id' => $variant->id, 'synced_at' => now()]
            );

            $count++;
            if ($count % 250 === 0) {
                $this->line("  ... {$count} variants");
            }
        }

        $this->info("✓ Imported {$count} variants.");
    }

    private function importCustomers(): void
    {
        $this->info('Importing customers...');

        $legacy = DB::connection('legacy');
        $customers = $legacy->table('wp_users')
            ->where('ID', '>', 1) // Skip admin
            ->select('ID', 'user_email', 'user_login', 'user_registered')
            ->get();

        $count = 0;
        foreach ($customers as $legacyCust) {
            $user = User::updateOrCreate(
                ['email' => $legacyCust->user_email],
                [
                    'name' => $legacyCust->user_login,
                    'email_verified_at' => now(),
                ]
            );

            DB::table('import_legacy_customers')->updateOrInsert(
                ['legacy_wp_user_id' => $legacyCust->ID],
                ['user_id' => $user->id, 'synced_at' => now()]
            );

            $count++;
        }

        $this->info("✓ Imported {$count} customers.");
    }

    private function importOrders(): void
    {
        $this->info('Importing orders...');

        $legacy = DB::connection('legacy');
        $orders = $legacy->table('wp_posts')
            ->where('post_type', 'shop_order')
            ->where('post_status', '!=', 'trash')
            ->orderBy('post_date')
            ->select('ID', 'post_author', 'post_date', 'post_status')
            ->get();

        $count = 0;
        foreach ($orders as $legacyOrder) {
            // Get meta
            $meta = $legacy->table('wp_postmeta')
                ->where('post_id', $legacyOrder->ID)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            // Map status
            $statusMap = [
                'wc-completed' => 'completed',
                'wc-processing' => 'processing',
                'wc-on-hold' => 'on-hold',
                'wc-pending' => 'pending',
                'wc-cancelled' => 'cancelled',
                'wc-refunded' => 'refunded',
                'wc-failed' => 'failed',
                'wc-pre-ordered' => 'pre-ordered',
            ];
            $status = $statusMap[$legacyOrder->post_status] ?? 'pending';

            // Find customer
            $userId = null;
            if ($legacyOrder->post_author > 0) {
                $cusMapping = DB::table('import_legacy_customers')
                    ->where('legacy_wp_user_id', $legacyOrder->post_author)
                    ->first();
                if ($cusMapping) {
                    $userId = $cusMapping->user_id;
                }
            }

            $order = Order::create([
                'order_number' => "WC-{$legacyOrder->ID}",
                'status' => $status,
                'email' => $meta['_billing_email'] ?? '',
                'first_name' => $meta['_shipping_first_name'] ?? $meta['_billing_first_name'] ?? '',
                'last_name' => $meta['_shipping_last_name'] ?? $meta['_billing_last_name'] ?? '',
                'phone' => $meta['_billing_phone'] ?? null,
                'country' => $meta['_shipping_country'] ?? $meta['_billing_country'] ?? 'DE',
                'state' => $meta['_shipping_state'] ?? $meta['_billing_state'] ?? null,
                'city' => $meta['_shipping_city'] ?? $meta['_billing_city'] ?? '',
                'postal_code' => $meta['_shipping_postcode'] ?? $meta['_billing_postcode'] ?? '',
                'street_name' => $meta['_shipping_address_1'] ?? $meta['_billing_address_1'] ?? '',
                'street_number' => $meta['_shipping_address_2'] ?? $meta['_billing_address_2'] ?? null,
                'apartment_block' => null,
                'entrance' => null,
                'floor' => null,
                'apartment_number' => null,
                'currency' => 'EUR',
                'subtotal' => (float) ($meta['_order_total'] ?? 0),
                'total' => (float) ($meta['_order_total'] ?? 0),
                'shipping_total' => (float) ($meta['_order_shipping'] ?? 0),
                'tax_total' => (float) ($meta['_order_tax'] ?? 0),
                'discount_total' => (float) ($meta['_cart_discount'] ?? 0),
                'payment_method' => $meta['_payment_method'] ?? 'manual',
                'payment_status' => 'completed',
                'coupon_code' => null,
                'placed_at' => $legacyOrder->post_date,
                'billing_first_name' => $meta['_billing_first_name'] ?? null,
                'billing_last_name' => $meta['_billing_last_name'] ?? null,
                'billing_country' => $meta['_billing_country'] ?? null,
                'billing_state' => $meta['_billing_state'] ?? null,
                'billing_city' => $meta['_billing_city'] ?? null,
                'billing_postal_code' => $meta['_billing_postcode'] ?? null,
                'billing_street_name' => $meta['_billing_address_1'] ?? null,
                'billing_street_number' => $meta['_billing_address_2'] ?? null,
                'billing_apartment_block' => null,
                'billing_entrance' => null,
                'billing_floor' => null,
                'billing_apartment_number' => null,
            ]);

            // Import order items
            $items = $legacy->table('wp_woocommerce_order_items')
                ->where('order_id', $legacyOrder->ID)
                ->where('order_item_type', 'line_item')
                ->select('order_item_id', 'order_item_name')
                ->get();

            foreach ($items as $item) {
                $itemMeta = $legacy->table('wp_woocommerce_order_itemmeta')
                    ->where('order_item_id', $item->order_item_id)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $productId = $itemMeta['_product_id'] ?? null;
                $variantId = $itemMeta['_variation_id'] ?? null;

                $newProductId = null;
                $newVariantId = null;

                if ($variantId) {
                    $varMapping = DB::table('import_legacy_variants')
                        ->where('legacy_wp_post_id', $variantId)
                        ->first();
                    if ($varMapping) {
                        $newVariantId = $varMapping->product_variant_id;
                        $variant = ProductVariant::find($newVariantId);
                        $newProductId = $variant->product_id ?? null;
                    }
                } elseif ($productId) {
                    $prodMapping = DB::table('import_legacy_products')
                        ->where('legacy_wp_post_id', $productId)
                        ->first();
                    if ($prodMapping) {
                        $newProductId = $prodMapping->product_id;
                    }
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $newProductId,
                    'product_variant_id' => $newVariantId,
                    'product_name' => $item->order_item_name,
                    'variant_name' => $itemMeta['_variation_title'] ?? null,
                    'sku' => $itemMeta['_sku'] ?? 'LEGACY-ITEM',
                    'unit_price' => (float) ($itemMeta['_line_subtotal'] ?? 0),
                    'quantity' => (int) ($itemMeta['_qty'] ?? 1),
                    'line_total' => (float) ($itemMeta['_line_total'] ?? 0),
                ]);
            }

            DB::table('import_legacy_orders')->updateOrInsert(
                ['legacy_wc_order_id' => $legacyOrder->ID],
                ['order_id' => $order->id, 'synced_at' => now()]
            );

            $count++;
            if ($count % 500 === 0) {
                $this->line("  ... {$count} orders");
            }
        }

        $this->info("✓ Imported {$count} orders.");
    }
}
