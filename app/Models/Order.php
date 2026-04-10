<?php

namespace App\Models;

use App\Concerns\HasEditHistory;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasEditHistory;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'status',
        'payment_method',
        'payment_status',
        'shipping_carrier',
        'tracking_number',
        'paypal_order_id',
        'paypal_capture_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'country',
        'state',
        'city',
        'postal_code',
        'street_name',
        'street_number',
        'apartment_block',
        'entrance',
        'floor',
        'apartment_number',
        'currency',
        'coupon_code',
        'discount_total',
        'regional_discount_total',
        'bundle_discount_total',
        'bundle_sku',
        'subtotal',
        'total',
        'placed_at',
        'shipped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_total' => 'decimal:2',
            'regional_discount_total' => 'decimal:2',
            'bundle_discount_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(OrderCouponUsage::class);
    }
}
