<?php

namespace App\Models;

use Database\Factories\BundleDiscountItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleDiscountItem extends Model
{
    /** @use HasFactory<BundleDiscountItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bundle_discount_id',
        'product_variant_id',
        'min_quantity',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'position' => 'integer',
        ];
    }

    public function bundleDiscount(): BelongsTo
    {
        return $this->belongsTo(BundleDiscount::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
