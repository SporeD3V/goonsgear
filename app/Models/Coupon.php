<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'minimum_subtotal',
        'usage_limit',
        'used_count',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_subtotal' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [self::TYPE_FIXED, self::TYPE_PERCENT];
    }

    public function validationError(float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is not active.';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'This coupon is not active yet.';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'This coupon has expired.';
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }

        if ($this->minimum_subtotal !== null && $subtotal < (float) $this->minimum_subtotal) {
            return 'This coupon requires a minimum subtotal of $'.number_format((float) $this->minimum_subtotal, 2).'.';
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $discount = match ($this->type) {
            self::TYPE_PERCENT => round($subtotal * ((float) $this->value / 100), 2),
            default => round((float) $this->value, 2),
        };

        return min($subtotal, max(0.0, $discount));
    }
}
