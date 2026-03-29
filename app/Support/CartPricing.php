<?php

namespace App\Support;

use App\Models\BundleDiscount;
use App\Models\Coupon;
use App\Models\RegionalDiscount;

class CartPricing
{
    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{subtotal: float, discount_total: float, regional_discount_total: float, bundle_discount_total: float, total: float, coupon: ?Coupon, coupon_code: ?string, requested_coupon_code: ?string, error: ?string, regional_discount: ?RegionalDiscount, bundle_discount: ?BundleDiscount}
     */
    public function summarize(array $items, ?string $couponCode = null, ?string $countryCode = null): array
    {
        $subtotal = (float) collect($items)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity']);
        $summary = $this->summarizeFromSubtotal($subtotal, $couponCode, $countryCode);

        if ($summary['error'] !== null) {
            return $summary;
        }

        $applicableBundle = BundleDiscount::query()
            ->where('is_active', true)
            ->with('items')
            ->get()
            ->filter(fn (BundleDiscount $bundle): bool => $bundle->isApplicableToCart($items))
            ->sortByDesc(fn (BundleDiscount $bundle): float => $bundle->discountFor($subtotal))
            ->first();

        if ($applicableBundle !== null) {
            $summary['bundle_discount'] = $applicableBundle;
            $summary['bundle_discount_total'] = round($applicableBundle->discountFor($subtotal), 2);
            $summary['total'] = round(max(0, $summary['total'] - $summary['bundle_discount_total']), 2);
        }

        return $summary;
    }

    /**
     * @return array{subtotal: float, discount_total: float, regional_discount_total: float, bundle_discount_total: float, total: float, coupon: ?Coupon, coupon_code: ?string, requested_coupon_code: ?string, error: ?string, regional_discount: ?RegionalDiscount, bundle_discount: ?BundleDiscount}
     */
    public function summarizeFromSubtotal(float $subtotal, ?string $couponCode = null, ?string $countryCode = null): array
    {
        $normalizedCode = $this->normalizeCouponCode($couponCode);

        $summary = [
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0.0,
            'regional_discount_total' => 0.0,
            'bundle_discount_total' => 0.0,
            'total' => round($subtotal, 2),
            'coupon' => null,
            'coupon_code' => null,
            'requested_coupon_code' => $normalizedCode,
            'error' => null,
            'regional_discount' => null,
            'bundle_discount' => null,
        ];

        if ($normalizedCode !== null) {
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
        }

        $regionalDiscount = $countryCode !== null ? RegionalDiscount::findForCountry($countryCode) : null;

        if ($regionalDiscount !== null) {
            $regionalDiscountTotal = round($regionalDiscount->discountFor($subtotal), 2);
            $summary['regional_discount'] = $regionalDiscount;
            $summary['regional_discount_total'] = $regionalDiscountTotal;
        }

        $summary['total'] = round(max(0, $subtotal - $summary['discount_total'] - $summary['regional_discount_total']), 2);

        return $summary;
    }

    private function normalizeCouponCode(?string $couponCode): ?string
    {
        $normalizedCode = strtoupper(trim((string) $couponCode));

        return $normalizedCode !== '' ? $normalizedCode : null;
    }
}
