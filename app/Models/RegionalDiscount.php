<?php

namespace App\Models;

use Database\Factories\RegionalDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionalDiscount extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    /** @use HasFactory<RegionalDiscountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_code',
        'discount_type',
        'discount_value',
        'reason',
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

    public static function findForCountry(string $countryCode): ?self
    {
        return static::query()
            ->where('country_code', strtoupper($countryCode))
            ->where('is_active', true)
            ->first();
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
