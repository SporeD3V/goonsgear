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
    protected $signature = 'import:legacy-data
        {--dry-run : Preview import without saving changes}
        {--only= : Import only specific entities (categories,tags,products,variants,customers,orders)}';

    protected $description = 'Import categories, products, variants, customers, and orders from legacy WooCommerce database';

    /**
     * @var array<string, string>
     */
    private array $legacyAttributeTermNameCache = [];

    /**
     * @var array<string, int>
     */
    private array $stats = [
        'categories' => 0,
        'categories_skipped' => 0,
        'artist_tags' => 0,
        'genre_tags' => 0,
        'tags' => 0,
        'tags_skipped' => 0,
        'products' => 0,
        'simple_variants' => 0,
        'variants' => 0,
        'customers' => 0,
        'orders' => 0,
        'order_items' => 0,
        'products_skipped' => 0,
        'variants_skipped' => 0,
        'customers_skipped' => 0,
        'orders_skipped' => 0,
        'products_errors' => 0,
        'variants_errors' => 0,
        'customers_errors' => 0,
        'orders_errors' => 0,
    ];

    /**
     * WooCommerce category slugs that are actually artists, not product categories.
     *
     * @var list<string>
     */
    private const ARTIST_CATEGORY_SLUGS = [
        'snowgoons',
        'onyx',
        'sean-p',
        'lords-of-the-underground',
        'dod',
    ];

    /**
     * WooCommerce category slugs that are actually genres, not product categories.
     *
     * @var list<string>
     */
    private const GENRE_CATEGORY_SLUGS = [
        'germanhiphop',
        'indie-hip-hop',
        '90shiphop',
    ];

    public function handle(): int
    {
        $this->info('=== WooCommerce Legacy Import ===');
        $this->info('Mode: additive (existing data is preserved, only new records are created)');
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');
        $only = $this->parseOnlyOption();

        if ($only === false) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - Changes will be previewed but not saved');
            $this->newLine();
        }

        DB::beginTransaction();

        try {
            if ($this->shouldImport('categories', $only)) {
                $this->importCategories();
            }

            if ($this->shouldImport('tags', $only)) {
                $this->importTags();
            }

            if ($this->shouldImport('products', $only)) {
                $this->importProducts();
            }

            if ($this->shouldImport('variants', $only)) {
                $this->importSimpleProductVariants();
                $this->importVariants();
            }

            if ($this->shouldImport('customers', $only)) {
                $this->importCustomers();
            }

            if ($this->shouldImport('orders', $only)) {
                $this->importOrders();
            }

            $this->printSummary();

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN - All changes have been rolled back.');
            } else {
                DB::commit();
                $this->info('✓ Import complete.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
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

        $categoryCount = 0;
        $artistCount = 0;
        $genreCount = 0;

        foreach ($categories as $legacyCat) {
            // Artist categories become tags instead of categories
            if (in_array($legacyCat->slug, self::ARTIST_CATEGORY_SLUGS, true)) {
                $tag = Tag::where('slug', $legacyCat->slug)->first();

                if ($tag === null) {
                    $tag = Tag::create([
                        'name' => $legacyCat->name,
                        'slug' => $legacyCat->slug,
                        'type' => 'artist',
                        'is_active' => true,
                    ]);
                }

                DB::table('import_legacy_tags')->updateOrInsert(
                    ['legacy_term_id' => $legacyCat->term_id],
                    ['tag_id' => $tag->id, 'synced_at' => now()]
                );

                $artistCount++;

                continue;
            }

            // Genre categories become genre tags instead of categories
            if (in_array($legacyCat->slug, self::GENRE_CATEGORY_SLUGS, true)) {
                $tag = Tag::where('slug', $legacyCat->slug)->first();

                if ($tag === null) {
                    $tag = Tag::create([
                        'name' => $legacyCat->name,
                        'slug' => $legacyCat->slug,
                        'type' => 'genre',
                        'is_active' => true,
                    ]);
                }

                DB::table('import_legacy_tags')->updateOrInsert(
                    ['legacy_term_id' => $legacyCat->term_id],
                    ['tag_id' => $tag->id, 'synced_at' => now()]
                );

                $genreCount++;

                continue;
            }

            // Skip existing categories — preserve admin changes
            $existingCategory = Category::where('slug', $legacyCat->slug)->first();

            if ($existingCategory !== null) {
                DB::table('import_legacy_categories')->updateOrInsert(
                    ['legacy_term_id' => $legacyCat->term_id],
                    ['category_id' => $existingCategory->id, 'synced_at' => now()]
                );

                $this->stats['categories_skipped']++;

                continue;
            }

            $parent = null;
            if ($legacyCat->parent > 0) {
                $parentMapping = DB::table('import_legacy_categories')
                    ->where('legacy_term_id', $legacyCat->parent)
                    ->first();
                if ($parentMapping) {
                    $parent = $parentMapping->category_id;
                }
            }

            $category = Category::create([
                'name' => $legacyCat->name,
                'slug' => $legacyCat->slug,
                'parent_id' => $parent,
                'is_active' => true,
            ]);

            DB::table('import_legacy_categories')->updateOrInsert(
                ['legacy_term_id' => $legacyCat->term_id],
                ['category_id' => $category->id, 'synced_at' => now()]
            );

            $categoryCount++;
        }

        $this->info("✓ Imported {$categoryCount} categories, {$artistCount} artist tags, {$genreCount} genre tags.");
        $this->stats['categories'] = $categoryCount;
        $this->stats['artist_tags'] = $artistCount;
        $this->stats['genre_tags'] = $genreCount;
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
            $existingTag = Tag::where('slug', $legacyTag->slug)->first();

            if ($existingTag) {
                // Skip existing tags — preserve admin changes
                DB::table('import_legacy_tags')->updateOrInsert(
                    ['legacy_term_id' => $legacyTag->term_id],
                    ['tag_id' => $existingTag->id, 'synced_at' => now()]
                );

                $this->stats['tags_skipped']++;

                continue;
            }

            $tag = Tag::create([
                'name' => $legacyTag->name,
                'slug' => $legacyTag->slug,
                'type' => 'standard',
            ]);

            DB::table('import_legacy_tags')->updateOrInsert(
                ['legacy_term_id' => $legacyTag->term_id],
                ['tag_id' => $tag->id, 'synced_at' => now()]
            );

            $count++;
        }

        $this->info("✓ Imported {$count} tags.");
        $this->stats['tags'] = $count;
    }

    private function importProducts(): void
    {
        $this->info('Importing products...');

        $legacy = DB::connection('legacy');
        $products = $legacy->table('wp_posts')
            ->where('post_type', 'product')
            ->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_name', 'post_excerpt', 'post_content', 'post_date')
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

            // Skip already-imported products — preserve admin changes
            if ($existingProductId !== null) {
                $this->stats['products_skipped']++;

                continue;
            }

            // Also skip if a product with this slug already exists (created via admin)
            $slug = $legacyProd->post_name;
            $existingBySlug = Product::where('slug', $slug)->first();

            if ($existingBySlug !== null) {
                $this->syncLegacyProductMapping($legacyProd->ID, $existingBySlug->id);
                $this->stats['products_skipped']++;

                continue;
            }

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
                $fallbackSlug = "{$slug}-legacy-{$legacyProd->ID}";

                if (Product::where('slug', $slug)->exists()) {
                    $slug = $fallbackSlug;
                }

                $product = new Product;
                $product->slug = $slug;
                $product->fill([
                    'name' => $legacyProd->post_title,
                    'primary_category_id' => $categoryId,
                    'excerpt' => $legacyProd->post_excerpt,
                    'description' => $legacyProd->post_content,
                    'meta_title' => $meta['_yoast_wpseo_title'] ?? null,
                    'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null,
                    'status' => 'active',
                    'published_at' => Carbon::parse($legacyProd->post_date),
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
                $this->stats['products_errors']++;
            }

            if ($count % 100 === 0) {
                $this->line("  ... {$count} products");
            }
        }

        $this->info("✓ Imported {$count} products.");
        $this->stats['products'] = $count;
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

            // Skip already-imported variants
            if ($existingVariantId !== null) {
                $this->stats['variants_skipped']++;

                continue;
            }

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

            // Check if product already has a default variant — just map it
            $existingDefaultVariant = ProductVariant::where('product_id', $productId)
                ->where('name', 'Default')
                ->first();

            if ($existingDefaultVariant !== null) {
                $this->syncLegacyVariantMapping($legacyProd->ID, $existingDefaultVariant->id);
                $this->stats['variants_skipped']++;

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

                $variant = new ProductVariant([
                    'product_id' => $productId,
                    'name' => 'Default',
                ]);

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
                $this->stats['variants_errors']++;
            }
        }

        $this->info("✓ Imported {$count} simple product default variants.");
        $this->stats['simple_variants'] = $count;
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

            // Skip already-imported variants
            if ($existingVariantId !== null) {
                $this->stats['variants_skipped']++;

                continue;
            }

            $productId = $this->resolveMappedModelId(
                'import_legacy_products',
                'legacy_wp_post_id',
                $legacyVar->post_parent,
                'product_id',
                Product::class,
            );

            if ($productId === null) {
                $this->line("  ⚠ Orphan variant {$legacyVar->ID} (parent: {$legacyVar->post_parent}): parent product not found");
                $this->stats['variants_skipped']++;

                continue;
            }

            try {
                // Get meta
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyVar->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $sku = $meta['_sku'] ?? "var-{$legacyVar->ID}";

                // Check if variant already exists by SKU — just map it
                $existingVariant = ProductVariant::where('product_id', $productId)
                    ->where('sku', $sku)
                    ->first();

                if ($existingVariant !== null) {
                    $this->syncLegacyVariantMapping($legacyVar->ID, $existingVariant->id);
                    $this->stats['variants_skipped']++;

                    continue;
                }

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

                $variant = new ProductVariant([
                    'product_id' => $productId,
                    'name' => $variantName,
                ]);

                $variant->fill([
                    'product_id' => $productId,
                    'name' => $variantName,
                    'sku' => $this->uniqueVariantSku((string) $sku, null, $legacyVar->ID),
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
                $this->stats['variants_errors']++;
            }

            if ($count % 250 === 0) {
                $this->line("  ... {$count} variants");
            }
        }

        $this->info("✓ Imported {$count} variants.");
        $this->stats['variants'] = $count;
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
                $this->stats['customers_skipped']++;

                continue;
            }

            try {
                $existingUser = User::where('email', $legacyCust->user_email)->first();

                if ($existingUser !== null) {
                    // User already exists in our system — just record the mapping
                    $this->syncLegacyCustomerMapping($legacyCust->ID, $existingUser->id);
                    $this->stats['customers_skipped']++;

                    continue;
                }

                // Disable timestamps so created_at preserves the WP registration date
                $user = new User;
                $user->timestamps = false;
                $user->forceFill([
                    'name' => $legacyCust->user_login,
                    'email' => $legacyCust->user_email,
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                    'created_at' => $legacyCust->user_registered ?: now(),
                    'updated_at' => now(),
                ]);
                $user->save();
                $user->timestamps = true;

                $this->syncLegacyCustomerMapping($legacyCust->ID, $user->id);

                $count++;
            } catch (\Exception $e) {
                $this->line("  ⚠ Skipped user {$legacyCust->ID} ({$legacyCust->user_email}): {$e->getMessage()}");
                $this->stats['customers_errors']++;
            }
        }

        $this->info("✓ Imported {$count} customers.");
        $this->stats['customers'] = $count;
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
                $this->stats['orders_skipped']++;

                continue;
            }

            try {
                // Check if order already exists by order_number
                $existingOrder = Order::where('order_number', "WC-{$legacyOrder->ID}")->first();

                if ($existingOrder !== null) {
                    $this->syncLegacyOrderMapping($legacyOrder->ID, $existingOrder->id);
                    $this->stats['orders_skipped']++;

                    continue;
                }

                // Get meta
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyOrder->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                // Map order status
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

                // Map payment status based on WC order status
                $paymentStatusMap = [
                    'wc-completed' => 'completed',
                    'wc-processing' => 'paid',
                    'wc-on-hold' => 'pending',
                    'wc-pending' => 'pending',
                    'wc-cancelled' => 'cancelled',
                    'wc-refunded' => 'refunded',
                    'wc-failed' => 'failed',
                    'wc-pre-ordered' => 'paid',
                ];
                $paymentStatus = $paymentStatusMap[$legacyOrder->post_status] ?? 'pending';

                // Compute subtotal (total minus shipping and tax)
                $orderTotal = (float) ($meta['_order_total'] ?? 0);
                $shippingTotal = (float) ($meta['_order_shipping'] ?? 0);
                $taxTotal = (float) ($meta['_order_tax'] ?? 0);
                $subtotal = max(0, $orderTotal - $shippingTotal - $taxTotal);

                // Get coupon code from WC order items
                $couponItem = $legacy->table('wp_woocommerce_order_items')
                    ->where('order_id', $legacyOrder->ID)
                    ->where('order_item_type', 'coupon')
                    ->first();
                $couponCode = $couponItem?->order_item_name;

                // forceCreate bypasses $fillable so non-guarded fields
                // (shipping_total, tax_total, billing_*) are persisted
                $order = Order::forceCreate([
                    'order_number' => "WC-{$legacyOrder->ID}",
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
                    'subtotal' => $subtotal,
                    'total' => $orderTotal,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => $taxTotal,
                    'discount_total' => (float) ($meta['_cart_discount'] ?? 0),
                    'payment_method' => $meta['_payment_method'] ?? 'manual',
                    'payment_status' => $paymentStatus,
                    'coupon_code' => $couponCode,
                    'placed_at' => $legacyOrder->post_date,
                    'shipped_at' => in_array($status, ['completed']) && ! empty($meta['_date_completed'])
                        ? Carbon::createFromTimestamp((int) $meta['_date_completed'])
                        : null,
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
                    }

                    // Fallback: resolve product even when variant lookup failed or wasn't attempted
                    if ($newProductId === null && $productId) {
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

                    $this->stats['order_items']++;
                }

                $this->syncLegacyOrderMapping($legacyOrder->ID, $order->id);

                $count++;
                if ($count % 500 === 0) {
                    $this->line("  ... {$count} orders");
                }
            } catch (\Exception $e) {
                $this->warn("  ⚠ Failed to import order {$legacyOrder->ID}: {$e->getMessage()}");
                $this->stats['orders_errors']++;

                continue;
            }
        }

        $this->info("✓ Imported {$count} orders.");
        $this->stats['orders'] = $count;
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

        if ($modelClass::query()->whereKey($mappedId)->exists()) {
            return $mappedId;
        }

        // Clean up stale mapping — the referenced model no longer exists
        DB::table($mappingTable)->where($legacyColumn, $legacyId)->delete();

        return null;
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

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function resolveLegacyVariantOptionValues($legacyConnection, array $meta): array
    {
        $optionValues = [];

        foreach ($meta as $metaKey => $metaValue) {
            if (! str_starts_with($metaKey, 'attribute_')) {
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

    /**
     * @param  list<string>|null  $only
     */
    private function shouldImport(string $entity, ?array $only): bool
    {
        return $only === null || in_array($entity, $only, true);
    }

    /**
     * @return list<string>|null|false False if invalid values provided
     */
    private function parseOnlyOption(): array|null|false
    {
        $only = $this->option('only');

        if (! is_string($only) || trim($only) === '') {
            return null;
        }

        $valid = ['categories', 'tags', 'products', 'variants', 'customers', 'orders'];
        $selected = array_map('trim', explode(',', $only));
        $invalid = array_diff($selected, $valid);

        if ($invalid !== []) {
            $this->error('Invalid --only values: '.implode(', ', $invalid));
            $this->info('Valid values: '.implode(', ', $valid));

            return false;
        }

        return $selected;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->table(
            ['Entity', 'Imported', 'Skipped', 'Errors'],
            [
                ['Categories', $this->stats['categories'], $this->stats['categories_skipped'], '-'],
                ['Artist Tags', $this->stats['artist_tags'], '-', '-'],
                ['Genre Tags', $this->stats['genre_tags'], '-', '-'],
                ['Standard Tags', $this->stats['tags'], $this->stats['tags_skipped'], '-'],
                ['Products', $this->stats['products'], $this->stats['products_skipped'], $this->stats['products_errors']],
                ['Simple Variants', $this->stats['simple_variants'], $this->stats['variants_skipped'], '-'],
                ['Variants', $this->stats['variants'], $this->stats['variants_skipped'], $this->stats['variants_errors']],
                ['Customers', $this->stats['customers'], $this->stats['customers_skipped'], $this->stats['customers_errors']],
                ['Orders', $this->stats['orders'], $this->stats['orders_skipped'], $this->stats['orders_errors']],
                ['Order Items', $this->stats['order_items'], '-', '-'],
            ]
        );
    }
}
