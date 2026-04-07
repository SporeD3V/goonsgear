<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartAbandonment;
use App\Models\Coupon;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\UserCartItem;
use App\Support\CartPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CartController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    private const COUPON_SESSION_KEY = 'cart.coupon_codes';

    private const LEGACY_COUPON_SESSION_KEY = 'cart.coupon_code';

    public function index(Request $request, CartPricing $cartPricing): View
    {
        $this->restoreCartFromDb($request);

        $cartItems = $this->getCartItems($request);
        $selectedCouponCodes = $this->getSelectedCouponCodes($request);
        $pricing = $cartPricing->summarize($cartItems, $selectedCouponCodes, null, $request->user());

        if ($pricing['error'] !== null) {
            $this->forgetCouponSession($request);
        }

        $availableCoupons = $this->availableCouponsForUser($request);

        return view('cart.index', [
            'items' => $cartItems,
            'subtotal' => $pricing['subtotal'],
            'discountTotal' => $pricing['discount_total'],
            'bundleDiscountTotal' => $pricing['bundle_discount_total'],
            'total' => $pricing['total'],
            'appliedCoupon' => $pricing['coupon'],
            'appliedCoupons' => $pricing['coupons'],
            'appliedBundle' => $pricing['bundle_discount'],
            'couponCode' => $pricing['requested_coupon_code'],
            'selectedCouponCodes' => $selectedCouponCodes,
            'availableCoupons' => $availableCoupons,
            'invalidCouponMessages' => $pricing['invalid_coupon_messages'],
            'recommendationMessage' => $pricing['recommendation_message'],
            'couponError' => $pricing['error'],
        ]);
    }

    public function applyCoupon(Request $request, CartPricing $cartPricing): RedirectResponse
    {
        $payload = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        $cartItems = $this->getCartItems($request);

        if ($cartItems === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $selectedCouponCodes = $this->getSelectedCouponCodes($request);
        $selectedCouponCodes[] = strtoupper(trim($payload['coupon_code']));
        $selectedCouponCodes = array_values(array_unique(array_filter($selectedCouponCodes)));

        $pricing = $cartPricing->summarize($cartItems, $selectedCouponCodes, null, $request->user());

        if ($pricing['error'] !== null || $pricing['coupon_codes'] === []) {
            return redirect()
                ->route('cart.index')
                ->withErrors(['coupon_code' => $pricing['error'] ?? 'Unable to apply coupon.'])
                ->withInput();
        }

        $this->storeSelectedCouponCodes($request, $selectedCouponCodes);

        return redirect()->route('cart.index')->with('status', 'Coupon selection updated.');
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $codeToRemove = strtoupper(trim((string) $request->input('coupon_code', '')));

        if ($codeToRemove === '') {
            $this->forgetCouponSession($request);

            return redirect()->route('cart.index')->with('status', 'All coupons removed.');
        }

        $remainingCodes = collect($this->getSelectedCouponCodes($request))
            ->reject(fn (string $code): bool => $code === $codeToRemove)
            ->values()
            ->all();

        $this->storeSelectedCouponCodes($request, $remainingCodes);

        return redirect()->route('cart.index')->with('status', 'Coupon removed.');
    }

    public function selectCoupons(Request $request, CartPricing $cartPricing): RedirectResponse
    {
        $payload = $request->validate([
            'coupon_codes' => ['nullable', 'array'],
            'coupon_codes.*' => ['string', 'max:50'],
        ]);

        $cartItems = $this->getCartItems($request);

        if ($cartItems === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $selectedCouponCodes = collect($payload['coupon_codes'] ?? [])
            ->map(fn (string $code): string => strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($selectedCouponCodes === []) {
            $this->forgetCouponSession($request);

            return redirect()->route('cart.index')->with('status', 'Coupon selection cleared.');
        }

        $pricing = $cartPricing->summarize($cartItems, $selectedCouponCodes, null, $request->user());

        if ($pricing['error'] !== null) {
            return redirect()->route('cart.index')->withErrors(['coupon_code' => $pricing['error']]);
        }

        $this->storeSelectedCouponCodes($request, $selectedCouponCodes);

        return redirect()->route('cart.index')->with('status', 'Coupon selection updated.');
    }

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $variant = ProductVariant::query()
            ->with([
                'product:id,name,slug,status',
                'product.media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->findOrFail($payload['variant_id']);

        if (! $variant->is_active || $variant->product?->status !== 'active') {
            return back()->withErrors(['cart' => 'This variant is not available for purchase.']);
        }

        $cartItems = $this->getCartItems($request);
        $existingItem = $cartItems[$variant->id] ?? null;
        $requestedQuantity = (int) $payload['quantity'];
        $nextQuantity = (int) ($existingItem['quantity'] ?? 0) + $requestedQuantity;

        $maxQuantity = $this->getMaxAllowedQuantity($variant);

        if ($maxQuantity !== null && $nextQuantity > $maxQuantity) {
            return back()->withErrors([
                'cart' => "Only {$maxQuantity} unit(s) are currently available for {$variant->name}.",
            ]);
        }

        $primaryMedia = $variant->product?->media->first();

        $cartItems[$variant->id] = array_merge(
            $this->buildCartItemData($variant),
            ['quantity' => $nextQuantity],
        );

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);
        $this->syncCartItemToDb($request, $variant->id, $nextQuantity);

        return back()->with('status', 'Added item to cart.');
    }

    public function update(UpdateCartItemRequest $request, ProductVariant $variant): RedirectResponse
    {
        $payload = $request->validated();

        $cartItems = $this->getCartItems($request);

        if (! isset($cartItems[$variant->id])) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Item not found in your cart.']);
        }

        $maxQuantity = $this->getMaxAllowedQuantity($variant);
        $nextQuantity = (int) $payload['quantity'];

        if ($maxQuantity !== null && $nextQuantity > $maxQuantity) {
            return redirect()->route('cart.index')->withErrors([
                'cart' => "Only {$maxQuantity} unit(s) are currently available for {$variant->name}.",
            ]);
        }

        $cartItems[$variant->id]['quantity'] = $nextQuantity;
        $cartItems[$variant->id]['max_quantity'] = $maxQuantity;

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);
        $this->syncCartItemToDb($request, $variant->id, $nextQuantity);

        return redirect()->route('cart.index')->with('status', 'Cart item updated.');
    }

    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $cartItems = $this->getCartItems($request);
        unset($cartItems[$variant->id]);

        $request->session()->put(self::CART_SESSION_KEY, $cartItems);
        $this->removeCartItemFromDb($request, $variant->id);

        return redirect()->route('cart.index')->with('status', 'Item removed from cart.');
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getCartItems(Request $request): array
    {
        $items = $request->session()->get(self::CART_SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function getMaxAllowedQuantity(ProductVariant $variant): ?int
    {
        if (! $variant->track_inventory || $variant->allow_backorder || $variant->is_preorder) {
            return null;
        }

        return max(0, (int) $variant->stock_quantity);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCartItemData(ProductVariant $variant): array
    {
        $primaryMedia = $variant->product?->media->first();
        $imagePath = $this->resolveCartImagePath($primaryMedia);

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => (string) $variant->product?->name,
            'product_slug' => (string) $variant->product?->slug,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'price' => (float) $variant->price,
            'max_quantity' => $this->getMaxAllowedQuantity($variant),
            'image' => $imagePath ? route('media.show', ['path' => $imagePath]) : null,
            'url' => $variant->product ? route('shop.show', $variant->product) : null,
        ];
    }

    private function resolveCartImagePath(?ProductMedia $media): ?string
    {
        if ($media === null) {
            return null;
        }

        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        return $media->getThumbnailPath();
    }

    private function syncCartItemToDb(Request $request, int $variantId, int $quantity): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        UserCartItem::query()->updateOrCreate(
            ['user_id' => $user->id, 'product_variant_id' => $variantId],
            ['quantity' => $quantity],
        );
    }

    private function removeCartItemFromDb(Request $request, int $variantId): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        UserCartItem::query()
            ->where('user_id', $user->id)
            ->where('product_variant_id', $variantId)
            ->delete();
    }

    private function restoreCartFromDb(Request $request): void
    {
        $user = $request->user();

        if ($user === null || $this->getCartItems($request) !== []) {
            return;
        }

        $dbItems = UserCartItem::query()
            ->where('user_id', $user->id)
            ->with([
                'variant.product:id,name,slug,status',
                'variant.product.media' => fn ($q) => $q
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->get();

        if ($dbItems->isEmpty()) {
            return;
        }

        $items = [];

        foreach ($dbItems as $dbItem) {
            $variant = $dbItem->variant;

            if ($variant === null || ! $variant->is_active || $variant->product?->status !== 'active') {
                continue;
            }

            $items[$variant->id] = array_merge(
                $this->buildCartItemData($variant),
                ['quantity' => $dbItem->quantity],
            );
        }

        if ($items !== []) {
            $request->session()->put(self::CART_SESSION_KEY, $items);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getSelectedCouponCodes(Request $request): array
    {
        $hasNewKey = $request->session()->has(self::COUPON_SESSION_KEY);
        $codes = $request->session()->get(self::COUPON_SESSION_KEY, []);

        if (is_string($codes)) {
            $codes = [$codes];
        }

        if (! is_array($codes) || (! $hasNewKey && $codes === [])) {
            $legacyCode = $request->session()->get(self::LEGACY_COUPON_SESSION_KEY);

            if (is_string($legacyCode) && trim($legacyCode) !== '') {
                return [strtoupper(trim($legacyCode))];
            }

            return [];
        }

        return collect($codes)
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $couponCodes
     */
    private function storeSelectedCouponCodes(Request $request, array $couponCodes): void
    {
        if ($couponCodes === []) {
            $this->forgetCouponSession($request);

            return;
        }

        $request->session()->put(self::COUPON_SESSION_KEY, $couponCodes);
        $request->session()->forget(self::LEGACY_COUPON_SESSION_KEY);
    }

    private function forgetCouponSession(Request $request): void
    {
        $request->session()->forget(self::COUPON_SESSION_KEY);
        $request->session()->forget(self::LEGACY_COUPON_SESSION_KEY);
    }

    private function availableCouponsForUser(Request $request)
    {
        $user = $request->user();

        if ($user === null) {
            return collect();
        }

        return $user->coupons()
            ->where('coupon_user.is_active', true)
            ->where('coupons.is_active', true)
            ->where(function ($query): void {
                $query->whereNull('coupons.starts_at')->orWhere('coupons.starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('coupons.ends_at')->orWhere('coupons.ends_at', '>=', now());
            })
            ->orderBy('coupons.code')
            ->get();
    }

    public function trackEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $items = $this->getCartItems($request);

        if (empty($items)) {
            return response()->json(['ok' => true]);
        }

        $existing = CartAbandonment::query()
            ->where('email', $data['email'])
            ->whereNull('recovered_at')
            ->latest()
            ->first();

        if ($existing instanceof CartAbandonment) {
            $existing->update([
                'cart_data' => $items,
                'abandoned_at' => now(),
                'reminder_sent_at' => null,
                'token' => Str::uuid()->toString(),
            ]);
        } else {
            CartAbandonment::query()->create([
                'email' => $data['email'],
                'cart_data' => $items,
                'token' => Str::uuid()->toString(),
                'abandoned_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function recoverCart(Request $request, CartAbandonment $abandonment): RedirectResponse
    {
        if ($abandonment->recovered_at !== null) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'This recovery link has already been used.']);
        }

        if ($abandonment->abandoned_at < now()->subDays(7)) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'This recovery link has expired.']);
        }

        $request->session()->put(self::CART_SESSION_KEY, $abandonment->cart_data);

        $couponCode = strtoupper($request->string('coupon')->trim()->toString());

        if ($couponCode !== '' && Coupon::query()->where('code', $couponCode)->exists()) {
            $this->storeSelectedCouponCodes($request, [$couponCode]);
            $request->session()->put(self::LEGACY_COUPON_SESSION_KEY, $couponCode);
        }

        $abandonment->update(['recovered_at' => now()]);

        if ($request->user() !== null) {
            foreach ($abandonment->cart_data as $variantId => $item) {
                $this->syncCartItemToDb($request, (int) $variantId, (int) $item['quantity']);
            }
        }

        return redirect()->route('checkout.index')->with('status', 'Your cart has been restored. Please complete your order below.');
    }
}
