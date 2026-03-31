<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'variant_type',
        'option_values',
        'price',
        'compare_at_price',
        'track_inventory',
        'stock_quantity',
        'allow_backorder',
        'is_active',
        'is_preorder',
        'position',
        'preorder_available_from',
        'expected_ship_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_values' => 'array',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'is_active' => 'boolean',
            'is_preorder' => 'boolean',
            'preorder_available_from' => 'datetime',
            'expected_ship_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }

    public function bundleDiscountItems(): HasMany
    {
        return $this->hasMany(BundleDiscountItem::class, 'product_variant_id');
    }

    public function stockAlertSubscriptions(): HasMany
    {
        return $this->hasMany(StockAlertSubscription::class, 'product_variant_id');
    }

    public function isSize(): bool
    {
        return $this->variant_type === 'size';
    }

    public function isColor(): bool
    {
        return $this->variant_type === 'color';
    }

    public function isCustom(): bool
    {
        return $this->variant_type === 'custom';
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ($this->stock_quantity > 0 || $this->allow_backorder || $this->is_preorder);
    }

    public static function detectTypeFromName(string $name): string
    {
        if (preg_match('/^(XXS|XS|S|M|L|XL|XXL|XXXL|2XL|3XL|4XL|5XL|\d+)$/i', trim($name))) {
            return 'size';
        }

        if (preg_match('/(black|white|red|blue|green|yellow|navy|gray|grey|purple|orange|pink|brown|beige|tan|olive|maroon|teal|cyan|magenta|gold|silver)/i', $name)) {
            return 'color';
        }

        return 'custom';
    }
}
