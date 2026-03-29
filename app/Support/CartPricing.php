<?php

namespace App\Support;

use App\Models\Coupon;

class CartPricing
{
    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{subtotal: float, discount_total: float, total: float, coupon: ?Coupon, coupon_code: ?string, requested_coupon_code: ?string, error: ?string}
     */
    public function summarize(array $items, ?string $couponCode = null): array
    {
        $subtotal = (float) collect($items)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity']);

        return $this->summarizeFromSubtotal($subtotal, $couponCode);
    }

    /**
     * @return array{subtotal: float, discount_total: float, total: float, coupon: ?Coupon, coupon_code: ?string, requested_coupon_code: ?string, error: ?string}
     */
    public function summarizeFromSubtotal(float $subtotal, ?string $couponCode = null): array
    {
        $normalizedCode = $this->normalizeCouponCode($couponCode);

        $summary = [
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0.0,
            'total' => round($subtotal, 2),
            'coupon' => null,
            'coupon_code' => null,
            'requested_coupon_code' => $normalizedCode,
            'error' => null,
        ];

        if ($normalizedCode === null) {
            return $summary;
        }

        $coupon = Coupon::query()->where('code', $normalizedCode)->first();

        if ($coupon === null) {
            $summary['error'] = 'That coupon code is invalid.';

            return $summary;
        }

        $error = $coupon->validationError($subtotal);

        if ($error !== null) {
            $summary['error'] = $error;

            return $summary;
        }

        $discountTotal = $coupon->discountFor($subtotal);

        if ($discountTotal <= 0) {
            $summary['error'] = 'That coupon does not apply to the current cart.';

            return $summary;
        }

        $summary['coupon'] = $coupon;
        $summary['coupon_code'] = $coupon->code;
        $summary['discount_total'] = round($discountTotal, 2);
        $summary['total'] = round(max(0, $subtotal - $discountTotal), 2);

        return $summary;
    }

    private function normalizeCouponCode(?string $couponCode): ?string
    {
        $normalizedCode = strtoupper(trim((string) $couponCode));

        return $normalizedCode !== '' ? $normalizedCode : null;
    }
}
