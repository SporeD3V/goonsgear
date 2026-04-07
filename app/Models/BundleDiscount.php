<?php

namespace App\Models;

use App\Concerns\HasEditHistory;
use Database\Factories\BundleDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'name',
        'description',
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

    public function items(): HasMany
    {
        return $this->hasMany(BundleDiscountItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $cartItems
     */
    public function isApplicableToCart(array $cartItems): bool
    {
        if (! $this->is_active || $this->items->isEmpty()) {
            return false;
        }

        /** @var array<int, int> $cartQuantities */
        $cartQuantities = collect($cartItems)
            ->mapWithKeys(fn (array $item): array => [(int) $item['variant_id'] => (int) $item['quantity']])
            ->all();

        foreach ($this->items as $item) {
            $variantId = (int) $item->product_variant_id;
            $requiredQuantity = max(1, (int) $item->min_quantity);

            if (($cartQuantities[$variantId] ?? 0) < $requiredQuantity) {
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

        $discount = match ($this->discount_type) {
            self::TYPE_PERCENT => round($subtotal * ((float) $this->discount_value / 100), 2),
            default => round((float) $this->discount_value, 2),
        };

        return min($subtotal, max(0.0, $discount));
    }
}
