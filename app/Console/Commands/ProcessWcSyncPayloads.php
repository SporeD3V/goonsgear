<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WcSyncPayload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessWcSyncPayloads extends Command
{
    protected $signature = 'sync:process
        {--limit=100 : Maximum number of payloads to process}
        {--event= : Only process payloads matching this event prefix}
        {--replay : Re-process already-processed payloads}
        {--dry-run : Show what would be processed without writing}';

    protected $description = 'Process incoming WooCommerce webhook payloads into application models.';

    private int $processed = 0;

    private int $failed = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $query = WcSyncPayload::query()
            ->orderBy('received_at')
            ->limit((int) $this->option('limit'));

        if (! $this->option('replay')) {
            $query->whereNull('processed_at');
        }

        if ($eventFilter = $this->option('event')) {
            $query->where('event', 'like', $eventFilter.'%');
        }

        $payloads = $query->get();

        if ($payloads->isEmpty()) {
            $this->info('No payloads to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$payloads->count()} payload(s)…");

        foreach ($payloads as $payload) {
            $this->processPayload($payload);
        }

        $this->newLine();
        $this->info("Done. Processed: {$this->processed}, Failed: {$this->failed}, Skipped: {$this->skipped}");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processPayload(WcSyncPayload $payload): void
    {
        $event = $payload->event;
        /** @var array<string, mixed> $decoded */
        $decoded = $payload->payload;
        $data = $decoded['data'] ?? [];

        if ($this->option('dry-run')) {
            $this->line("  [DRY-RUN] {$event} (ID: {$payload->id})");
            $this->skipped++;

            return;
        }

        try {
            $handled = match (true) {
                str_starts_with($event, 'order.') => $this->processOrder($event, $data),
                str_starts_with($event, 'product.') => $this->processProduct($event, $data),
                str_starts_with($event, 'coupon.') => $this->processCoupon($event, $data),
                str_starts_with($event, 'customer.') => $this->processCustomer($event, $data),
                str_starts_with($event, 'note.') => $this->processNote($event, $data),
                default => false,
            };

            if ($handled) {
                $payload->markProcessed();
                $this->processed++;
                $this->line("  <info>✓</info> {$event} (ID: {$payload->id})");
            } else {
                $this->skipped++;
                $this->line("  <comment>–</comment> {$event} (ID: {$payload->id}) — unhandled event");
            }
        } catch (\Throwable $e) {
            $payload->markFailed($e->getMessage());
            $this->failed++;
            $this->error("  ✗ {$event} (ID: {$payload->id}): {$e->getMessage()}");
            Log::error("[WC Sync] Failed to process payload #{$payload->id}: {$e->getMessage()}", [
                'event' => $event,
                'exception' => $e,
            ]);
        }
    }

    /* ------------------------------------------------------------------
     *  Orders
     * ----------------------------------------------------------------*/

    private function processOrder(string $event, array $data): bool
    {
        $wcOrderId = $data['wc_order_id'] ?? null;

        if (! $wcOrderId) {
            return false;
        }

        return match ($event) {
            'order.created' => $this->upsertOrder($wcOrderId, $data),
            'order.status_changed' => $this->updateOrderStatus($wcOrderId, $data),
            'order.refunded' => $this->updateOrderRefund($wcOrderId, $data),
            default => false,
        };
    }

    private function upsertOrder(int $wcOrderId, array $data): bool
    {
        $existingOrderId = DB::table('import_legacy_orders')
            ->where('legacy_wc_order_id', $wcOrderId)
            ->value('order_id');

        $shipping = $data['shipping'] ?? [];
        $status = $this->mapWcOrderStatus($data['status'] ?? 'pending');

        $attributes = [
            'order_number' => $data['order_number'] ?? "WC-{$wcOrderId}",
            'status' => $status,
            'payment_status' => $status === 'pre-ordered' ? 'paid' : $this->mapPaymentStatus($data['status'] ?? ''),
            'payment_method' => $data['payment_method'] ?? 'manual',
            'email' => $data['email'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? null,
            'country' => $shipping['country'] ?? '',
            'state' => $shipping['state'] ?? '',
            'city' => $shipping['city'] ?? '',
            'postal_code' => $shipping['postal_code'] ?? '',
            'street_name' => $shipping['address_1'] ?? '',
            'street_number' => $shipping['address_2'] ?? '',
            'currency' => $data['currency'] ?? 'EUR',
            'subtotal' => $data['subtotal'] ?? 0,
            'total' => $data['total'] ?? 0,
            'discount_total' => $data['discount_total'] ?? 0,
            'coupon_code' => ! empty($data['coupon_codes']) ? $data['coupon_codes'][0] : null,
            'placed_at' => isset($data['placed_at']) ? Carbon::parse($data['placed_at']) : now(),
        ];

        // Fields not in $fillable — must use forceCreate or manual update.
        $forceAttributes = [
            'shipping_total' => $data['shipping_total'] ?? 0,
            'tax_total' => $data['tax_total'] ?? 0,
            'refund_total' => $data['refund_total'] ?? 0,
        ];

        if ($existingOrderId) {
            $order = Order::find($existingOrderId);

            if (! $order) {
                return false;
            }

            $order->forceFill($attributes + $forceAttributes)->save();
        } else {
            $order = Order::forceCreate($attributes + $forceAttributes);

            DB::table('import_legacy_orders')->insert([
                'legacy_wc_order_id' => $wcOrderId,
                'order_id' => $order->id,
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sync line items.
        if (! empty($data['items'])) {
            $this->syncOrderItems($order, $data['items']);
        }

        // Update tracking if present.
        if (! empty($data['tracking'])) {
            $order->update([
                'shipping_carrier' => $data['tracking']['carrier'] ?? null,
                'tracking_number' => $data['tracking']['tracking_number'] ?? null,
                'shipped_at' => isset($data['tracking']['shipped_at']) ? Carbon::parse($data['tracking']['shipped_at']) : null,
            ]);
        }

        return true;
    }

    private function updateOrderStatus(int $wcOrderId, array $data): bool
    {
        $orderId = DB::table('import_legacy_orders')
            ->where('legacy_wc_order_id', $wcOrderId)
            ->value('order_id');

        if (! $orderId) {
            // Order not yet known — upsert it from the full payload.
            return $this->upsertOrder($wcOrderId, $data);
        }

        $order = Order::find($orderId);

        if (! $order) {
            return false;
        }

        $newStatus = $this->mapWcOrderStatus($data['new_status'] ?? $data['status'] ?? '');
        $newPaymentStatus = $newStatus === 'pre-ordered'
            ? 'paid'
            : $this->mapPaymentStatus($data['new_status'] ?? $data['status'] ?? '');

        $updates = [
            'status' => $newStatus,
            'payment_status' => $newPaymentStatus,
        ];

        // The status_changed payload carries full amounts — update financials
        // if they were missing (e.g. order first seen via a partial event).
        if (isset($data['subtotal']) && (float) $order->subtotal === 0.0) {
            $updates['subtotal'] = $data['subtotal'];
            $updates['total'] = $data['total'] ?? $order->total;
            $updates['discount_total'] = $data['discount_total'] ?? $order->discount_total;
            $updates['coupon_code'] = ! empty($data['coupon_codes']) ? $data['coupon_codes'][0] : $order->coupon_code;
        }

        $order->forceFill(array_filter([
            'shipping_total' => isset($data['shipping_total']) && (float) $order->shipping_total === 0.0
                ? $data['shipping_total'] : null,
            'tax_total' => isset($data['tax_total']) && (float) $order->tax_total === 0.0
                ? $data['tax_total'] : null,
            'refund_total' => isset($data['refund_total']) && (float) $data['refund_total'] > 0
                ? $data['refund_total'] : null,
        ], fn ($v) => $v !== null) + $updates)->save();

        // Sync line items if they were missing.
        if (! empty($data['items']) && $order->items()->count() === 0) {
            $this->syncOrderItems($order, $data['items']);
        }

        return true;
    }

    private function updateOrderRefund(int $wcOrderId, array $data): bool
    {
        $orderId = DB::table('import_legacy_orders')
            ->where('legacy_wc_order_id', $wcOrderId)
            ->value('order_id');

        if (! $orderId) {
            return false;
        }

        $order = Order::find($orderId);

        if (! $order) {
            return false;
        }

        $order->forceFill([
            'refund_total' => $data['refund_total'] ?? $order->refund_total,
        ])->save();

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncOrderItems(Order $order, array $items): void
    {
        // Only add items if order has none yet (avoid duplicating on re-process).
        if ($order->items()->count() > 0) {
            return;
        }

        foreach ($items as $item) {
            $productId = null;
            $variantId = null;

            if (! empty($item['wc_product_id'])) {
                $productId = DB::table('import_legacy_products')
                    ->where('legacy_wp_post_id', $item['wc_product_id'])
                    ->value('product_id');
            }

            if (! empty($item['wc_variation_id'])) {
                $variantId = DB::table('import_legacy_variants')
                    ->where('legacy_wp_post_id', $item['wc_variation_id'])
                    ->value('product_variant_id');
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'product_name' => $item['name'] ?? 'Unknown',
                'variant_name' => null,
                'sku' => $item['sku'] ?? 'WC-ITEM',
                'unit_price' => $item['unit_price'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'line_total' => $item['line_total'] ?? 0,
            ]);
        }
    }

    /* ------------------------------------------------------------------
     *  Products
     * ----------------------------------------------------------------*/

    private function processProduct(string $event, array $data): bool
    {
        $wcProductId = $data['wc_product_id'] ?? null;

        if (! $wcProductId) {
            return false;
        }

        return match ($event) {
            'product.created', 'product.updated', 'product.restored' => $this->upsertProduct($wcProductId, $data),
            'product.trashed' => $this->trashProduct($wcProductId),
            'product.stock_changed' => $this->updateVariantStock($data),
            default => false,
        };
    }

    private function upsertProduct(int $wcProductId, array $data): bool
    {
        $existingProductId = DB::table('import_legacy_products')
            ->where('legacy_wp_post_id', $wcProductId)
            ->value('product_id');

        $attributes = [
            'name' => $data['name'] ?? '',
            'slug' => $data['slug'] ?? '',
            'status' => $this->mapWcProductStatus($data['status'] ?? 'publish'),
            'excerpt' => $data['excerpt'] ?? null,
            'description' => $data['description'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'is_preorder' => $data['is_preorder'] ?? false,
            'preorder_available_from' => isset($data['preorder_available_from']) ? Carbon::parse($data['preorder_available_from']) : null,
            'weight' => $data['weight'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : null,
        ];

        if ($existingProductId) {
            $product = Product::find($existingProductId);

            if (! $product) {
                return false;
            }

            $product->update($attributes);
        } else {
            // A product with the same slug/name may already exist (e.g. re-created in WC with a new ID).
            $product = Product::where('slug', $attributes['slug'])
                ->orWhere('name', $attributes['name'])
                ->first();

            if ($product) {
                $product->update($attributes);
            } else {
                $product = Product::create($attributes);
            }

            DB::table('import_legacy_products')->insert([
                'legacy_wp_post_id' => $wcProductId,
                'product_id' => $product->id,
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sync variants.
        if (! empty($data['variants'])) {
            $this->syncVariants($product, $data['variants']);
        } elseif (isset($data['price']) || isset($data['regular_price'])) {
            // Simple product (no variations) — create/update a default variant.
            $this->syncDefaultVariant($product, $wcProductId, $data);
        }

        // Sync categories.
        if (! empty($data['categories'])) {
            $this->syncProductCategories($product, $data['categories']);
        }

        // Sync images.
        if (! empty($data['images'])) {
            $this->syncProductImages($product, $data['images']);
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            $wcVariationId = $variantData['wc_variation_id'] ?? null;

            if (! $wcVariationId) {
                continue;
            }

            $existingVariantId = DB::table('import_legacy_variants')
                ->where('legacy_wp_post_id', $wcVariationId)
                ->value('product_variant_id');

            $attrs = [
                'product_id' => $product->id,
                'name' => $variantData['name'] ?? '',
                'sku' => $variantData['sku'] ?? "WC-{$wcVariationId}",
                'price' => $variantData['price'] ?? 0,
                'compare_at_price' => $variantData['regular_price'] ?? null,
                'stock_quantity' => $variantData['stock_quantity'] ?? 0,
                'is_active' => ($variantData['stock_status'] ?? 'instock') !== 'outofstock',
                'is_preorder' => $variantData['is_preorder'] ?? false,
                'preorder_available_from' => isset($variantData['preorder_available_from']) ? Carbon::parse($variantData['preorder_available_from']) : null,
            ];

            if ($existingVariantId) {
                // Clear SKU from any other variant to prevent unique constraint conflicts.
                ProductVariant::where('sku', $attrs['sku'])
                    ->where('id', '!=', $existingVariantId)
                    ->update(['sku' => DB::raw("CONCAT(sku, '-moved-', id)")]);

                ProductVariant::where('id', $existingVariantId)->update($attrs);
            } else {
                // Check for existing variant by SKU or by (product_id, name).
                $variant = ProductVariant::where('sku', $attrs['sku'])->first()
                    ?? ProductVariant::where('product_id', $product->id)
                        ->where('name', $attrs['name'])
                        ->first();

                if ($variant) {
                    // Clear conflicting SKU before updating.
                    if ($variant->sku !== $attrs['sku']) {
                        ProductVariant::where('sku', $attrs['sku'])
                            ->where('id', '!=', $variant->id)
                            ->update(['sku' => DB::raw("CONCAT(sku, '-moved-', id)")]);
                    }
                    $variant->update($attrs);
                } else {
                    $variant = ProductVariant::create($attrs);
                }

                DB::table('import_legacy_variants')->insert([
                    'legacy_wp_post_id' => $wcVariationId,
                    'product_variant_id' => $variant->id,
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Create or update a default variant for a simple (non-variable) WC product.
     */
    private function syncDefaultVariant(Product $product, int $wcProductId, array $data): void
    {
        $price = (float) ($data['price'] ?? $data['regular_price'] ?? 0);
        $regularPrice = (float) ($data['regular_price'] ?? $data['price'] ?? 0);

        $sku = $data['sku']
            ?? $data['_raw_meta']['_sku'] ?? null
            ?: "WC-{$wcProductId}";

        $attrs = [
            'product_id' => $product->id,
            'name' => 'Default',
            'sku' => $sku,
            'price' => $price > 0 ? $price : $regularPrice,
            'compare_at_price' => $regularPrice > $price && $price > 0 ? $regularPrice : null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'is_active' => ($data['stock_status'] ?? 'instock') !== 'outofstock',
            'is_preorder' => $data['is_preorder'] ?? false,
            'preorder_available_from' => isset($data['preorder_available_from']) ? Carbon::parse($data['preorder_available_from']) : null,
        ];

        // Check if this product already has a default variant.
        $existingVariant = ProductVariant::where('product_id', $product->id)
            ->where('name', 'Default')
            ->first();

        if ($existingVariant) {
            $existingVariant->update($attrs);

            return;
        }

        // Check via legacy mapping (WC simple products map their post ID to import_legacy_variants).
        $existingVariantId = DB::table('import_legacy_variants')
            ->where('legacy_wp_post_id', $wcProductId)
            ->value('product_variant_id');

        if ($existingVariantId) {
            ProductVariant::where('id', $existingVariantId)->update($attrs);

            return;
        }

        // Check for SKU conflict before creating.
        $skuVariant = ProductVariant::where('sku', $attrs['sku'])->first();

        if ($skuVariant) {
            $skuVariant->update($attrs);
            $variant = $skuVariant;
        } else {
            $variant = ProductVariant::create($attrs);
        }

        DB::table('import_legacy_variants')->insert([
            'legacy_wp_post_id' => $wcProductId,
            'product_variant_id' => $variant->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sync product category assignments using the import_legacy_categories mapping.
     * Only assigns existing categories — does not create new ones from WC data.
     *
     * @param  list<array<string, mixed>>  $categories
     */
    private function syncProductCategories(Product $product, array $categories): void
    {
        $categoryIds = [];
        $primaryCategoryId = null;

        foreach ($categories as $position => $cat) {
            $wcTermId = $cat['wc_term_id'] ?? null;

            if (! $wcTermId) {
                continue;
            }

            // Look up via mapping table first.
            $categoryId = DB::table('import_legacy_categories')
                ->where('legacy_term_id', $wcTermId)
                ->value('category_id');

            // Fall back to slug match if no mapping exists.
            if (! $categoryId) {
                $slug = $cat['slug'] ?? null;

                if ($slug) {
                    $categoryId = Category::where('slug', $slug)->value('id');
                }
            }

            if ($categoryId) {
                $categoryIds[$categoryId] = ['position' => $position];

                if ($primaryCategoryId === null) {
                    $primaryCategoryId = $categoryId;
                }
            }
        }

        if (! empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        if ($primaryCategoryId && $product->primary_category_id !== $primaryCategoryId) {
            $product->update(['primary_category_id' => $primaryCategoryId]);
        }
    }

    /**
     * Download product images from WooCommerce URLs and create ProductMedia records.
     * Skips images that already exist for this product.
     *
     * @param  list<array<string, mixed>>  $images
     */
    private function syncProductImages(Product $product, array $images): void
    {
        // Skip if product already has images (avoid re-downloading on replays).
        if ($product->media()->count() > 0) {
            return;
        }

        $slug = $product->slug ?: 'product-'.$product->id;
        $directory = "products/{$slug}";

        foreach ($images as $imageData) {
            $url = $imageData['url'] ?? null;

            if (! $url) {
                continue;
            }

            try {
                $response = Http::timeout(15)->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
                $filename = $slug.'-'.($imageData['position'] ?? 0).'.'.$extension;
                $path = "{$directory}/{$filename}";

                Storage::disk('public')->put($path, $response->body());

                $mimeType = $response->header('Content-Type') ?: 'image/jpeg';

                ProductMedia::create([
                    'product_id' => $product->id,
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'alt_text' => $imageData['alt'] ?? $product->name,
                    'is_primary' => ($imageData['position'] ?? 0) === 0,
                    'position' => $imageData['position'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                Log::warning("[WC Sync] Failed to download image for product #{$product->id}: {$e->getMessage()}", [
                    'url' => $url,
                ]);
            }
        }
    }

    private function trashProduct(int $wcProductId): bool
    {
        $productId = DB::table('import_legacy_products')
            ->where('legacy_wp_post_id', $wcProductId)
            ->value('product_id');

        if (! $productId) {
            return false;
        }

        Product::where('id', $productId)->update(['status' => 'archived']);

        return true;
    }

    private function updateVariantStock(array $data): bool
    {
        $wcVariationId = $data['wc_variation_id'] ?? null;

        if (! $wcVariationId) {
            return false;
        }

        $variantId = DB::table('import_legacy_variants')
            ->where('legacy_wp_post_id', $wcVariationId)
            ->value('product_variant_id');

        if (! $variantId) {
            return false;
        }

        ProductVariant::where('id', $variantId)->update([
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'is_active' => ($data['stock_status'] ?? 'instock') !== 'outofstock',
        ]);

        return true;
    }

    /* ------------------------------------------------------------------
     *  Coupons
     * ----------------------------------------------------------------*/

    private function processCoupon(string $event, array $data): bool
    {
        if ($event === 'coupon.deleted') {
            $code = $data['code'] ?? null;

            if (! $code) {
                return false;
            }

            Coupon::where('code', $code)->delete();

            return true;
        }

        if ($event === 'coupon.saved') {
            $code = $data['code'] ?? null;

            if (! $code) {
                return false;
            }

            Coupon::updateOrCreate(
                ['code' => $code],
                [
                    'type' => $this->mapCouponType($data['discount_type'] ?? 'fixed_cart'),
                    'value' => $data['amount'] ?? 0,
                    'minimum_subtotal' => $data['minimum_amount'] ?? 0,
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'used_count' => $data['usage_count'] ?? 0,
                    'is_active' => ! isset($data['date_expires']) || Carbon::parse($data['date_expires'])->isFuture(),
                    'description' => $data['description'] ?? null,
                    'ends_at' => isset($data['date_expires']) ? Carbon::parse($data['date_expires']) : null,
                ]
            );

            return true;
        }

        return false;
    }

    /* ------------------------------------------------------------------
     *  Customers
     * ----------------------------------------------------------------*/

    private function processCustomer(string $event, array $data): bool
    {
        $wcUserId = $data['wc_user_id'] ?? null;

        if (! $wcUserId) {
            return false;
        }

        if (! in_array($event, ['customer.created', 'customer.updated'], true)) {
            return false;
        }

        $existingUserId = DB::table('import_legacy_customers')
            ->where('legacy_wp_user_id', $wcUserId)
            ->value('user_id');

        $billing = $data['billing'] ?? [];
        $shipping = $data['shipping'] ?? [];

        $attrs = [
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => $data['email'] ?? '',
            'delivery_country' => $shipping['country'] ?? $billing['country'] ?? null,
            'delivery_state' => $shipping['state'] ?? $billing['state'] ?? null,
            'delivery_city' => $shipping['city'] ?? $billing['city'] ?? null,
            'delivery_postal_code' => $shipping['postal_code'] ?? $billing['postal_code'] ?? null,
            'delivery_street_name' => $shipping['address_1'] ?? $billing['address_1'] ?? null,
            'delivery_phone' => $billing['phone'] ?? null,
        ];

        if ($existingUserId) {
            User::where('id', $existingUserId)->update($attrs);
        } else {
            // Check if user exists by email first.
            $user = User::where('email', $attrs['email'])->first();

            if (! $user) {
                $user = User::forceCreate($attrs + [
                    'password' => bcrypt(bin2hex(random_bytes(16))),
                ]);
            } else {
                $user->update($attrs);
            }

            DB::table('import_legacy_customers')->insert([
                'legacy_wp_user_id' => $wcUserId,
                'user_id' => $user->id,
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    /* ------------------------------------------------------------------
     *  Notes & Tracking
     * ----------------------------------------------------------------*/

    private function processNote(string $event, array $data): bool
    {
        $wcOrderId = $data['wc_order_id'] ?? null;

        if (! $wcOrderId) {
            return false;
        }

        $orderId = DB::table('import_legacy_orders')
            ->where('legacy_wc_order_id', $wcOrderId)
            ->value('order_id');

        if (! $orderId) {
            return false;
        }

        if ($event === 'note.tracking') {
            Order::where('id', $orderId)->update([
                'shipping_carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'shipped_at' => isset($data['shipped_at']) ? Carbon::parse($data['shipped_at']) : null,
            ]);

            return true;
        }

        // note.created — store as a log entry; the app doesn't have per-order notes yet.
        if ($event === 'note.created') {
            Log::info("[WC Sync] Order note for order #{$orderId}", [
                'wc_order_id' => $wcOrderId,
                'content' => $data['content'] ?? '',
                'author' => $data['author'] ?? '',
                'is_customer_note' => $data['is_customer_note'] ?? false,
            ]);

            return true;
        }

        return false;
    }

    /* ------------------------------------------------------------------
     *  Status mapping helpers
     * ----------------------------------------------------------------*/

    private function mapWcOrderStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'completed' => 'completed',
            'processing' => 'processing',
            'on-hold' => 'on-hold',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'pre-ordered' => 'pre-ordered',
            default => $wcStatus,
        };
    }

    private function mapPaymentStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'completed', 'processing' => 'paid',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'pending', 'on-hold' => 'pending',
            default => 'pending',
        };
    }

    private function mapWcProductStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'publish' => 'active',
            'draft' => 'draft',
            'private' => 'draft',
            'trash' => 'archived',
            default => 'draft',
        };
    }

    private function mapCouponType(string $wcType): string
    {
        return match ($wcType) {
            'percent' => 'percent',
            default => 'fixed',
        };
    }
}
