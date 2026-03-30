<?php

namespace App\Support;

use App\Models\BundleDiscount;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\RegionalDiscount;
use App\Models\User;
use Illuminate\Support\Collection;

class CartPricing
{
    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  string|array<int, string>|null  $couponCodes
     * @return array{subtotal: float, discount_total: float, regional_discount_total: float, bundle_discount_total: float, total: float, coupon: ?Coupon, coupons: Collection<int, Coupon>, coupon_code: ?string, coupon_codes: array<int, string>, coupon_breakdown: array<string, float>, requested_coupon_code: ?string, requested_coupon_codes: array<int, string>, invalid_coupon_messages: array<string, string>, recommendation_message: ?string, error: ?string, regional_discount: ?RegionalDiscount, bundle_discount: ?BundleDiscount}
     */
    public function summarize(array $items, string|array|null $couponCodes = null, ?string $countryCode = null, ?User $user = null): array
    {
        $subtotal = (float) collect($items)->sum(fn (array $item): float => $this->itemSubtotal($item));
        $summary = $this->summarizeFromSubtotal($subtotal, $couponCodes, $countryCode, $items, $user);

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
     * @param  string|array<int, string>|null  $couponCodes
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{subtotal: float, discount_total: float, regional_discount_total: float, bundle_discount_total: float, total: float, coupon: ?Coupon, coupons: Collection<int, Coupon>, coupon_code: ?string, coupon_codes: array<int, string>, coupon_breakdown: array<string, float>, requested_coupon_code: ?string, requested_coupon_codes: array<int, string>, invalid_coupon_messages: array<string, string>, recommendation_message: ?string, error: ?string, regional_discount: ?RegionalDiscount, bundle_discount: ?BundleDiscount}
     */
    public function summarizeFromSubtotal(float $subtotal, string|array|null $couponCodes = null, ?string $countryCode = null, array $items = [], ?User $user = null): array
    {
        $normalizedCodes = $this->normalizeCouponCodes($couponCodes);

        $summary = [
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0.0,
            'regional_discount_total' => 0.0,
            'bundle_discount_total' => 0.0,
            'total' => round($subtotal, 2),
            'coupon' => null,
            'coupons' => collect(),
            'coupon_code' => null,
            'coupon_codes' => [],
            'coupon_breakdown' => [],
            'requested_coupon_code' => $normalizedCodes[0] ?? null,
            'requested_coupon_codes' => $normalizedCodes,
            'invalid_coupon_messages' => [],
            'recommendation_message' => null,
            'error' => null,
            'regional_discount' => null,
            'bundle_discount' => null,
        ];

        if ($normalizedCodes !== []) {
            $couponPricing = $this->resolveCouponPricing($items, $subtotal, $normalizedCodes, $user);

            if ($couponPricing['applied_coupons']->isEmpty() && $couponPricing['invalid_coupon_messages'] !== []) {
                $summary['error'] = array_values($couponPricing['invalid_coupon_messages'])[0];
                $summary['invalid_coupon_messages'] = $couponPricing['invalid_coupon_messages'];

                return $summary;
            }

            $summary['coupons'] = $couponPricing['applied_coupons'];
            $summary['coupon'] = $couponPricing['applied_coupons']->first();
            $summary['coupon_codes'] = $couponPricing['applied_coupon_codes'];
            $summary['coupon_breakdown'] = $couponPricing['coupon_breakdown'];
            $summary['coupon_code'] = $couponPricing['applied_coupon_codes'] !== []
                ? implode(', ', $couponPricing['applied_coupon_codes'])
                : null;
            $summary['discount_total'] = round($couponPricing['discount_total'], 2);
            $summary['invalid_coupon_messages'] = $couponPricing['invalid_coupon_messages'];
            $summary['recommendation_message'] = $couponPricing['recommendation_message'];
        }

        $regionalDiscount = $countryCode !== null ? RegionalDiscount::findForCountry($countryCode) : null;

        if ($regionalDiscount !== null) {
            $summary['regional_discount'] = $regionalDiscount;
            $summary['regional_discount_total'] = round($regionalDiscount->discountFor($subtotal), 2);
        }

        $summary['total'] = round(max(0, $subtotal - $summary['discount_total'] - $summary['regional_discount_total']), 2);

        return $summary;
    }

    /**
     * @param  string|array<int, string>|null  $couponCodes
     * @return array<int, string>
     */
    private function normalizeCouponCodes(string|array|null $couponCodes): array
    {
        $candidateCodes = is_array($couponCodes) ? $couponCodes : [$couponCodes];

        return collect($candidateCodes)
            ->map(fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  array<int, string>  $requestedCodes
     * @return array{applied_coupons: Collection<int, Coupon>, applied_coupon_codes: array<int, string>, coupon_breakdown: array<string, float>, discount_total: float, invalid_coupon_messages: array<string, string>, recommendation_message: ?string}
     */
    private function resolveCouponPricing(array $items, float $subtotal, array $requestedCodes, ?User $user): array
    {
        $invalidCouponMessages = [];

        $requestedCouponsQuery = Coupon::query()->whereIn('code', $requestedCodes);

        if ($user !== null) {
            $requestedCouponsQuery->with([
                'users' => fn ($query) => $query
                    ->select('users.id')
                    ->where('users.id', $user->id),
            ]);
        }

        $requestedCoupons = $requestedCouponsQuery->get()->keyBy('code');

        $productsById = $this->loadProductsById($items);
        $eligibleCoupons = collect();

        foreach ($requestedCodes as $code) {
            $coupon = $requestedCoupons->get($code);

            if (! $coupon instanceof Coupon) {
                $invalidCouponMessages[$code] = 'That coupon code is invalid.';

                continue;
            }

            $validationError = $coupon->validationError($subtotal);

            if ($validationError !== null) {
                $invalidCouponMessages[$code] = $validationError;

                continue;
            }

            if ($coupon->is_personal) {
                if ($user === null) {
                    $invalidCouponMessages[$code] = 'This coupon is only available for signed-in accounts.';

                    continue;
                }

                $assignment = $coupon->users->firstWhere('id', $user->id);

                if ($assignment === null || ! $assignment->pivot->is_active) {
                    $invalidCouponMessages[$code] = 'This coupon is not assigned to your account.';

                    continue;
                }

                if ($assignment->pivot->usage_limit !== null && $assignment->pivot->used_count >= $assignment->pivot->usage_limit) {
                    $invalidCouponMessages[$code] = 'You have already used this coupon the maximum number of times.';

                    continue;
                }
            }

            $eligibleSubtotal = empty($items) ? $subtotal : $this->eligibleSubtotalForCoupon($coupon, $items, $productsById);

            if ($eligibleSubtotal <= 0) {
                $invalidCouponMessages[$code] = 'That coupon does not apply to the current cart.';

                continue;
            }

            $discount = $coupon->discountFor($eligibleSubtotal);

            if ($discount <= 0) {
                $invalidCouponMessages[$code] = 'That coupon does not apply to the current cart.';

                continue;
            }

            $eligibleCoupons->push([
                'coupon' => $coupon,
                'discount' => round($discount, 2),
            ]);
        }

        if ($eligibleCoupons->isEmpty()) {
            return [
                'applied_coupons' => collect(),
                'applied_coupon_codes' => [],
                'coupon_breakdown' => [],
                'discount_total' => 0.0,
                'invalid_coupon_messages' => $invalidCouponMessages,
                'recommendation_message' => null,
            ];
        }

        $bestCombination = $this->bestCouponCombination($eligibleCoupons, $subtotal);
        $appliedCoupons = $bestCombination->pluck('coupon');
        $appliedCodes = $appliedCoupons->pluck('code')->all();
        $couponBreakdown = $bestCombination
            ->mapWithKeys(fn (array $entry): array => [(string) $entry['coupon']->code => (float) $entry['discount']])
            ->all();
        $discountTotal = round((float) $bestCombination->sum('discount'), 2);

        return [
            'applied_coupons' => $appliedCoupons,
            'applied_coupon_codes' => $appliedCodes,
            'coupon_breakdown' => $couponBreakdown,
            'discount_total' => min($subtotal, $discountTotal),
            'invalid_coupon_messages' => $invalidCouponMessages,
            'recommendation_message' => count($appliedCodes) > 1
                ? 'Best combination applied: '.implode(' + ', $appliedCodes).'.'
                : 'Best coupon applied: '.$appliedCodes[0].'.',
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return Collection<int, Product>
     */
    private function loadProductsById(array $items): Collection
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->with(['categories:id', 'tags:id'])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  Collection<int, Product>  $productsById
     */
    private function eligibleSubtotalForCoupon(Coupon $coupon, array $items, Collection $productsById): float
    {
        // When no items provided (backward compatibility for summarizeFromSubtotal calls without items),
        // we don't have scope information, so we can't filter - this should have been handled by caller
        // Returning 0 signals that scope filtering couldn't be applied, which will fail validation
        // unless the caller passes the full subtotal
        if (empty($items)) {
            return 0.0;
        }

        if ($coupon->scope_type === null || $coupon->scope_type === Coupon::SCOPE_ALL || $coupon->scope_id === null) {
            return (float) collect($items)->sum(fn (array $item): float => $this->itemSubtotal($item));
        }

        $scopeType = $coupon->scope_type;
        $scopeId = (int) $coupon->scope_id;

        return (float) collect($items)->sum(function (array $item) use ($productsById, $scopeType, $scopeId): float {
            $product = $productsById->get((int) ($item['product_id'] ?? 0));

            if (! $product instanceof Product) {
                return 0.0;
            }

            $isApplicable = match ($scopeType) {
                Coupon::SCOPE_PRODUCT => $product->id === $scopeId,
                Coupon::SCOPE_CATEGORY => $product->categories->contains('id', $scopeId),
                Coupon::SCOPE_TAG => $product->tags->contains('id', $scopeId),
                default => false,
            };

            return $isApplicable ? $this->itemSubtotal($item) : 0.0;
        });
    }

    /**
     * @param  Collection<int, array{coupon: Coupon, discount: float}>  $eligibleCoupons
     * @return Collection<int, array{coupon: Coupon, discount: float}>
     */
    private function bestCouponCombination(Collection $eligibleCoupons, float $subtotal): Collection
    {
        $candidates = $eligibleCoupons->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $bestExclusive = $candidates
            ->filter(fn (array $entry): bool => ! $entry['coupon']->is_stackable)
            ->sortByDesc('discount')
            ->first();

        $stackableCandidates = $candidates
            ->filter(fn (array $entry): bool => $entry['coupon']->is_stackable)
            ->values();

        $stackableSelection = collect();

        $stackableCandidates
            ->groupBy(fn (array $entry): string => trim((string) $entry['coupon']->stack_group))
            ->each(function (Collection $groupEntries, string $group) use (&$stackableSelection): void {
                if ($group === '') {
                    $stackableSelection = $stackableSelection->merge($groupEntries->values());

                    return;
                }

                $bestInGroup = $groupEntries->sortByDesc('discount')->first();

                if ($bestInGroup !== null) {
                    $stackableSelection->push($bestInGroup);
                }
            });

        $stackableDiscount = min($subtotal, (float) $stackableSelection->sum('discount'));
        $exclusiveDiscount = $bestExclusive !== null ? min($subtotal, (float) $bestExclusive['discount']) : 0.0;

        if ($exclusiveDiscount > $stackableDiscount) {
            return $bestExclusive !== null ? collect([$bestExclusive]) : collect();
        }

        return $stackableSelection->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemSubtotal(array $item): float
    {
        $unitPrice = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);

        return max(0, $unitPrice) * max(0, $quantity);
    }
}
