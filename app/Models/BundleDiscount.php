<?php

namespace App\Models;

use App\Concerns\HasEditHistory;
use Database\Factories\BundleDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BundleDiscount extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    use HasEditHistory;

    /** @use HasFactory<BundleDiscountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'description',
        'bundle_price',
        'discount_type',
        'discount_value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [self::TYPE_FIXED, self::TYPE_PERCENT];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleDiscountItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Calculate savings when bundle_price is set.
     * Savings = sum of component prices - bundle_price.
     */
    public function calculateSavings(float $componentTotal): float
    {
        if ($this->bundle_price === null || $componentTotal <= 0) {
            return 0.0;
        }

        return max(0.0, round($componentTotal - (float) $this->bundle_price, 2));
    }

    /**
     * Calculate the combined subtotal of only the bundle's component items from the cart.
     * Uses each bundle item's required quantity (min_quantity), not the full cart quantity.
     *
     * @param  array<int|string, array<string, mixed>>  $cartItems
     */
    public function componentSubtotal(array $cartItems): float
    {
        $cartByVariant = collect($cartItems)->keyBy(fn (array $item): int => (int) $item['variant_id']);

        $cartVariantToProduct = collect($cartItems)
            ->filter(fn (array $item): bool => isset($item['product_id']))
            ->mapWithKeys(fn (array $item): array => [(int) $item['variant_id'] => (int) $item['product_id']])
            ->all();

        $total = 0.0;

        /** @var BundleDiscountItem $bundleItem */
        foreach ($this->items as $bundleItem) {
            $requiredQty = max(1, (int) $bundleItem->min_quantity);

            if ($bundleItem->product_variant_id) {
                $cartItem = $cartByVariant->get((int) $bundleItem->product_variant_id);

                if ($cartItem !== null) {
                    $total += (float) ($cartItem['price'] ?? $cartItem['unit_price'] ?? 0) * $requiredQty;
                }
            } elseif ($bundleItem->product_id) {
                $matchingVariantIds = collect($cartVariantToProduct)
                    ->filter(fn (int $productId): bool => $productId === (int) $bundleItem->product_id)
                    ->keys();

                $remaining = $requiredQty;

                foreach ($matchingVariantIds as $variantId) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $cartItem = $cartByVariant->get($variantId);

                    if ($cartItem !== null) {
                        $available = (int) ($cartItem['quantity'] ?? 0);
                        $use = min($remaining, $available);
                        $total += (float) ($cartItem['price'] ?? $cartItem['unit_price'] ?? 0) * $use;
                        $remaining -= $use;
                    }
                }
            }
        }

        return round($total, 2);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $cartItems
     */
    public function isApplicableToCart(array $cartItems): bool
    {
        if (! $this->is_active || $this->items->isEmpty()) {
            return false;
        }

        /** @var array<int, int> $cartQuantities  variant_id => quantity */
        $cartQuantities = collect($cartItems)
            ->mapWithKeys(fn (array $item): array => [(int) $item['variant_id'] => (int) $item['quantity']])
            ->all();

        /** @var array<int, int> $cartVariantToProduct  variant_id => product_id */
        $cartVariantToProduct = collect($cartItems)
            ->filter(fn (array $item): bool => isset($item['product_id']))
            ->mapWithKeys(fn (array $item): array => [(int) $item['variant_id'] => (int) $item['product_id']])
            ->all();

        foreach ($this->items as $item) {
            $requiredQuantity = max(1, (int) $item->min_quantity);

            if ($item->product_variant_id) {
                // Specific variant required
                if (($cartQuantities[(int) $item->product_variant_id] ?? 0) < $requiredQuantity) {
                    return false;
                }
            } elseif ($item->product_id) {
                // Any variant of this product satisfies the requirement
                $totalQty = collect($cartVariantToProduct)
                    ->filter(fn (int $productId): bool => $productId === (int) $item->product_id)
                    ->keys()
                    ->sum(fn (int $variantId): int => $cartQuantities[$variantId] ?? 0);

                if ($totalQty < $requiredQuantity) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    public function discountFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        // When bundle_price is set, discount = subtotal - bundle_price
        if ($this->bundle_price !== null) {
            return $this->calculateSavings($subtotal);
        }

        // Legacy: fixed or percent discount
        $discount = match ($this->discount_type) {
            self::TYPE_PERCENT => round($subtotal * ((float) $this->discount_value / 100), 2),
            default => round((float) $this->discount_value, 2),
        };

        return min($subtotal, max(0.0, $discount));
    }
}
