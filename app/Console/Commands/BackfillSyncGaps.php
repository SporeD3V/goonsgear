<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSyncGaps extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:backfill-sync-gaps
        {--dry-run : Preview changes without writing}
        {--only= : Run specific operations (refunds,tax,coupons,items,phones,dimensions,preorders)}';

    /**
     * @var string
     */
    protected $description = 'Backfill data gaps identified in the WC↔GG database audit';

    /**
     * @var array<string, int>
     */
    private array $stats = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->parseOnlyOption();

        if ($only === false) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE — Changes will be previewed but not saved');
            $this->newLine();
        }

        $legacy = DB::connection('legacy');

        if ($this->shouldRun('refunds', $only)) {
            $this->backfillRefunds($legacy, $dryRun);
        }

        if ($this->shouldRun('tax', $only)) {
            $this->backfillShippingTax($legacy, $dryRun);
        }

        if ($this->shouldRun('coupons', $only)) {
            $this->backfillCouponUsages($legacy, $dryRun);
        }

        if ($this->shouldRun('items', $only)) {
            $this->backfillMissingOrderItems($legacy, $dryRun);
        }

        if ($this->shouldRun('phones', $only)) {
            $this->backfillPhoneNumbers($legacy, $dryRun);
        }

        if ($this->shouldRun('dimensions', $only)) {
            $this->backfillProductDimensions($legacy, $dryRun);
        }

        if ($this->shouldRun('preorders', $only)) {
            $this->backfillPreorderFlags($legacy, $dryRun);
        }

        $this->newLine();
        $this->info('=== Summary ===');
        foreach ($this->stats as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

        return self::SUCCESS;
    }

    /**
     * Backfill refund_total from WC shop_order_refund posts.
     */
    private function backfillRefunds(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Refunds ---');

        $refunds = $legacy->table('wp_posts')
            ->where('post_type', 'shop_order_refund')
            ->where('post_status', '!=', 'trash')
            ->join('wp_postmeta', function ($join) {
                $join->on('wp_posts.ID', '=', 'wp_postmeta.post_id')
                    ->where('wp_postmeta.meta_key', '=', '_refund_amount');
            })
            ->groupBy('wp_posts.post_parent')
            ->selectRaw('wp_posts.post_parent as wc_order_id, SUM(CAST(wp_postmeta.meta_value AS DECIMAL(10,2))) as refund_sum')
            ->get();

        $this->line("  Found {$refunds->count()} WC orders with refunds.");

        $updated = 0;
        $skipped = 0;

        foreach ($refunds as $refund) {
            $mapping = DB::table('import_legacy_orders')
                ->where('legacy_wc_order_id', (int) $refund->wc_order_id)
                ->first();

            if ($mapping === null) {
                $skipped++;

                continue;
            }

            $refundAmount = round((float) $refund->refund_sum, 2);

            if (! $dryRun) {
                DB::table('orders')
                    ->where('id', $mapping->order_id)
                    ->update(['refund_total' => $refundAmount]);
            }

            $updated++;
        }

        $this->stats['refunds_updated'] = $updated;
        $this->stats['refunds_skipped'] = $skipped;
        $this->info("  {$updated} orders updated, {$skipped} skipped (no mapping).");
    }

    /**
     * Backfill tax_total to include _order_shipping_tax (was missing).
     * Also recalculates subtotal since it depends on tax_total.
     */
    private function backfillShippingTax(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Shipping Tax ---');

        $mappings = DB::table('import_legacy_orders')
            ->select('legacy_wc_order_id', 'order_id')
            ->get();

        $this->line("  Processing {$mappings->count()} mapped orders...");

        $updated = 0;
        $unchanged = 0;
        $chunk = 0;

        foreach ($mappings->chunk(500) as $batch) {
            $chunk++;
            $wcIds = $batch->pluck('legacy_wc_order_id')->toArray();

            // Get shipping tax for this batch
            $shippingTaxes = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->where('meta_key', '_order_shipping_tax')
                ->pluck('meta_value', 'post_id');

            // Also get _order_tax and _cart_discount for recalculating subtotal
            $orderTaxes = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->where('meta_key', '_order_tax')
                ->pluck('meta_value', 'post_id');

            $cartDiscounts = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->where('meta_key', '_cart_discount')
                ->pluck('meta_value', 'post_id');

            foreach ($batch as $mapping) {
                $wcId = $mapping->legacy_wc_order_id;
                $shippingTax = (float) ($shippingTaxes[$wcId] ?? 0);

                if ($shippingTax == 0) {
                    $unchanged++;

                    continue;
                }

                // Recalculate: new tax_total = _order_tax + _order_shipping_tax
                $orderTax = (float) ($orderTaxes[$wcId] ?? 0);
                $discount = (float) ($cartDiscounts[$wcId] ?? 0);
                $newTaxTotal = round($orderTax + $shippingTax, 2);

                // Get current order to recalculate subtotal
                $order = DB::table('orders')->where('id', $mapping->order_id)->first(['total', 'shipping_total']);

                if ($order === null) {
                    continue;
                }

                $newSubtotal = max(0, round($order->total - (float) $order->shipping_total - $newTaxTotal + $discount, 2));

                if (! $dryRun) {
                    DB::table('orders')
                        ->where('id', $mapping->order_id)
                        ->update([
                            'tax_total' => $newTaxTotal,
                            'subtotal' => $newSubtotal,
                        ]);
                }

                $updated++;
            }
        }

        $this->stats['tax_updated'] = $updated;
        $this->stats['tax_unchanged'] = $unchanged;
        $this->info("  {$updated} orders updated with shipping tax, {$unchanged} had no shipping tax.");
    }

    /**
     * Populate the order_coupon_usages table from WC data.
     */
    private function backfillCouponUsages(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Coupon Usages ---');

        // Get all WC coupon order items
        $couponItems = $legacy->table('wp_woocommerce_order_items')
            ->where('order_item_type', 'coupon')
            ->select('order_item_id', 'order_id', 'order_item_name')
            ->get();

        $this->line("  Found {$couponItems->count()} WC coupon usages.");

        $created = 0;
        $skipped = 0;

        foreach ($couponItems as $couponItem) {
            $mapping = DB::table('import_legacy_orders')
                ->where('legacy_wc_order_id', (int) $couponItem->order_id)
                ->first();

            if ($mapping === null) {
                $skipped++;

                continue;
            }

            // Check if already exists
            $exists = DB::table('order_coupon_usages')
                ->where('order_id', $mapping->order_id)
                ->where('coupon_code', $couponItem->order_item_name)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            // Get discount amount from item meta
            $discountAmount = (float) $legacy->table('wp_woocommerce_order_itemmeta')
                ->where('order_item_id', $couponItem->order_item_id)
                ->where('meta_key', 'discount_amount')
                ->value('meta_value');

            // If no discount_amount meta, try from coupon lookup
            if ($discountAmount == 0) {
                $discountAmount = (float) $legacy->table('wp_wc_order_coupon_lookup')
                    ->where('order_id', $couponItem->order_id)
                    ->where('coupon_id', function ($query) use ($couponItem) {
                        $query->select('ID')
                            ->from('wp_posts')
                            ->where('post_title', $couponItem->order_item_name)
                            ->where('post_type', 'shop_coupon')
                            ->limit(1);
                    })
                    ->value('discount_amount');
            }

            // Resolve coupon ID from our coupons table (if it exists)
            $localCoupon = DB::table('coupons')
                ->whereRaw('LOWER(code) = ?', [strtolower($couponItem->order_item_name)])
                ->first();

            if (! $dryRun) {
                DB::table('order_coupon_usages')->insert([
                    'order_id' => $mapping->order_id,
                    'coupon_id' => $localCoupon?->id,
                    'coupon_code' => $couponItem->order_item_name,
                    'discount_total' => round($discountAmount, 2),
                    'applied_position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created++;
        }

        $this->stats['coupon_usages_created'] = $created;
        $this->stats['coupon_usages_skipped'] = $skipped;
        $this->info("  {$created} coupon usages created, {$skipped} skipped.");
    }

    /**
     * Find and create missing order items (253 delta from audit).
     */
    private function backfillMissingOrderItems(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Missing Order Items ---');

        // Get all WC order IDs that are mapped
        $mappings = DB::table('import_legacy_orders')
            ->select('legacy_wc_order_id', 'order_id')
            ->get()
            ->keyBy('legacy_wc_order_id');

        $wcOrderIds = $mappings->keys()->toArray();

        // Get all WC line items for mapped orders
        $wcItems = $legacy->table('wp_woocommerce_order_items')
            ->whereIn('order_id', $wcOrderIds)
            ->where('order_item_type', 'line_item')
            ->select('order_item_id', 'order_id', 'order_item_name')
            ->get();

        // Get existing GG order items (by order_id) to detect gaps
        $existingCounts = DB::table('order_items')
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id');

        // Group WC items by order
        $wcItemsByOrder = $wcItems->groupBy('order_id');

        $created = 0;
        $ordersFixed = 0;

        foreach ($wcItemsByOrder as $wcOrderId => $items) {
            $mapping = $mappings[$wcOrderId] ?? null;

            if ($mapping === null) {
                continue;
            }

            $ggOrderId = $mapping->order_id;
            $existingCount = $existingCounts[$ggOrderId] ?? 0;

            // If GG already has the same number of items, skip
            if ($existingCount >= $items->count()) {
                continue;
            }

            // Order has missing items — delete and re-import all items for this order
            if (! $dryRun) {
                DB::table('order_items')->where('order_id', $ggOrderId)->delete();
            }

            $ordersFixed++;

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
                    $mapped = DB::table('import_legacy_variants')
                        ->where('legacy_wp_post_id', (int) $variantId)
                        ->first();

                    if ($mapped) {
                        $newVariantId = $mapped->product_variant_id;
                        $variant = ProductVariant::find($mapped->product_variant_id);
                        $newProductId = $variant?->product_id;
                    }
                }

                if ($newProductId === null && $productId) {
                    $mapped = DB::table('import_legacy_products')
                        ->where('legacy_wp_post_id', (int) $productId)
                        ->first();

                    if ($mapped) {
                        $newProductId = $mapped->product_id;
                    }
                }

                $qty = max(1, (int) ($itemMeta['_qty'] ?? 1));
                $lineSubtotal = (float) ($itemMeta['_line_subtotal'] ?? 0);

                if (! $dryRun) {
                    OrderItem::create([
                        'order_id' => $ggOrderId,
                        'product_id' => $newProductId,
                        'product_variant_id' => $newVariantId,
                        'product_name' => $item->order_item_name,
                        'variant_name' => $itemMeta['_variation_title'] ?? null,
                        'sku' => $itemMeta['_sku'] ?? 'LEGACY-ITEM',
                        'unit_price' => round($lineSubtotal / $qty, 2),
                        'quantity' => $qty,
                        'line_total' => (float) ($itemMeta['_line_total'] ?? 0),
                    ]);
                }

                $created++;
            }
        }

        $this->stats['items_created'] = $created;
        $this->stats['orders_fixed'] = $ordersFixed;
        $this->info("  {$created} items (re)created across {$ordersFixed} orders.");
    }

    /**
     * Backfill phone numbers from WC order meta onto orders.
     */
    private function backfillPhoneNumbers(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Phone Numbers ---');

        $mappings = DB::table('import_legacy_orders')
            ->select('legacy_wc_order_id', 'order_id')
            ->get();

        $updated = 0;
        $unchanged = 0;

        foreach ($mappings->chunk(500) as $batch) {
            $wcIds = $batch->pluck('legacy_wc_order_id')->toArray();

            $phones = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->where('meta_key', '_billing_phone')
                ->where('meta_value', '!=', '')
                ->pluck('meta_value', 'post_id');

            foreach ($batch as $mapping) {
                $phone = $phones[$mapping->legacy_wc_order_id] ?? null;

                if ($phone === null) {
                    $unchanged++;

                    continue;
                }

                // Only update if current phone is null
                $current = DB::table('orders')
                    ->where('id', $mapping->order_id)
                    ->value('phone');

                if ($current !== null && $current !== '') {
                    $unchanged++;

                    continue;
                }

                if (! $dryRun) {
                    DB::table('orders')
                        ->where('id', $mapping->order_id)
                        ->update(['phone' => $phone]);
                }

                $updated++;
            }
        }

        $this->stats['phones_updated'] = $updated;
        $this->stats['phones_unchanged'] = $unchanged;
        $this->info("  {$updated} orders updated with phone, {$unchanged} unchanged.");
    }

    /**
     * Backfill weight & dimensions from WC product meta.
     */
    private function backfillProductDimensions(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Product Dimensions ---');

        $mappings = DB::table('import_legacy_products')
            ->select('legacy_wp_post_id', 'product_id')
            ->get();

        $updated = 0;
        $unchanged = 0;

        foreach ($mappings->chunk(200) as $batch) {
            $wcIds = $batch->pluck('legacy_wp_post_id')->toArray();

            // Get weight and dimensions
            $allMeta = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->whereIn('meta_key', ['_weight', '_length', '_width', '_height'])
                ->where('meta_value', '!=', '')
                ->get()
                ->groupBy('post_id');

            foreach ($batch as $mapping) {
                $metas = $allMeta[$mapping->legacy_wp_post_id] ?? collect();

                if ($metas->isEmpty()) {
                    $unchanged++;

                    continue;
                }

                $values = $metas->pluck('meta_value', 'meta_key');
                $updates = [];

                foreach (['_weight' => 'weight', '_length' => 'length', '_width' => 'width', '_height' => 'height'] as $wcKey => $ggKey) {
                    $val = $values[$wcKey] ?? null;

                    if ($val !== null && (float) $val > 0) {
                        $updates[$ggKey] = (float) $val;
                    }
                }

                if (empty($updates)) {
                    $unchanged++;

                    continue;
                }

                if (! $dryRun) {
                    DB::table('products')
                        ->where('id', $mapping->product_id)
                        ->update($updates);
                }

                $updated++;
            }
        }

        $this->stats['dimensions_updated'] = $updated;
        $this->stats['dimensions_unchanged'] = $unchanged;
        $this->info("  {$updated} products updated with dimensions, {$unchanged} unchanged.");
    }

    /**
     * Backfill is_preorder flag from WC _is_pre_order variation meta.
     */
    private function backfillPreorderFlags(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Pre-Order Flags ---');

        // Get all WC variations with _is_pre_order = yes
        $preorderWcIds = $legacy->table('wp_postmeta')
            ->where('meta_key', '_is_pre_order')
            ->where('meta_value', 'yes')
            ->pluck('post_id');

        $this->line("  Found {$preorderWcIds->count()} WC variations with pre-order flag.");

        $variantsUpdated = 0;
        $productsUpdated = 0;

        // Map to local variants
        $variantMappings = DB::table('import_legacy_variants')
            ->whereIn('legacy_wp_post_id', $preorderWcIds)
            ->select('legacy_wp_post_id', 'product_variant_id')
            ->get();

        $productIds = collect();

        foreach ($variantMappings as $mapping) {
            $variant = ProductVariant::find($mapping->product_variant_id);

            if ($variant === null || $variant->is_preorder) {
                continue;
            }

            if (! $dryRun) {
                $variant->update(['is_preorder' => true]);
            }

            $variantsUpdated++;
            $productIds->push($variant->product_id);
        }

        // Flag products that have at least one pre-order variant
        $productIds = $productIds->unique();

        foreach ($productIds as $productId) {
            $product = Product::find($productId);

            if ($product === null || $product->is_preorder) {
                continue;
            }

            if (! $dryRun) {
                $product->update(['is_preorder' => true]);
            }

            $productsUpdated++;
        }

        $this->stats['preorder_variants_updated'] = $variantsUpdated;
        $this->stats['preorder_products_updated'] = $productsUpdated;
        $this->info("  {$variantsUpdated} variants and {$productsUpdated} products flagged as pre-order.");
    }

    /**
     * @return list<string>|false|null
     */
    private function parseOnlyOption(): array|false|null
    {
        $raw = $this->option('only');

        if ($raw === null) {
            return null;
        }

        $valid = ['refunds', 'tax', 'coupons', 'items', 'phones', 'dimensions', 'preorders'];
        $requested = array_map('trim', explode(',', $raw));

        foreach ($requested as $item) {
            if (! in_array($item, $valid, true)) {
                $this->error("Unknown operation: {$item}. Valid: ".implode(', ', $valid));

                return false;
            }
        }

        return $requested;
    }

    /**
     * @param  list<string>|null  $only
     */
    private function shouldRun(string $operation, ?array $only): bool
    {
        return $only === null || in_array($operation, $only, true);
    }
}
