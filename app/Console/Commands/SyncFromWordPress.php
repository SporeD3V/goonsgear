<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncFromWordPress extends Command
{
    protected $signature = 'sync:wordpress
        {--dry-run : Preview sync without saving changes}
        {--only= : Sync only specific entities (prices,orders,products,customers)}';

    protected $description = 'Sync stock/prices, new orders, and new products from the live WooCommerce database';

    /**
     * @var array<string, int>
     */
    private array $stats = [
        'variants_updated' => 0,
        'variants_unchanged' => 0,
        'variants_skipped' => 0,
        'products_created' => 0,
        'products_skipped' => 0,
        'products_delisted' => 0,
        'products_relisted' => 0,
        'products_tags_synced' => 0,
        'simple_variants_created' => 0,
        'variants_created' => 0,
        'customers_created' => 0,
        'customers_skipped' => 0,
        'orders_status_updated' => 0,
        'orders_status_unchanged' => 0,
        'orders_refund_updated' => 0,
        'orders_created' => 0,
        'orders_skipped' => 0,
        'order_items_created' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $this->info('=== WooCommerce → GG Sync ===');
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');
        $only = $this->parseOnlyOption();

        if ($only === false) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE — Changes will be previewed but not saved');
            $this->newLine();
        }

        DB::beginTransaction();

        try {
            // Suppress model events to prevent observer side effects
            // (e.g. ProductObserver sending tag follower emails on product creation,
            // ProductVariantObserver sending cart discount/low-stock alerts on price changes)
            Product::withoutEvents(function () use ($only): void {
                ProductVariant::withoutEvents(function () use ($only): void {
                    if ($this->shouldSync('prices', $only)) {
                        $this->syncPricesAndStock();
                    }

                    if ($this->shouldSync('products', $only)) {
                        $this->syncProductStatuses();
                        $this->syncNewProducts();
                    }

                    if ($this->shouldSync('customers', $only)) {
                        $this->syncNewCustomers();
                    }

                    if ($this->shouldSync('orders', $only)) {
                        $this->syncExistingOrderStatuses();
                        $this->syncNewOrders();
                    }
                });
            });

            $this->flushDashboardCache();
            $this->printSummary();

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN — All changes rolled back.');
            } else {
                DB::commit();
                $this->info('✓ Sync complete.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Sync prices, sale prices, stock, and pre-order fields for all mapped variants.
     * Does NOT touch product-level fields (name, description, meta, categories, media).
     */
    private function syncPricesAndStock(): void
    {
        $this->info('Syncing prices & stock for existing variants...');

        $legacy = DB::connection('legacy');

        // Load all variant mappings in one query
        $mappings = DB::table('import_legacy_variants')
            ->join('product_variants', 'import_legacy_variants.product_variant_id', '=', 'product_variants.id')
            ->select(
                'import_legacy_variants.legacy_wp_post_id',
                'import_legacy_variants.product_variant_id',
                'product_variants.price as current_price',
                'product_variants.compare_at_price as current_compare_at_price',
                'product_variants.stock_quantity as current_stock',
                'product_variants.is_preorder as current_is_preorder',
            )
            ->get();

        if ($mappings->isEmpty()) {
            $this->line('  No mapped variants found. Run import:legacy-data first.');

            return;
        }

        $this->line("  Found {$mappings->count()} mapped variants.");

        // Batch-fetch WC meta for all mapped variants
        $legacyIds = $mappings->pluck('legacy_wp_post_id')->toArray();

        // Process in chunks to avoid huge IN clauses
        $updated = 0;
        $unchanged = 0;

        foreach (array_chunk($legacyIds, 500) as $chunkIds) {
            $metaRows = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $chunkIds)
                ->whereIn('meta_key', [
                    '_price', '_regular_price', '_sale_price',
                    '_stock', '_manage_stock', '_backorders',
                    '_pre_order_date',
                ])
                ->get()
                ->groupBy('post_id');

            // Also fetch parent post IDs for pre-order fallback
            $parentMap = $legacy->table('wp_posts')
                ->whereIn('ID', $chunkIds)
                ->where('post_type', 'product_variation')
                ->pluck('post_parent', 'ID');

            $parentIds = $parentMap->values()->unique()->filter()->toArray();
            $parentPreorderMeta = [];

            if ($parentIds !== []) {
                $parentPreorderMeta = $legacy->table('wp_postmeta')
                    ->whereIn('post_id', $parentIds)
                    ->where('meta_key', '_pre_order_date')
                    ->pluck('meta_value', 'post_id')
                    ->toArray();
            }

            foreach ($mappings->whereIn('legacy_wp_post_id', $chunkIds) as $mapping) {
                $meta = ($metaRows[$mapping->legacy_wp_post_id] ?? collect())
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                // Resolve pre-order from variant meta, fall back to parent
                $preorderDate = $meta['_pre_order_date']
                    ?? ($parentPreorderMeta[$parentMap[$mapping->legacy_wp_post_id] ?? 0] ?? null);
                $preorderAttributes = $this->parsePreorderAttributes($preorderDate);

                $wcPrice = (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0);
                $wcRegularPrice = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
                $wcCompareAt = $wcRegularPrice > $wcPrice && $wcPrice > 0 ? $wcRegularPrice : null;
                $wcStock = (int) ($meta['_stock'] ?? 0);

                // Check if anything actually changed
                $priceChanged = bccomp((string) $wcPrice, (string) $mapping->current_price, 2) !== 0;
                $compareChanged = ($wcCompareAt === null) !== ($mapping->current_compare_at_price === null)
                    || ($wcCompareAt !== null && bccomp((string) $wcCompareAt, (string) $mapping->current_compare_at_price, 2) !== 0);
                $stockChanged = $wcStock !== $mapping->current_stock;
                $preorderChanged = $preorderAttributes['is_preorder'] !== (bool) $mapping->current_is_preorder;

                if (! $priceChanged && ! $compareChanged && ! $stockChanged && ! $preorderChanged) {
                    $unchanged++;

                    continue;
                }

                ProductVariant::where('id', $mapping->product_variant_id)->update([
                    'price' => $wcPrice,
                    'compare_at_price' => $wcCompareAt,
                    'stock_quantity' => $wcStock,
                    'track_inventory' => ($meta['_manage_stock'] ?? 'no') === 'yes',
                    'allow_backorder' => ($meta['_backorders'] ?? 'no') === 'yes',
                    'is_preorder' => $preorderAttributes['is_preorder'],
                    'preorder_available_from' => $preorderAttributes['preorder_available_from'],
                    'expected_ship_at' => $preorderAttributes['expected_ship_at'],
                ]);

                $updated++;
            }
        }

        $this->info("✓ Updated {$updated} variants, {$unchanged} unchanged.");
        $this->stats['variants_updated'] = $updated;
        $this->stats['variants_unchanged'] = $unchanged;
    }

    /**
     * Detect WC products that changed between publish↔private and update our status accordingly.
     * publish → active, private/draft/trash → delisted.
     */
    private function syncProductStatuses(): void
    {
        $this->info('Checking for product status changes...');

        $legacy = DB::connection('legacy');

        $mappedProducts = DB::table('import_legacy_products')
            ->join('products', 'products.id', '=', 'import_legacy_products.product_id')
            ->select('import_legacy_products.legacy_wp_post_id', 'products.id as product_id', 'products.status')
            ->get();

        if ($mappedProducts->isEmpty()) {
            return;
        }

        $wcStatuses = $legacy->table('wp_posts')
            ->where('post_type', 'product')
            ->whereIn('ID', $mappedProducts->pluck('legacy_wp_post_id'))
            ->pluck('post_status', 'ID');

        foreach ($mappedProducts as $mapped) {
            $wcStatus = $wcStatuses[$mapped->legacy_wp_post_id] ?? null;

            if ($wcStatus === null) {
                continue;
            }

            $expectedStatus = $wcStatus === 'publish' ? 'active' : 'delisted';

            if ($mapped->status === $expectedStatus) {
                continue;
            }

            Product::where('id', $mapped->product_id)->update(['status' => $expectedStatus]);

            if ($expectedStatus === 'delisted') {
                $this->stats['products_delisted']++;
                $this->line("  ⊘ Delisted product #{$mapped->product_id} (WC status: {$wcStatus})");
            } else {
                $this->stats['products_relisted']++;
                $this->line("  ↑ Relisted product #{$mapped->product_id} (WC status: {$wcStatus})");
            }
        }

        $total = $this->stats['products_delisted'] + $this->stats['products_relisted'];

        if ($total > 0) {
            $this->info("✓ {$this->stats['products_delisted']} delisted, {$this->stats['products_relisted']} relisted.");
        } else {
            $this->line('  No status changes detected.');
        }
    }

    /**
     * Import products that exist in WC but not in our database.
     * Creates the product + its variants + mapping records.
     */
    private function syncNewProducts(): void
    {
        $this->info('Checking for new WC products...');

        $legacy = DB::connection('legacy');

        // Find WC product IDs not yet mapped
        $existingLegacyIds = DB::table('import_legacy_products')
            ->pluck('legacy_wp_post_id')
            ->toArray();

        $newProducts = $legacy->table('wp_posts')
            ->where('post_type', 'product')
            ->whereIn('post_status', ['publish', 'private'])
            ->whereNotIn('ID', $existingLegacyIds)
            ->select('ID', 'post_title', 'post_name', 'post_excerpt', 'post_content', 'post_date', 'post_status')
            ->get();

        if ($newProducts->isEmpty()) {
            $this->line('  No new products found.');

            return;
        }

        $this->line("  Found {$newProducts->count()} new products.");

        foreach ($newProducts as $legacyProd) {
            // Double-check a product with this slug doesn't already exist
            if (Product::where('slug', $legacyProd->post_name)->exists()) {
                $this->stats['products_skipped']++;

                continue;
            }

            try {
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyProd->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                // Resolve primary category via mapping
                $categoryId = $this->resolvePrimaryCategory($legacy, $legacyProd->ID);
                $preorderAttributes = $this->parsePreorderAttributes($meta['_pre_order_date'] ?? null);

                $product = new Product;
                $product->slug = $legacyProd->post_name;
                $product->fill([
                    'name' => $legacyProd->post_title,
                    'primary_category_id' => $categoryId,
                    'excerpt' => $legacyProd->post_excerpt,
                    'description' => $legacyProd->post_content,
                    'meta_title' => $meta['_yoast_wpseo_title'] ?? null,
                    'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null,
                    'status' => $legacyProd->post_status === 'publish' ? 'active' : 'delisted',
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

                $this->syncMapping('import_legacy_products', 'legacy_wp_post_id', $legacyProd->ID, 'product_id', $product->id);
                $this->stats['products_created']++;

                // Import this product's variants
                $this->syncVariantsForProduct($legacy, $legacyProd->ID, $product->id, $meta);

                // Assign tags via legacy mapping
                $this->syncTagsForProduct($legacy, $legacyProd->ID, $product);

                $this->line("  + {$legacyProd->post_title}");
            } catch (\Exception $e) {
                $this->warn("  ⚠ Failed to import product {$legacyProd->ID} ({$legacyProd->post_title}): {$e->getMessage()}");
                $this->stats['errors']++;
            }
        }

        $this->info("✓ Created {$this->stats['products_created']} products, {$this->stats['simple_variants_created']} simple variants, {$this->stats['variants_created']} variants.");
    }

    /**
     * Import variants for a newly created product.
     *
     * @param  Connection  $legacy
     * @param  array<string, mixed>  $parentMeta
     */
    private function syncVariantsForProduct($legacy, int $legacyProductId, int $productId, array $parentMeta): void
    {
        $variations = $legacy->table('wp_posts')
            ->where('post_parent', $legacyProductId)
            ->where('post_type', 'product_variation')
            ->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_excerpt')
            ->get();

        if ($variations->isEmpty()) {
            // Simple product — create a default variant
            $this->createSimpleVariant($legacy, $legacyProductId, $productId, $parentMeta);

            return;
        }

        foreach ($variations as $legacyVar) {
            try {
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyVar->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $sku = $meta['_sku'] ?? "var-{$legacyVar->ID}";
                $price = (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0);
                $regularPrice = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
                $stock = (int) ($meta['_stock'] ?? 0);
                $variantName = $legacyVar->post_title ?: "Variant {$legacyVar->ID}";
                $preorderAttributes = $this->parsePreorderAttributes(
                    $meta['_pre_order_date'] ?? $parentMeta['_pre_order_date'] ?? null,
                );
                $optionValues = $this->resolveLegacyVariantOptionValues($legacy, $meta);
                $variantType = $this->resolveLegacyVariantType($variantName, $optionValues);

                $variant = new ProductVariant;
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

                $this->syncMapping('import_legacy_variants', 'legacy_wp_post_id', $legacyVar->ID, 'product_variant_id', $variant->id);
                $this->stats['variants_created']++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Failed variant {$legacyVar->ID}: {$e->getMessage()}");
                $this->stats['errors']++;
            }
        }
    }

    /**
     * Create a default variant for a simple (non-variable) product.
     *
     * @param  Connection  $legacy
     * @param  array<string, mixed>  $meta
     */
    private function createSimpleVariant($legacy, int $legacyProductId, int $productId, array $meta): void
    {
        $baseSku = $meta['_sku'] ?? "product-{$legacyProductId}";
        $price = (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0);
        $regularPrice = (float) ($meta['_regular_price'] ?? $meta['_price'] ?? 0);
        $preorderAttributes = $this->parsePreorderAttributes($meta['_pre_order_date'] ?? null);

        $variant = new ProductVariant;
        $variant->fill([
            'product_id' => $productId,
            'name' => 'Default',
            'sku' => $this->uniqueVariantSku($baseSku, null, $legacyProductId),
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

        // Map simple product's WC post ID to the variant (so price sync can find it)
        $this->syncMapping('import_legacy_variants', 'legacy_wp_post_id', $legacyProductId, 'product_variant_id', $variant->id);
        $this->stats['simple_variants_created']++;
    }

    /**
     * Assign tags to a newly created product using the legacy mapping table.
     * Looks up WC term relationships (product_tag + product_cat taxonomies) and
     * resolves them to local Tag IDs via import_legacy_tags.
     *
     * @param  Connection  $legacy
     */
    private function syncTagsForProduct($legacy, int $legacyProductId, Product $product): void
    {
        $termIds = $legacy->table('wp_term_relationships')
            ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
            ->where('wp_term_relationships.object_id', $legacyProductId)
            ->whereIn('wp_term_taxonomy.taxonomy', ['product_tag', 'product_cat'])
            ->pluck('wp_term_taxonomy.term_id');

        if ($termIds->isEmpty()) {
            return;
        }

        $tagIds = DB::table('import_legacy_tags')
            ->whereIn('legacy_term_id', $termIds)
            ->pluck('tag_id')
            ->toArray();

        if ($tagIds !== []) {
            $product->tags()->syncWithoutDetaching($tagIds);
            $this->stats['products_tags_synced']++;
        }
    }

    /**
     * Import customers that placed orders in WC but don't exist in our system yet.
     */
    private function syncNewCustomers(): void
    {
        $this->info('Checking for new WC customers...');

        $legacy = DB::connection('legacy');

        $existingLegacyIds = DB::table('import_legacy_customers')
            ->pluck('legacy_wp_user_id')
            ->toArray();

        $newCustomers = $legacy->table('wp_users')
            ->where('ID', '>', 1)
            ->whereNotIn('ID', $existingLegacyIds)
            ->select('ID', 'user_email', 'user_login', 'user_registered')
            ->get();

        if ($newCustomers->isEmpty()) {
            $this->line('  No new customers found.');

            return;
        }

        $this->line("  Found {$newCustomers->count()} new customers.");

        foreach ($newCustomers as $legacyCust) {
            try {
                $existingUser = User::where('email', $legacyCust->user_email)->first();

                if ($existingUser !== null) {
                    $this->syncMapping('import_legacy_customers', 'legacy_wp_user_id', $legacyCust->ID, 'user_id', $existingUser->id);
                    $this->stats['customers_skipped']++;

                    continue;
                }

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

                $this->syncMapping('import_legacy_customers', 'legacy_wp_user_id', $legacyCust->ID, 'user_id', $user->id);
                $this->stats['customers_created']++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Customer {$legacyCust->ID} ({$legacyCust->user_email}): {$e->getMessage()}");
                $this->stats['errors']++;
            }
        }

        $this->info("✓ Created {$this->stats['customers_created']} customers.");
    }

    /**
     * Update status, payment_status, shipped_at, and refund_total for orders we already have.
     */
    private function syncExistingOrderStatuses(): void
    {
        $this->info('Syncing statuses for existing orders...');

        $legacy = DB::connection('legacy');

        $mappings = DB::table('import_legacy_orders')
            ->join('orders', 'import_legacy_orders.order_id', '=', 'orders.id')
            ->select(
                'import_legacy_orders.legacy_wc_order_id',
                'import_legacy_orders.order_id',
                'orders.status as current_status',
                'orders.payment_status as current_payment_status',
                'orders.refund_total as current_refund_total',
            )
            ->get();

        if ($mappings->isEmpty()) {
            $this->line('  No mapped orders found.');

            return;
        }

        $this->line("  Found {$mappings->count()} mapped orders to check.");

        $legacyIds = $mappings->pluck('legacy_wc_order_id')->toArray();
        $statusMap = $this->statusMap();
        $paymentStatusMap = $this->paymentStatusMap();

        // Batch-fetch current WC statuses
        foreach (array_chunk($legacyIds, 500) as $chunkIds) {
            $wcOrders = $legacy->table('wp_posts')
                ->whereIn('ID', $chunkIds)
                ->where('post_type', 'shop_order')
                ->select('ID', 'post_status')
                ->get()
                ->keyBy('ID');

            // Batch-fetch _date_completed meta for completed orders
            $completedMeta = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $chunkIds)
                ->where('meta_key', '_date_completed')
                ->pluck('meta_value', 'post_id')
                ->toArray();

            foreach ($mappings->whereIn('legacy_wc_order_id', $chunkIds) as $mapping) {
                $wcOrder = $wcOrders[$mapping->legacy_wc_order_id] ?? null;

                if ($wcOrder === null) {
                    continue;
                }

                $wcStatus = $statusMap[$wcOrder->post_status] ?? $mapping->current_status;
                $wcPaymentStatus = $paymentStatusMap[$wcOrder->post_status] ?? $mapping->current_payment_status;

                if (! isset($statusMap[$wcOrder->post_status])) {
                    $this->warn("  ⚠ Order {$mapping->legacy_wc_order_id}: unknown WC status '{$wcOrder->post_status}', keeping current");
                }

                // Resolve refund total from WC
                $wcRefundTotal = $this->resolveRefundTotal($legacy, $mapping->legacy_wc_order_id);

                $statusChanged = $wcStatus !== $mapping->current_status;
                $paymentStatusChanged = $wcPaymentStatus !== $mapping->current_payment_status;
                $refundChanged = bccomp((string) $wcRefundTotal, (string) ($mapping->current_refund_total ?? '0'), 2) !== 0;

                if (! $statusChanged && ! $paymentStatusChanged && ! $refundChanged) {
                    $this->stats['orders_status_unchanged']++;

                    continue;
                }

                $updates = [];

                if ($statusChanged || $paymentStatusChanged) {
                    $updates['status'] = $wcStatus;
                    $updates['payment_status'] = $wcPaymentStatus;

                    // Set shipped_at when order transitions to completed and has _date_completed
                    if ($wcStatus === 'completed' && $mapping->current_status !== 'completed') {
                        $dateCompleted = $completedMeta[$mapping->legacy_wc_order_id] ?? null;

                        if ($dateCompleted) {
                            $updates['shipped_at'] = Carbon::createFromTimestamp((int) $dateCompleted);
                        }
                    }
                }

                if ($refundChanged) {
                    $updates['refund_total'] = $wcRefundTotal;
                    $this->stats['orders_refund_updated']++;
                }

                $order = Order::find($mapping->order_id);

                if ($order === null) {
                    continue;
                }

                $order->forceFill($updates)->save();

                if ($statusChanged || $paymentStatusChanged) {
                    $this->stats['orders_status_updated']++;
                }
            }
        }

        $this->info("✓ Updated {$this->stats['orders_status_updated']} order statuses, {$this->stats['orders_status_unchanged']} unchanged, {$this->stats['orders_refund_updated']} refunds updated.");
    }

    /**
     * Import orders that exist in WC but not in our database.
     */
    private function syncNewOrders(): void
    {
        $this->info('Checking for new WC orders...');

        $legacy = DB::connection('legacy');

        $existingLegacyIds = DB::table('import_legacy_orders')
            ->pluck('legacy_wc_order_id')
            ->toArray();

        $newOrders = $legacy->table('wp_posts')
            ->where('post_type', 'shop_order')
            ->where('post_status', '!=', 'trash')
            ->whereNotIn('ID', $existingLegacyIds)
            ->orderBy('post_date')
            ->select('ID', 'post_author', 'post_date', 'post_status')
            ->get();

        if ($newOrders->isEmpty()) {
            $this->line('  No new orders found.');

            return;
        }

        $this->line("  Found {$newOrders->count()} new orders.");

        $statusMap = $this->statusMap();
        $paymentStatusMap = $this->paymentStatusMap();

        foreach ($newOrders as $legacyOrder) {
            // Double-check by order_number
            if (Order::where('order_number', "WC-{$legacyOrder->ID}")->exists()) {
                $this->syncMapping('import_legacy_orders', 'legacy_wc_order_id', $legacyOrder->ID, 'order_id',
                    Order::where('order_number', "WC-{$legacyOrder->ID}")->value('id'));
                $this->stats['orders_skipped']++;

                continue;
            }

            try {
                $meta = $legacy->table('wp_postmeta')
                    ->where('post_id', $legacyOrder->ID)
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();

                $status = $statusMap[$legacyOrder->post_status] ?? 'pending';
                $paymentStatus = $paymentStatusMap[$legacyOrder->post_status] ?? 'pending';

                if (! isset($statusMap[$legacyOrder->post_status])) {
                    $this->warn("  ⚠ Order {$legacyOrder->ID}: unknown WC status '{$legacyOrder->post_status}', defaulting to pending");
                }

                $orderTotal = (float) ($meta['_order_total'] ?? 0);
                $shippingTotal = (float) ($meta['_order_shipping'] ?? 0);
                $taxTotal = (float) ($meta['_order_tax'] ?? 0) + (float) ($meta['_order_shipping_tax'] ?? 0);
                $discountTotal = (float) ($meta['_cart_discount'] ?? 0);
                // WC _order_total is post-discount, so add discount back for pre-discount product subtotal
                $subtotal = max(0, $orderTotal - $shippingTotal - $taxTotal + $discountTotal);

                $couponItem = $legacy->table('wp_woocommerce_order_items')
                    ->where('order_id', $legacyOrder->ID)
                    ->where('order_item_type', 'coupon')
                    ->first();

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
                    'coupon_code' => $couponItem?->order_item_name,
                    'placed_at' => $legacyOrder->post_date,
                    'shipped_at' => $status === 'completed' && ! empty($meta['_date_completed'])
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

                // Import order line items
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

                    $wcProductId = $itemMeta['_product_id'] ?? null;
                    $wcVariantId = $itemMeta['_variation_id'] ?? null;
                    $newProductId = null;
                    $newVariantId = null;

                    if ($wcVariantId) {
                        $mappedVariantId = $this->resolveMappedModelId(
                            'import_legacy_variants', 'legacy_wp_post_id', (int) $wcVariantId, 'product_variant_id', ProductVariant::class,
                        );

                        if ($mappedVariantId !== null) {
                            $newVariantId = $mappedVariantId;
                            $newProductId = ProductVariant::find($mappedVariantId)?->product_id;
                        }
                    }

                    if ($newProductId === null && $wcProductId) {
                        $newProductId = $this->resolveMappedModelId(
                            'import_legacy_products', 'legacy_wp_post_id', (int) $wcProductId, 'product_id', Product::class,
                        );
                    }

                    $qty = max(1, (int) ($itemMeta['_qty'] ?? 1));
                    $lineSubtotal = (float) ($itemMeta['_line_subtotal'] ?? 0);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $newProductId,
                        'product_variant_id' => $newVariantId,
                        'product_name' => $item->order_item_name,
                        'variant_name' => $itemMeta['_variation_title'] ?? null,
                        'sku' => $itemMeta['_sku'] ?? 'LEGACY-ITEM',
                        'unit_price' => round($lineSubtotal / $qty, 2),
                        'quantity' => $qty,
                        'line_total' => (float) ($itemMeta['_line_total'] ?? 0),
                    ]);

                    $this->stats['order_items_created']++;
                }

                // Handle refunds for this order
                $refundTotal = $this->resolveRefundTotal($legacy, $legacyOrder->ID);

                if ($refundTotal > 0) {
                    $order->forceFill(['refund_total' => $refundTotal])->save();
                }

                $this->syncMapping('import_legacy_orders', 'legacy_wc_order_id', $legacyOrder->ID, 'order_id', $order->id);
                $this->stats['orders_created']++;
            } catch (\Exception $e) {
                $this->warn("  ⚠ Order {$legacyOrder->ID}: {$e->getMessage()}");
                $this->stats['errors']++;
            }
        }

        $this->info("✓ Created {$this->stats['orders_created']} orders ({$this->stats['order_items_created']} items).");
    }

    /**
     * Resolve the primary category for a WC product, using our category mappings.
     *
     * @param  Connection  $legacy
     */
    private function resolvePrimaryCategory($legacy, int $legacyProductId): ?int
    {
        $catTerm = $legacy->table('wp_term_relationships')
            ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
            ->where('wp_term_relationships.object_id', $legacyProductId)
            ->where('wp_term_taxonomy.taxonomy', 'product_cat')
            ->first();

        if ($catTerm === null) {
            return null;
        }

        $catMapping = DB::table('import_legacy_categories')
            ->where('legacy_term_id', $catTerm->term_id)
            ->first();

        return $catMapping?->category_id;
    }

    /**
     * Sum refund amounts for a WC order.
     *
     * @param  Connection  $legacy
     */
    private function resolveRefundTotal($legacy, int $legacyOrderId): float
    {
        $refunds = $legacy->table('wp_posts')
            ->where('post_parent', $legacyOrderId)
            ->where('post_type', 'shop_order_refund')
            ->pluck('ID');

        if ($refunds->isEmpty()) {
            return 0;
        }

        $total = $legacy->table('wp_postmeta')
            ->whereIn('post_id', $refunds->toArray())
            ->where('meta_key', '_refund_amount')
            ->sum('meta_value');

        return abs((float) $total);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveMappedModelId(string $table, string $legacyColumn, int $legacyId, string $mappedColumn, string $modelClass): ?int
    {
        $mapping = DB::table($table)->where($legacyColumn, $legacyId)->first();

        if ($mapping === null) {
            return null;
        }

        $mappedId = (int) $mapping->{$mappedColumn};

        if ($modelClass::query()->whereKey($mappedId)->exists()) {
            return $mappedId;
        }

        DB::table($table)->where($legacyColumn, $legacyId)->delete();

        return null;
    }

    private function syncMapping(string $table, string $legacyColumn, int $legacyId, string $mappedColumn, int $mappedId): void
    {
        DB::table($table)->updateOrInsert(
            [$legacyColumn => $legacyId],
            [$mappedColumn => $mappedId, 'synced_at' => now()]
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
     * @return array{is_preorder: bool, preorder_available_from: ?Carbon, expected_ship_at: null}
     */
    private function parsePreorderAttributes(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return ['is_preorder' => false, 'preorder_available_from' => null, 'expected_ship_at' => null];
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return ['is_preorder' => false, 'preorder_available_from' => null, 'expected_ship_at' => null];
        }

        if ($date->endOfDay()->isPast()) {
            return ['is_preorder' => false, 'preorder_available_from' => null, 'expected_ship_at' => null];
        }

        return ['is_preorder' => true, 'preorder_available_from' => $date->startOfDay(), 'expected_ship_at' => null];
    }

    /**
     * Resolve WC variant attribute meta into an option_values array.
     *
     * Keys are normalized to match ImportLegacyData conventions (lowercase, multilingual aliases resolved).
     * The frontend relies on lowercase keys like 'size' and 'color' for filtering.
     *
     * @param  Connection  $legacy
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function resolveLegacyVariantOptionValues($legacy, array $meta): array
    {
        $options = [];

        foreach ($meta as $key => $value) {
            if (! str_starts_with($key, 'attribute_')) {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $taxonomy = Str::replaceFirst('attribute_', '', $key);
            $attributeKey = $this->normalizeLegacyAttributeKey($taxonomy);
            $attributeValue = $this->resolveLegacyAttributeValueName($legacy, $taxonomy, $value, $attributeKey);

            if ($attributeValue === '') {
                continue;
            }

            $options[$attributeKey] = $attributeValue;
        }

        return $options;
    }

    /**
     * Normalize a WC attribute taxonomy to a consistent lowercase key.
     * Matches the same normalization used in ImportLegacyData.
     */
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

    /**
     * Resolve a WC attribute value slug to a human-readable name via wp_terms.
     * Falls back to formatted raw value if no term found.
     */
    private function resolveLegacyAttributeValueName($legacy, string $taxonomy, string $rawValue, string $attributeKey): string
    {
        $cacheKey = "{$taxonomy}|{$rawValue}";

        if (array_key_exists($cacheKey, $this->legacyAttributeTermNameCache)) {
            return $this->legacyAttributeTermNameCache[$cacheKey];
        }

        $termName = $legacy->table('wp_terms')
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
     * Detect variant type from option values keys first, then fall back to name detection.
     * Matches ImportLegacyData logic: multi-value = custom, single size/color key = that type.
     *
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
     * @var array<string, string>
     */
    private array $legacyAttributeTermNameCache = [];

    /**
     * @return array<string, string>
     */
    private function statusMap(): array
    {
        return [
            'wc-completed' => 'completed',
            'wc-processing' => 'processing',
            'wc-on-hold' => 'on-hold',
            'wc-pending' => 'pending',
            'wc-cancelled' => 'cancelled',
            'wc-refunded' => 'refunded',
            'wc-failed' => 'failed',
            'wc-pre-ordered' => 'pre-ordered',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentStatusMap(): array
    {
        return [
            'wc-completed' => 'completed',
            'wc-processing' => 'paid',
            'wc-on-hold' => 'pending',
            'wc-pending' => 'pending',
            'wc-cancelled' => 'cancelled',
            'wc-refunded' => 'refunded',
            'wc-failed' => 'failed',
            'wc-pre-ordered' => 'paid',
        ];
    }

    /**
     * Flush all dashboard cache keys so metrics reflect synced data immediately.
     */
    private function flushDashboardCache(): void
    {
        $flushed = 0;

        // The dashboard uses database cache with keys starting with 'dashboard:'
        $prefix = config('cache.prefix', '');
        $cacheTable = config('cache.stores.database.table', 'cache');

        $keys = DB::table($cacheTable)
            ->where('key', 'like', $prefix.'dashboard:%')
            ->pluck('key');

        foreach ($keys as $key) {
            // Strip the prefix to get the cache key used by Cache::forget()
            $cacheKey = $prefix !== '' ? Str::after($key, $prefix) : $key;
            Cache::forget($cacheKey);
            $flushed++;
        }

        if ($flushed > 0) {
            $this->line("  Flushed {$flushed} dashboard cache entries.");
        }
    }

    /**
     * @return list<string>|false
     */
    private function parseOnlyOption(): array|false
    {
        $only = $this->option('only');

        if ($only === null) {
            return [];
        }

        $valid = ['prices', 'products', 'customers', 'orders'];
        $parts = array_map('trim', explode(',', $only));

        foreach ($parts as $part) {
            if (! in_array($part, $valid, true)) {
                $this->error("Unknown entity: '{$part}'. Valid: ".implode(', ', $valid));

                return false;
            }
        }

        return $parts;
    }

    /**
     * @param  list<string>  $only
     */
    private function shouldSync(string $entity, array $only): bool
    {
        return $only === [] || in_array($entity, $only, true);
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Sync Summary ===');

        if ($this->stats['variants_updated'] > 0 || $this->stats['variants_unchanged'] > 0) {
            $this->line("  Prices/Stock: {$this->stats['variants_updated']} updated, {$this->stats['variants_unchanged']} unchanged");
        }

        if ($this->stats['products_created'] > 0 || $this->stats['products_skipped'] > 0) {
            $tagNote = $this->stats['products_tags_synced'] > 0 ? ", {$this->stats['products_tags_synced']} tagged" : '';
            $this->line("  Products: {$this->stats['products_created']} created, {$this->stats['products_skipped']} skipped{$tagNote}");
        }

        if ($this->stats['products_delisted'] > 0 || $this->stats['products_relisted'] > 0) {
            $this->line("  Product Statuses: {$this->stats['products_delisted']} delisted, {$this->stats['products_relisted']} relisted");
        }

        if ($this->stats['simple_variants_created'] > 0 || $this->stats['variants_created'] > 0) {
            $this->line("  Variants: {$this->stats['simple_variants_created']} simple + {$this->stats['variants_created']} variable");
        }

        if ($this->stats['customers_created'] > 0 || $this->stats['customers_skipped'] > 0) {
            $this->line("  Customers: {$this->stats['customers_created']} created, {$this->stats['customers_skipped']} existing mapped");
        }

        if ($this->stats['orders_status_updated'] > 0 || $this->stats['orders_status_unchanged'] > 0) {
            $refundNote = $this->stats['orders_refund_updated'] > 0 ? ", {$this->stats['orders_refund_updated']} refunds synced" : '';
            $this->line("  Order Statuses: {$this->stats['orders_status_updated']} updated, {$this->stats['orders_status_unchanged']} unchanged{$refundNote}");
        }

        if ($this->stats['orders_created'] > 0 || $this->stats['orders_skipped'] > 0) {
            $this->line("  New Orders: {$this->stats['orders_created']} created ({$this->stats['order_items_created']} items), {$this->stats['orders_skipped']} skipped");
        }

        if ($this->stats['errors'] > 0) {
            $this->warn("  Errors: {$this->stats['errors']}");
        }
    }
}
