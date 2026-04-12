<?php

namespace App\Console\Commands;

use App\Models\AdminNote;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillSyncGaps extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:backfill-sync-gaps
        {--dry-run : Preview changes without writing}
        {--only= : Run specific operations (refunds,tax,coupons,items,phones,dimensions,preorders,wc-coupons,tracking,order-notes)}';

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

        if ($this->shouldRun('wc-coupons', $only)) {
            $this->importWcCoupons($legacy, $dryRun);
        }

        if ($this->shouldRun('tracking', $only)) {
            $this->backfillShipmentTracking($legacy, $dryRun);
        }

        if ($this->shouldRun('order-notes', $only)) {
            $this->importHumanOrderNotes($legacy, $dryRun);
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
     * Import WC shop_coupon posts into the GG coupons table.
     */
    private function importWcCoupons(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Importing WC Coupons ---');

        $wcCoupons = $legacy->table('wp_posts')
            ->where('post_type', 'shop_coupon')
            ->whereIn('post_status', ['publish', 'draft'])
            ->select('ID', 'post_title', 'post_excerpt', 'post_status')
            ->get();

        $this->line("  Found {$wcCoupons->count()} WC coupons.");

        $created = 0;
        $skipped = 0;

        foreach ($wcCoupons as $wcCoupon) {
            $code = strtoupper(trim($wcCoupon->post_title));

            if (Coupon::where('code', $code)->exists()) {
                $skipped++;

                continue;
            }

            $meta = $legacy->table('wp_postmeta')
                ->where('post_id', $wcCoupon->ID)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            $wcType = $meta['discount_type'] ?? 'percent';
            $type = match ($wcType) {
                'percent' => Coupon::TYPE_PERCENT,
                'fixed_cart', 'fixed_product' => Coupon::TYPE_FIXED,
                default => Coupon::TYPE_PERCENT,
            };

            $expiresTimestamp = $meta['date_expires'] ?? null;
            $endsAt = $expiresTimestamp && $expiresTimestamp > 0
                ? Carbon::createFromTimestamp((int) $expiresTimestamp)
                : null;

            if (! $dryRun) {
                Coupon::create([
                    'code' => $code,
                    'description' => $wcCoupon->post_excerpt ?: null,
                    'type' => $type,
                    'value' => (float) ($meta['coupon_amount'] ?? 0),
                    'minimum_subtotal' => ($meta['minimum_amount'] ?? '') !== '' ? (float) $meta['minimum_amount'] : null,
                    'usage_limit' => ($meta['usage_limit'] ?? '') !== '' && (int) $meta['usage_limit'] > 0 ? (int) $meta['usage_limit'] : null,
                    'used_count' => (int) ($meta['usage_count'] ?? 0),
                    'is_active' => $wcCoupon->post_status === 'publish',
                    'ends_at' => $endsAt,
                ]);
            }

            $created++;
        }

        $this->stats['coupons_imported'] = $created;
        $this->stats['coupons_skipped_existing'] = $skipped;
        $this->info("  {$created} coupons imported, {$skipped} skipped (already exist).");
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

        $valid = ['refunds', 'tax', 'coupons', 'items', 'phones', 'dimensions', 'preorders', 'wc-coupons', 'tracking', 'order-notes'];
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
     * Backfill shipping_carrier and tracking_number from WC serialized tracking data.
     */
    private function backfillShipmentTracking(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Backfilling Shipment Tracking ---');

        $mappings = DB::table('import_legacy_orders')
            ->select('legacy_wc_order_id', 'order_id')
            ->get();

        $this->line("  Processing {$mappings->count()} mapped orders...");

        $updated = 0;
        $skippedNoData = 0;
        $skippedAlreadySet = 0;
        $unparseable = 0;

        foreach ($mappings->chunk(500) as $batch) {
            $wcIds = $batch->pluck('legacy_wc_order_id')->toArray();

            $trackingMeta = $legacy->table('wp_postmeta')
                ->whereIn('post_id', $wcIds)
                ->where('meta_key', '_wc_shipment_tracking_items')
                ->where('meta_value', '!=', '')
                ->where('meta_value', '!=', 'a:0:{}')
                ->pluck('meta_value', 'post_id');

            foreach ($batch as $mapping) {
                $serialized = $trackingMeta[$mapping->legacy_wc_order_id] ?? null;

                if ($serialized === null) {
                    $skippedNoData++;

                    continue;
                }

                // Check if order already has tracking
                $existing = DB::table('orders')
                    ->where('id', $mapping->order_id)
                    ->first(['tracking_number', 'shipping_carrier']);

                if ($existing !== null && $existing->tracking_number !== null && $existing->tracking_number !== '') {
                    $skippedAlreadySet++;

                    continue;
                }

                $parsed = $this->parseWcTrackingData($serialized);

                if ($parsed === null) {
                    $unparseable++;

                    continue;
                }

                if (! $dryRun) {
                    DB::table('orders')
                        ->where('id', $mapping->order_id)
                        ->update([
                            'shipping_carrier' => $parsed['carrier'],
                            'tracking_number' => $parsed['tracking_number'],
                        ]);
                }

                $updated++;
            }
        }

        $this->stats['tracking_updated'] = $updated;
        $this->stats['tracking_no_data'] = $skippedNoData;
        $this->stats['tracking_already_set'] = $skippedAlreadySet;
        $this->stats['tracking_unparseable'] = $unparseable;
        $this->info("  {$updated} orders updated, {$skippedNoData} had no tracking, {$skippedAlreadySet} already set, {$unparseable} unparseable.");
    }

    /**
     * Parse WC serialized tracking data and extract carrier + tracking number.
     *
     * @return array{carrier: string, tracking_number: string}|null
     */
    private function parseWcTrackingData(string $serialized): ?array
    {
        // Try PHP unserialize first
        $data = @unserialize($serialized);

        if (is_array($data) && count($data) > 0) {
            // Use the first (most recent) tracking entry
            $entry = $data[0] ?? null;

            if (! is_array($entry)) {
                return null;
            }

            $trackingNumber = trim((string) ($entry['tracking_number'] ?? ''));
            // Remove spaces from tracking numbers (some have "L E 4 3 7..." format)
            $trackingNumber = str_replace(' ', '', $trackingNumber);

            if ($trackingNumber === '') {
                return null;
            }

            $provider = strtolower(trim((string) ($entry['tracking_provider'] ?? '')));
            $carrier = $this->normalizeCarrier($provider);

            return ['carrier' => $carrier, 'tracking_number' => $trackingNumber];
        }

        // Fallback: regex extraction for corrupted serialized data
        if (preg_match('/tracking_number";s:\d+:"([^"]+)"/', $serialized, $tnMatch)
            && preg_match('/tracking_provider";s:\d+:"([^"]+)"/', $serialized, $cpMatch)) {
            $trackingNumber = str_replace(' ', '', trim($tnMatch[1]));

            if ($trackingNumber === '') {
                return null;
            }

            return [
                'carrier' => $this->normalizeCarrier(strtolower(trim($cpMatch[1]))),
                'tracking_number' => $trackingNumber,
            ];
        }

        return null;
    }

    /**
     * Normalize WC carrier slug to GG carrier name.
     */
    private function normalizeCarrier(string $wcProvider): string
    {
        return match (true) {
            str_contains($wcProvider, 'dhl') => 'dhl',
            str_contains($wcProvider, 'deutsche-post') => 'dhl',
            str_contains($wcProvider, 'hermes') => 'hermes',
            str_contains($wcProvider, 'usps') => 'usps',
            str_contains($wcProvider, 'ups') => 'ups',
            str_contains($wcProvider, 'fedex') => 'fedex',
            str_contains($wcProvider, 'dpd') => 'dpd',
            $wcProvider !== '' => $wcProvider,
            default => 'dhl',
        };
    }

    /**
     * Import human-written order notes from WC into admin_notes.
     */
    private function importHumanOrderNotes(mixed $legacy, bool $dryRun): void
    {
        $this->info('--- Importing Human Order Notes ---');

        // Find admin user for note ownership
        $adminUser = User::where('is_admin', true)->first();

        if ($adminUser === null) {
            $this->error('  No admin user found. Cannot assign order notes.');

            return;
        }

        // Get human-written order notes (not system-generated WooCommerce notes)
        $notes = $legacy->table('wp_comments')
            ->where('comment_type', 'order_note')
            ->where('comment_author', '!=', 'WooCommerce')
            ->where('comment_content', '!=', '')
            ->select('comment_post_ID', 'comment_content', 'comment_date', 'comment_author')
            ->orderBy('comment_date')
            ->get();

        $this->line("  Found {$notes->count()} human order notes.");

        $created = 0;
        $skippedNoMapping = 0;
        $skippedDuplicate = 0;

        // Build order mapping lookup
        $mappings = DB::table('import_legacy_orders')
            ->pluck('order_id', 'legacy_wc_order_id');

        foreach ($notes as $note) {
            $orderId = $mappings[(int) $note->comment_post_ID] ?? null;

            if ($orderId === null) {
                $skippedNoMapping++;

                continue;
            }

            // Get the order number for context_label
            $orderNumber = DB::table('orders')
                ->where('id', $orderId)
                ->value('order_number');

            $contextKey = "order:{$orderId}";

            // Check for duplicate (same context + same content)
            $exists = AdminNote::where('context', $contextKey)
                ->where('content', $note->comment_content)
                ->exists();

            if ($exists) {
                $skippedDuplicate++;

                continue;
            }

            if (! $dryRun) {
                AdminNote::create([
                    'user_id' => $adminUser->id,
                    'content' => $note->comment_content,
                    'is_pinned' => false,
                    'color' => 'warm',
                    'context' => $contextKey,
                    'context_label' => $orderNumber ? "Order #{$orderNumber}" : "Order (ID:{$orderId})",
                ]);
            }

            $created++;
        }

        $this->stats['notes_created'] = $created;
        $this->stats['notes_skipped_no_mapping'] = $skippedNoMapping;
        $this->stats['notes_skipped_duplicate'] = $skippedDuplicate;
        $this->info("  {$created} notes imported, {$skippedNoMapping} skipped (no mapping), {$skippedDuplicate} skipped (duplicate).");
    }

    /**
     * @param  list<string>|null  $only
     */
    private function shouldRun(string $operation, ?array $only): bool
    {
        return $only === null || in_array($operation, $only, true);
    }
}
