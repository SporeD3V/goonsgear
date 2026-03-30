<?php

namespace App\Actions\Checkout;

use App\Models\CartAbandonment;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    private static ?bool $orderPaymentColumnsAvailable = null;

    /**
     * @var array<string, bool>
     */
    private static array $orderColumnAvailability = [];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $normalizedItems
     * @param  array<int, string>  $couponCodes
     * @param  array<string, float>  $couponBreakdown
     */
    public function execute(
        array $payload,
        array $normalizedItems,
        float $subtotal,
        float $discountTotal,
        float $total,
        array $couponCodes,
        array $couponBreakdown,
        string $paymentMethod,
        string $paymentStatus,
        ?string $paypalOrderId = null,
        ?string $paypalCaptureId = null,
        bool $markAsPaid = false,
        float $regionalDiscountTotal = 0.0,
        float $bundleDiscountTotal = 0.0,
        ?User $customer = null,
    ): Order {
        $order = DB::transaction(function () use ($payload, $normalizedItems, $subtotal, $discountTotal, $total, $couponCodes, $couponBreakdown, $paymentMethod, $paymentStatus, $paypalOrderId, $paypalCaptureId, $markAsPaid, $regionalDiscountTotal, $bundleDiscountTotal, $customer): Order {
            $variantIds = collect($normalizedItems)->pluck('product_variant_id')->map(fn ($id): int => (int) $id)->values();
            $variants = ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');

            foreach ($normalizedItems as $item) {
                $variant = $variants->get((int) $item['product_variant_id']);
                $quantity = (int) $item['quantity'];

                if ($variant === null || ! $variant->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => 'One or more cart items are no longer available.',
                    ]);
                }

                if ($variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder && $quantity > $variant->stock_quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Insufficient stock for {$variant->name}. Please update your cart quantity.",
                    ]);
                }
            }

            $orderPayload = [
                'order_number' => $this->generateOrderNumber(),
                'status' => $markAsPaid ? 'paid' : 'pending',
                'email' => (string) $payload['email'],
                'first_name' => (string) $payload['first_name'],
                'last_name' => (string) $payload['last_name'],
                'phone' => isset($payload['phone']) ? (string) $payload['phone'] : null,
                'country' => strtoupper((string) $payload['country']),
                'city' => (string) $payload['city'],
                'postal_code' => (string) $payload['postal_code'],
                'street_name' => (string) $payload['street_name'],
                'street_number' => (string) $payload['street_number'],
                'apartment_block' => isset($payload['apartment_block']) ? (string) $payload['apartment_block'] : null,
                'entrance' => isset($payload['entrance']) ? (string) $payload['entrance'] : null,
                'floor' => isset($payload['floor']) ? (string) $payload['floor'] : null,
                'apartment_number' => isset($payload['apartment_number']) ? (string) $payload['apartment_number'] : null,
                'currency' => 'EUR',
                'subtotal' => $subtotal,
                'total' => $total,
                'placed_at' => now(),
            ];

            if ($this->orderColumnAvailable('coupon_code')) {
                $orderPayload['coupon_code'] = $couponCodes !== [] ? implode(', ', $couponCodes) : null;
            }

            if ($this->orderColumnAvailable('discount_total')) {
                $orderPayload['discount_total'] = $discountTotal;
            }

            if ($this->orderColumnAvailable('state')) {
                $orderPayload['state'] = isset($payload['state']) ? (string) $payload['state'] : null;
            }

            if ($this->orderColumnAvailable('regional_discount_total')) {
                $orderPayload['regional_discount_total'] = $regionalDiscountTotal;
            }

            if ($this->orderColumnAvailable('bundle_discount_total')) {
                $orderPayload['bundle_discount_total'] = $bundleDiscountTotal;
            }

            if ($this->orderPaymentColumnsAvailable()) {
                $orderPayload['payment_method'] = $paymentMethod;
                $orderPayload['payment_status'] = $paymentStatus;
                $orderPayload['paypal_order_id'] = $paypalOrderId;
                $orderPayload['paypal_capture_id'] = $paypalCaptureId;
            }

            $order = Order::query()->create($orderPayload);

            $order->items()->createMany($normalizedItems);

            foreach ($normalizedItems as $item) {
                $variant = $variants->get((int) $item['product_variant_id']);

                if ($variant !== null && $variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder) {
                    $variant->decrement('stock_quantity', (int) $item['quantity']);
                }
            }

            if ($couponCodes !== []) {
                Coupon::query()->whereIn('code', $couponCodes)->increment('used_count');

                $couponLookup = Coupon::query()
                    ->whereIn('code', $couponCodes)
                    ->get(['id', 'code', 'is_personal'])
                    ->keyBy('code');

                foreach ($couponCodes as $position => $couponCode) {
                    $coupon = $couponLookup->get($couponCode);

                    if ($customer !== null && $coupon instanceof Coupon && $coupon->is_personal) {
                        if (! Coupon::assignmentTableExists()) {
                            throw ValidationException::withMessages([
                                'coupon_code' => 'Personal coupons are temporarily unavailable while coupon assignments are being updated.',
                            ]);
                        }

                        $updated = DB::table('coupon_user')
                            ->where('coupon_id', $coupon->id)
                            ->where('user_id', $customer->id)
                            ->where('is_active', true)
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('usage_limit')
                                    ->orWhereColumn('used_count', '<', 'usage_limit');
                            })
                            ->increment('used_count');

                        if ($updated === 0) {
                            throw ValidationException::withMessages([
                                'coupon_code' => "Coupon {$couponCode} reached its usage limit. Please review your cart and retry.",
                            ]);
                        }
                    }

                    $order->couponUsages()->create([
                        'coupon_id' => $coupon?->id,
                        'coupon_code' => $couponCode,
                        'discount_total' => (float) ($couponBreakdown[$couponCode] ?? 0.0),
                        'applied_position' => $position,
                    ]);
                }
            }

            return $order;
        });

        CartAbandonment::query()
            ->where('email', $order->email)
            ->whereNull('recovered_at')
            ->update(['recovered_at' => now()]);

        if ($customer !== null) {
            $customer->update([
                'delivery_phone' => isset($payload['phone']) ? (string) $payload['phone'] : null,
                'delivery_country' => strtoupper((string) $payload['country']),
                'delivery_state' => isset($payload['state']) ? (string) $payload['state'] : null,
                'delivery_city' => (string) $payload['city'],
                'delivery_postal_code' => (string) $payload['postal_code'],
                'delivery_street_name' => (string) $payload['street_name'],
                'delivery_street_number' => (string) $payload['street_number'],
                'delivery_apartment_block' => isset($payload['apartment_block']) ? (string) $payload['apartment_block'] : null,
                'delivery_entrance' => isset($payload['entrance']) ? (string) $payload['entrance'] : null,
                'delivery_floor' => isset($payload['floor']) ? (string) $payload['floor'] : null,
                'delivery_apartment_number' => isset($payload['apartment_number']) ? (string) $payload['apartment_number'] : null,
            ]);
        }

        return $order;
    }

    private function orderPaymentColumnsAvailable(): bool
    {
        if (self::$orderPaymentColumnsAvailable !== null) {
            return self::$orderPaymentColumnsAvailable;
        }

        self::$orderPaymentColumnsAvailable = Schema::hasColumns('orders', [
            'payment_method',
            'payment_status',
            'paypal_order_id',
            'paypal_capture_id',
        ]);

        return self::$orderPaymentColumnsAvailable;
    }

    private function orderColumnAvailable(string $column): bool
    {
        if (array_key_exists($column, self::$orderColumnAvailability)) {
            return self::$orderColumnAvailability[$column];
        }

        self::$orderColumnAvailability[$column] = Schema::hasColumn('orders', $column);

        return self::$orderColumnAvailability[$column];
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'GG-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
