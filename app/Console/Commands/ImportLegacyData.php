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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy-data {--skip-cleanup : Skip demo data cleanup}';

    protected $description = 'Import categories, products, variants, customers, and orders from legacy WooCommerce database';

    /**
     * @var array<string, string>
     */
    private array $legacyAttributeTermNameCache = [];

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

            // Create purchasable default variants for simple products
            $this->importSimpleProductVariants();

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
            $existingProductId = $this->resolveMappedModelId(
                'import_legacy_products',
                'legacy_wp_post_id',
                $legacyProd->ID,
                'product_id',
                Product::class,
            );

            try {
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

                $preorderAttributes = $this->legacyPreorderAttributes($meta);
                $slug = $legacyProd->post_name;
                $fallbackSlug = "{$slug}-legacy-{$legacyProd->ID}";
                $product = $existingProductId !== null
                    ? Product::query()->find($existingProductId)
                    : null;

                if ($product === null) {
                    $product = Product::query()
                        ->whereIn('slug', [$slug, $fallbackSlug])
                        ->first();
                }

                if ($product === null) {
                    $product = Product::query()
                        ->where('name', $legacyProd->post_title)
                        ->first();
                }

                if ($product === null) {
                    if (Product::where('slug', $slug)->exists()) {
                        $slug = $fallbackSlug;
                    }

                    $product = new Product;
                    $product->slug = $slug;
                }

                $product->fill([
                    'name' => $this->resolveImportedProductName($legacyProd->post_title, $product, (int) $legacyProd->ID),
                    'primary_category_id' => $categoryId,
                    'excerpt' => $legacyProd->post_excerpt,
                    'description' => $legacyProd->post_content,
                    'meta_title' => $meta['_yoast_wpseo_title'] ?? null,
                    'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null,
                    'status' => 'active',
                    'published_at' => $product->published_at ?? now(),
                    'is_preorder' => $preorderAttributes['is_preorder'],
                    'preorder_available_from' => $preorderAttributes['preorder_available_from'],
                    'expected_ship_at' => $preorderAttributes['expected_ship_at'],
                    'weight' => $meta['_weight'] ?? null,
                    'length' => $meta['_length'] ?? null,
                    'width' => $meta['_width'] ?? null,
                    'height' => $meta['_height'] ?? null,
                ]);
                $product->save();

                $this->syncLegacyProductMapping($legacyProd->ID, $product->id);

                $count++;
            } catch (\Exception $e) {
                $this->line("  ⚠ Skipped product {$legacyProd->ID} ({$legacyProd->post_title}): {$e->getMessage()}");
            }

            if ($count % 100 === 0) {
                $this->line("  ... {$count} products");
            }
        }

        $this->info("✓ Imported {$count} products.");
    }

    private function importSimpleProductVariants(): void
    {
        $this->info('Importing simple product default variants...');

        $legacy = DB::connection('legacy');
        $products = $legacy->table('wp_posts')
            ->where('post_type', 'product')
            ->where('post_status', 'publish')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('wp_posts as v')
                    ->whereColumn('v.post_parent', 'wp_posts.ID')
                    ->where('v.post_type', 'product_variation')
                    ->where('v.post_status', 'publish');
            })
            ->select('ID', 'post_title')
            ->get();

        $count = 0;
        foreach ($products as $legacyProd) {
            $existingVariantId = $this->resolveMappedModelId(
                'import_legacy_variants',
                'legacy_wp_post_id',
                $legacyProd->ID,
                'product_variant_id',
                ProductVariant::class,
            );

            $productId = $this->resolveMappedModelId(
                'import_legacy_products',
                'legacy_wp_post_id',
                $legacyProd->ID,
                'product_id',
                Product::class,
            );

            if ($productId === null) {
                continue;
            }

            try {
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyProd->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $price = (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0);
                $regularPrice = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
                $preorderAttributes = $this->legacyPreorderAttributes($meta);

                if ($price <= 0 && $regularPrice <= 0) {
                    $fallbackPrice = $this->resolveLegacySimpleProductFallbackPrice($legacy, (int) $legacyProd->ID);

                    if ($fallbackPrice === null) {
                        $this->line("  ⚠ Skipped simple product {$legacyProd->ID} ({$legacyProd->post_title}): no usable price found");

                        continue;
                    }

                    $price = $fallbackPrice;
                    $regularPrice = $fallbackPrice;

                    $this->line("  ↳ Recovered simple product {$legacyProd->ID} ({$legacyProd->post_title}) price from order history: {$fallbackPrice}");
                }

                $baseSku = (string) ($meta['_sku'] ?? "simple-{$legacyProd->ID}");
                $variant = $existingVariantId !== null
                    ? ProductVariant::query()->find($existingVariantId)
                    : null;

                if ($variant === null) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $productId)
                        ->where('name', 'Default')
                        ->first();
                }

                if ($variant === null && $baseSku !== '') {
                    $variant = ProductVariant::query()
                        ->where('sku', $baseSku)
                        ->first();
                }

                if ($variant === null) {
                    $variant = new ProductVariant([
                        'product_id' => $productId,
                        'name' => 'Default',
                    ]);
                }

                $variant->fill([
                    'product_id' => $productId,
                    'name' => 'Default',
                    'sku' => $this->uniqueVariantSku($baseSku, $variant->exists ? $variant->id : null, $legacyProd->ID),
                    'option_values' => null,
                    'price' => $price > 0 ? $price : $regularPrice,
                    'compare_at_price' => $regularPrice > $price && $price > 0 ? $regularPrice : null,
                    'stock_quantity' => (int) ($meta['_stock'] ?? 0),
                    'track_inventory' => ($meta['_manage_stock'] ?? 'no') === 'yes',
                    'allow_backorder' => ($meta['_backorders'] ?? 'no') === 'yes',
                    'is_active' => true,
                    'is_preorder' => $preorderAttributes['is_preorder'],
                    'preorder_available_from' => $preorderAttributes['preorder_available_from'],
                    'expected_ship_at' => $preorderAttributes['expected_ship_at'],
                ]);
                $variant->save();

                $this->syncLegacyVariantMapping($legacyProd->ID, $variant->id);

                $count++;
            } catch (\Exception $e) {
                $this->line("  ⚠ Skipped simple product {$legacyProd->ID} ({$legacyProd->post_title}): {$e->getMessage()}");
            }
        }

        $this->info("✓ Imported {$count} simple product default variants.");
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
            $existingVariantId = $this->resolveMappedModelId(
                'import_legacy_variants',
                'legacy_wp_post_id',
                $legacyVar->ID,
                'product_variant_id',
                ProductVariant::class,
            );

            $productId = $this->resolveMappedModelId(
                'import_legacy_products',
                'legacy_wp_post_id',
                $legacyVar->post_parent,
                'product_id',
                Product::class,
            );

            if ($productId === null) {
                continue; // Skip orphan variants
            }

            try {
                // Get meta
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyVar->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $sku = $meta['_sku'] ?? "var-{$legacyVar->ID}";
                $price = (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0);
                $regularPrice = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
                $stock = (int) ($meta['_stock'] ?? 0);
                $variantName = $legacyVar->post_title ?: "Variant {$legacyVar->ID}";
                $parentMeta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyVar->post_parent)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();
                $preorderAttributes = $this->legacyPreorderAttributes($meta, $parentMeta);
                $optionValues = $this->resolveLegacyVariantOptionValues($legacy, $meta);
                $variantType = $this->resolveLegacyVariantType($variantName, $optionValues);

                $variant = $existingVariantId !== null
                    ? ProductVariant::query()->find($existingVariantId)
                    : null;

                if ($variant === null) {
                    $variant = ProductVariant::query()
                        ->where('sku', $sku)
                        ->first();
                }

                if ($variant === null) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $productId)
                        ->where('name', $variantName)
                        ->first();
                }

                if ($variant === null) {
                    $variant = new ProductVariant([
                        'product_id' => $productId,
                        'name' => $variantName,
                    ]);
                }

                $variant->fill([
                    'product_id' => $productId,
                    'name' => $variantName,
                    'sku' => $this->uniqueVariantSku((string) $sku, $variant->exists ? $variant->id : null, $legacyVar->ID),
                    'variant_type' => $variantType,
                    'option_values' => $optionValues !== [] ? $optionValues : null,
                    'price' => $price,
                    'compare_at_price' => $regularPrice > $price && $price > 0 ? $regularPrice : null,
                    'stock_quantity' => $stock,
                    'track_inventory' => ($meta['_manage_stock'] ?? 'no') === 'yes',
                    'allow_backorder' => ($meta['_backorders'] ?? 'no') === 'yes',
                    'is_active' => true,
                    'is_preorder' => $preorderAttributes['is_preorder'],
                    'preorder_available_from' => $preorderAttributes['preorder_available_from'],
                    'expected_ship_at' => $preorderAttributes['expected_ship_at'],
                ]);
                $variant->save();

                $this->syncLegacyVariantMapping($legacyVar->ID, $variant->id);

                $count++;
            } catch (\Exception $e) {
                $this->line("  ⚠ Skipped variant {$legacyVar->ID}: {$e->getMessage()}");
            }

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
            $existingUserId = $this->resolveMappedModelId(
                'import_legacy_customers',
                'legacy_wp_user_id',
                $legacyCust->ID,
                'user_id',
                User::class,
            );

            if ($existingUserId !== null) {
                $count++;

                continue;
            }

            try {
                $user = User::firstOrNew(['email' => $legacyCust->user_email]);
                $user->fill([
                    'name' => $legacyCust->user_login,
                    'password' => $user->exists ? $user->password : bcrypt(Str::random(32)),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
                $user->save();

                $this->syncLegacyCustomerMapping($legacyCust->ID, $user->id);

                $count++;
            } catch (\Exception $e) {
                $this->line("  ⚠ Skipped user {$legacyCust->ID} ({$legacyCust->user_email}): {$e->getMessage()}");
            }
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
            $existingOrderId = $this->resolveMappedModelId(
                'import_legacy_orders',
                'legacy_wc_order_id',
                $legacyOrder->ID,
                'order_id',
                Order::class,
            );

            if ($existingOrderId !== null) {
                $count++;

                continue;
            }

            try {
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

                $order = Order::firstOrNew([
                    'order_number' => "WC-{$legacyOrder->ID}",
                ]);
                $order->fill([
                    'status' => $status,
                    'email' => $meta['_billing_email'] ?? '',
                    'first_name' => $meta['_shipping_first_name'] ?? $meta['_billing_first_name'] ?? '',
                    'last_name' => $meta['_shipping_last_name'] ?? $meta['_billing_last_name'] ?? '',
                    'phone' => $meta['_billing_phone'] ?? null,
                    'country' => $meta['_shipping_country'] ?? $meta['_billing_country'] ?? 'DE',
                    'state' => $meta['_shipping_state'] ?? $meta['_billing_state'] ?? '',
                    'city' => $meta['_shipping_city'] ?? $meta['_billing_city'] ?? '',
                    'postal_code' => $meta['_shipping_postcode'] ?? $meta['_billing_postcode'] ?? '',
                    'street_name' => $meta['_shipping_address_1'] ?? $meta['_billing_address_1'] ?? '',
                    'street_number' => $meta['_shipping_address_2'] ?? $meta['_billing_address_2'] ?? '',
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
                $order->save();

                // Import order items
                if (! $order->wasRecentlyCreated && DB::table('import_legacy_orders')->where('legacy_wc_order_id', $legacyOrder->ID)->exists()) {
                    $count++;

                    continue;
                }

                if ($order->wasRecentlyCreated) {
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
                            $mappedVariantId = $this->resolveMappedModelId(
                                'import_legacy_variants',
                                'legacy_wp_post_id',
                                (int) $variantId,
                                'product_variant_id',
                                ProductVariant::class,
                            );

                            if ($mappedVariantId !== null) {
                                $newVariantId = $mappedVariantId;
                                $variant = ProductVariant::find($mappedVariantId);
                                $newProductId = $variant?->product_id;
                            }
                        } elseif ($productId) {
                            $newProductId = $this->resolveMappedModelId(
                                'import_legacy_products',
                                'legacy_wp_post_id',
                                (int) $productId,
                                'product_id',
                                Product::class,
                            );
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
                }

                $this->syncLegacyOrderMapping($legacyOrder->ID, $order->id);

                $count++;
                if ($count % 500 === 0) {
                    $this->line("  ... {$count} orders");
                }
            } catch (\Exception $e) {
                $this->warn("  ⚠ Failed to import order {$legacyOrder->ID}: {$e->getMessage()}");

                continue;
            }
        }

        $this->info("✓ Imported {$count} orders.");
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveMappedModelId(string $mappingTable, string $legacyColumn, int $legacyId, string $mappedColumn, string $modelClass): ?int
    {
        $mapping = DB::table($mappingTable)
            ->where($legacyColumn, $legacyId)
            ->first();

        if ($mapping === null) {
            return null;
        }

        $mappedId = (int) $mapping->{$mappedColumn};

        return $modelClass::query()->whereKey($mappedId)->exists() ? $mappedId : null;
    }

    private function syncLegacyProductMapping(int $legacyPostId, int $productId): void
    {
        DB::table('import_legacy_products')->updateOrInsert(
            ['legacy_wp_post_id' => $legacyPostId],
            ['product_id' => $productId, 'synced_at' => now()]
        );
    }

    private function syncLegacyVariantMapping(int $legacyPostId, int $variantId): void
    {
        DB::table('import_legacy_variants')->updateOrInsert(
            ['legacy_wp_post_id' => $legacyPostId],
            ['product_variant_id' => $variantId, 'synced_at' => now()]
        );
    }

    private function syncLegacyCustomerMapping(int $legacyUserId, int $userId): void
    {
        DB::table('import_legacy_customers')->updateOrInsert(
            ['legacy_wp_user_id' => $legacyUserId],
            ['user_id' => $userId, 'synced_at' => now()]
        );
    }

    private function syncLegacyOrderMapping(int $legacyOrderId, int $orderId): void
    {
        DB::table('import_legacy_orders')->updateOrInsert(
            ['legacy_wc_order_id' => $legacyOrderId],
            ['order_id' => $orderId, 'synced_at' => now()]
        );
    }

    private function uniqueVariantSku(string $baseSku, ?int $ignoreVariantId = null, ?int $legacyId = null): string
    {
        $baseSku = $baseSku !== '' ? $baseSku : 'legacy-variant';
        $candidate = $baseSku;
        $attempt = 0;

        while (
            ProductVariant::query()
                ->where('sku', $candidate)
                ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
                ->exists()
        ) {
            $attempt++;
            $suffix = $legacyId !== null ? (string) $legacyId : (string) $attempt;
            $candidate = $attempt === 1 ? "{$baseSku}-{$suffix}" : "{$baseSku}-{$suffix}-{$attempt}";
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $fallbackMeta
     * @return array{is_preorder: bool, preorder_available_from: ?Carbon, expected_ship_at: ?Carbon}
     */
    private function legacyPreorderAttributes(array $meta, ?array $fallbackMeta = null): array
    {
        $preorderDate = $this->parseLegacyPreorderDate(
            $meta['_pre_order_date'] ?? $fallbackMeta['_pre_order_date'] ?? null,
        );

        if ($preorderDate === null || $preorderDate->endOfDay()->isPast()) {
            return [
                'is_preorder' => false,
                'preorder_available_from' => null,
                'expected_ship_at' => null,
            ];
        }

        return [
            'is_preorder' => true,
            'preorder_available_from' => $preorderDate->startOfDay(),
            'expected_ship_at' => null,
        ];
    }

    private function parseLegacyPreorderDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveLegacySimpleProductFallbackPrice($legacyConnection, int $legacyProductId): ?float
    {
        $rows = $legacyConnection->table('wp_woocommerce_order_itemmeta as product_meta')
            ->join('wp_woocommerce_order_items as items', 'items.order_item_id', '=', 'product_meta.order_item_id')
            ->join('wp_posts as orders', 'orders.ID', '=', 'items.order_id')
            ->leftJoin('wp_woocommerce_order_itemmeta as qty_meta', function ($join) {
                $join->on('qty_meta.order_item_id', '=', 'items.order_item_id')
                    ->where('qty_meta.meta_key', '_qty');
            })
            ->leftJoin('wp_woocommerce_order_itemmeta as subtotal_meta', function ($join) {
                $join->on('subtotal_meta.order_item_id', '=', 'items.order_item_id')
                    ->where('subtotal_meta.meta_key', '_line_subtotal');
            })
            ->leftJoin('wp_woocommerce_order_itemmeta as total_meta', function ($join) {
                $join->on('total_meta.order_item_id', '=', 'items.order_item_id')
                    ->where('total_meta.meta_key', '_line_total');
            })
            ->where('product_meta.meta_key', '_product_id')
            ->where('product_meta.meta_value', (string) $legacyProductId)
            ->where('items.order_item_type', 'line_item')
            ->whereIn('orders.post_status', ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-pre-ordered'])
            ->orderByDesc('orders.post_date')
            ->orderByDesc('items.order_item_id')
            ->select('qty_meta.meta_value as qty', 'subtotal_meta.meta_value as subtotal', 'total_meta.meta_value as total')
            ->limit(50)
            ->get();

        foreach ($rows as $row) {
            $qty = (float) ($row->qty ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $subtotal = (float) ($row->subtotal ?? 0);
            if ($subtotal > 0) {
                return round($subtotal / $qty, 2);
            }

            $total = (float) ($row->total ?? 0);
            if ($total > 0) {
                return round($total / $qty, 2);
            }
        }

        return null;
    }

    private function resolveImportedProductName(string $legacyName, Product $product, int $legacyId): string
    {
        $conflictExists = Product::query()
            ->where('name', $legacyName)
            ->whereKeyNot($product->id)
            ->exists();

        if (! $conflictExists) {
            return $legacyName;
        }

        if ($product->exists && $product->name !== '') {
            return $product->name;
        }

        $fallbackName = "{$legacyName} (Legacy {$legacyId})";
        $suffix = 1;

        while (
            Product::query()
                ->where('name', $fallbackName)
                ->exists()
        ) {
            $suffix++;
            $fallbackName = "{$legacyName} (Legacy {$legacyId}-{$suffix})";
        }

        return $fallbackName;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function resolveLegacyVariantOptionValues($legacyConnection, array $meta): array
    {
        $optionValues = [];

        foreach ($meta as $metaKey => $metaValue) {
            if (! is_string($metaKey) || ! str_starts_with($metaKey, 'attribute_')) {
                continue;
            }

            if (! is_scalar($metaValue)) {
                continue;
            }

            $value = trim((string) $metaValue);
            if ($value === '') {
                continue;
            }

            $taxonomy = Str::replaceFirst('attribute_', '', $metaKey);
            $attributeKey = $this->normalizeLegacyAttributeKey($taxonomy);
            $attributeValue = $this->resolveLegacyAttributeValueName($legacyConnection, (string) $taxonomy, $value, $attributeKey);

            if ($attributeValue === '') {
                continue;
            }

            $optionValues[$attributeKey] = $attributeValue;
        }

        return $optionValues;
    }

    private function normalizeLegacyAttributeKey(string $taxonomy): string
    {
        $normalized = Str::of($taxonomy)
            ->replace('pa_', '')
            ->snake()
            ->toString();

        return match ($normalized) {
            'colour', 'farbe', 'couleur' => 'color',
            'groesse', 'taille' => 'size',
            default => $normalized !== '' ? $normalized : 'option',
        };
    }

    private function resolveLegacyAttributeValueName($legacyConnection, string $taxonomy, string $rawValue, string $attributeKey): string
    {
        $cacheKey = $taxonomy.'|'.$rawValue;

        if (array_key_exists($cacheKey, $this->legacyAttributeTermNameCache)) {
            return $this->legacyAttributeTermNameCache[$cacheKey];
        }

        $termName = $legacyConnection->table('wp_terms')
            ->join('wp_term_taxonomy', 'wp_terms.term_id', '=', 'wp_term_taxonomy.term_id')
            ->where('wp_term_taxonomy.taxonomy', $taxonomy)
            ->where('wp_terms.slug', $rawValue)
            ->value('wp_terms.name');

        if (is_string($termName) && trim($termName) !== '') {
            $resolved = trim($termName);
        } else {
            $resolved = trim(str_replace(['-', '_'], ' ', $rawValue));

            if ($attributeKey === 'size') {
                $resolved = Str::upper($resolved);
            } else {
                $resolved = Str::of($resolved)->headline()->toString();
            }
        }

        $this->legacyAttributeTermNameCache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * @param  array<string, string>  $optionValues
     */
    private function resolveLegacyVariantType(string $variantName, array $optionValues): string
    {
        if (count($optionValues) > 1) {
            return 'custom';
        }

        if (array_key_exists('size', $optionValues)) {
            return 'size';
        }

        if (array_key_exists('color', $optionValues)) {
            return 'color';
        }

        return ProductVariant::detectTypeFromName($variantName);
    }
}
