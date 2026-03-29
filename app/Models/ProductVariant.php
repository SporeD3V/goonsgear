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
}
