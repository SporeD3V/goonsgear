<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\CreateOrderAction;
use App\Http\Requests\StoreCheckoutRequest;
use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CartPricing;
use App\Support\Countries;
use App\Support\PayPalClient;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use RuntimeException;

class CheckoutController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    private const COUPON_SESSION_KEY = 'cart.coupon_codes';

    private const LEGACY_COUPON_SESSION_KEY = 'cart.coupon_code';

    private const PAYPAL_PENDING_ORDER_SESSION_KEY = 'checkout.paypal.pending_orders';

    public function index(Request $request, PayPalClient $paypalClient, CartPricing $cartPricing, RecaptchaVerifier $recaptchaVerifier): View|RedirectResponse
    {
        $items = $this->getCartItems($request);
        $authenticatedUser = $request->user();

        $defaultName = trim((string) ($authenticatedUser?->name ?? ''));
        $defaultFirstName = $defaultName !== '' ? Str::of($defaultName)->before(' ')->toString() : '';
        $defaultLastName = $defaultName !== '' ? Str::of($defaultName)->after(' ')->toString() : '';

        if ($defaultLastName === $defaultName) {
            $defaultLastName = '';
        }

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $pricing = $cartPricing->summarize($items, $this->getSelectedCouponCodes($request), null, $request->user());

        if ($pricing['error'] !== null) {
            $this->forgetCouponSession($request);

            return redirect()->route('cart.index')->withErrors(['coupon_code' => $pricing['error']]);
        }

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $pricing['subtotal'],
            'discountTotal' => $pricing['discount_total'],
            'bundleDiscountTotal' => $pricing['bundle_discount_total'],
            'total' => $pricing['total'],
            'appliedCoupon' => $pricing['coupon'],
            'appliedCoupons' => $pricing['coupons'],
            'appliedBundle' => $pricing['bundle_discount'],
            'recommendationMessage' => $pricing['recommendation_message'],
            'countries' => Countries::all(),
            'paypalEnabled' => $paypalClient->isEnabled(),
            'paypalClientId' => $paypalClient->clientId(),
            'recaptchaEnabled' => $recaptchaVerifier->shouldChallenge('checkout', $request),
            'recaptchaSiteKey' => $recaptchaVerifier->siteKey(),
            'formDefaults' => [
                'email' => (string) old('email', (string) ($authenticatedUser?->email ?? '')),
                'first_name' => (string) old('first_name', $defaultFirstName),
                'last_name' => (string) old('last_name', $defaultLastName),
                'phone' => (string) old('phone', (string) ($authenticatedUser?->delivery_phone ?? '')),
                'country' => (string) old('country', (string) ($authenticatedUser?->delivery_country ?? 'DE')),
                'state' => (string) old('state', (string) ($authenticatedUser?->delivery_state ?? '')),
                'city' => (string) old('city', (string) ($authenticatedUser?->delivery_city ?? '')),
                'postal_code' => (string) old('postal_code', (string) ($authenticatedUser?->delivery_postal_code ?? '')),
                'street_name' => (string) old('street_name', (string) ($authenticatedUser?->delivery_street_name ?? '')),
                'street_number' => (string) old('street_number', (string) ($authenticatedUser?->delivery_street_number ?? '')),
                'apartment_block' => (string) old('apartment_block', (string) ($authenticatedUser?->delivery_apartment_block ?? '')),
                'entrance' => (string) old('entrance', (string) ($authenticatedUser?->delivery_entrance ?? '')),
                'floor' => (string) old('floor', (string) ($authenticatedUser?->delivery_floor ?? '')),
                'apartment_number' => (string) old('apartment_number', (string) ($authenticatedUser?->delivery_apartment_number ?? '')),
            ],
        ]);
    }

    public function store(StoreCheckoutRequest $request, CartPricing $cartPricing, RecaptchaVerifier $recaptchaVerifier): RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $email = strtolower($request->string('email')->trim()->toString());

        try {
            $payload = $this->validateCheckoutPayload($request, $recaptchaVerifier);
        } catch (ValidationException $exception) {
            $recaptchaVerifier->registerSignal('checkout', $request, $email);

            throw $exception;
        }

        try {
            $checkoutContext = $this->buildCheckoutContext($items);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors($exception->errors());
        }

        try {
            $pricing = $this->resolvePricing($request, $items, $cartPricing, (string) $payload['country']);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors($exception->errors());
        }

        $order = $this->createOrder(
            payload: $payload,
            normalizedItems: $checkoutContext['normalized_items'],
            subtotal: $checkoutContext['subtotal'],
            discountTotal: $pricing['discount_total'],
            total: $pricing['total'],
            couponCodes: $pricing['coupon_codes'],
            couponBreakdown: $pricing['coupon_breakdown'],
            paymentMethod: 'manual',
            paymentStatus: 'pending',
            regionalDiscountTotal: $pricing['regional_discount_total'],
            bundleDiscountTotal: $pricing['bundle_discount_total'],
            customer: $request->user(),
        );

        $request->session()->forget(self::CART_SESSION_KEY);
        $this->forgetCouponSession($request);
        $recaptchaVerifier->clearSignals('checkout', $request, $email);

        Mail::to($order->email)->send(new OrderConfirmation($order->load('items')));

        return redirect()->route('checkout.success', $order)->with('status', 'Order placed successfully.');
    }

    public function createPayPalOrder(StoreCheckoutRequest $request, PayPalClient $paypalClient, CartPricing $cartPricing, RecaptchaVerifier $recaptchaVerifier): JsonResponse
    {
        if (! $paypalClient->isEnabled()) {
            return response()->json([
                'message' => 'PayPal payments are not available right now.',
            ], 503);
        }

        $items = $this->getCartItems($request);

        if ($items === []) {
            return response()->json([
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $email = strtolower($request->string('email')->trim()->toString());

        try {
            $payload = $this->validateCheckoutPayload($request, $recaptchaVerifier);
        } catch (ValidationException $exception) {
            $recaptchaVerifier->registerSignal('checkout', $request, $email);

            throw $exception;
        }
        $checkoutContext = $this->buildCheckoutContext($items);
        $pricing = $this->resolvePricing($request, $items, $cartPricing, (string) $payload['country']);
        $amount = number_format((float) $pricing['total'], 2, '.', '');

        try {
            $paypalOrder = $paypalClient->createOrder($amount, 'EUR');
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Unable to initialize PayPal checkout right now.',
            ], 502);
        }

        $paypalOrderId = (string) ($paypalOrder['id'] ?? '');

        if ($paypalOrderId === '') {
            throw ValidationException::withMessages([
                'payment' => 'Unable to initialize PayPal order.',
            ]);
        }

        $pendingOrderData = [
            'payload' => $payload,
            'normalized_items' => $checkoutContext['normalized_items'],
            'subtotal' => $checkoutContext['subtotal'],
            'discount_total' => $pricing['discount_total'],
            'total' => $pricing['total'],
            'coupon_codes' => $pricing['coupon_codes'],
            'coupon_breakdown' => $pricing['coupon_breakdown'],
            'regional_discount_total' => $pricing['regional_discount_total'],
            'bundle_discount_total' => $pricing['bundle_discount_total'],
        ];

        $pendingOrderData['integrity_hash'] = $this->signPendingOrderData($pendingOrderData);

        $request->session()->put(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId, $pendingOrderData);

        return response()->json([
            'id' => $paypalOrderId,
        ]);
    }

    public function capturePayPalOrder(Request $request, PayPalClient $paypalClient, RecaptchaVerifier $recaptchaVerifier): JsonResponse
    {
        if (! $paypalClient->isEnabled()) {
            return response()->json([
                'message' => 'PayPal payments are not available right now.',
            ], 503);
        }

        $payload = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:100'],
        ]);

        $paypalOrderId = $payload['paypal_order_id'];
        $pendingOrder = $request->session()->get(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId);

        if (! is_array($pendingOrder)) {
            return response()->json([
                'message' => 'PayPal order session expired. Please try again.',
            ], 422);
        }

        if (! $this->hasValidPendingOrderSignature($pendingOrder)) {
            $request->session()->forget(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId);

            return response()->json([
                'message' => 'Checkout session integrity check failed. Please try again.',
            ], 422);
        }

        try {
            $captureResponse = $paypalClient->captureOrder($paypalOrderId);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Unable to capture PayPal payment right now.',
            ], 502);
        }
        $captureStatus = strtoupper((string) ($captureResponse['status'] ?? ''));

        if ($captureStatus !== 'COMPLETED') {
            return response()->json([
                'message' => 'PayPal payment was not completed.',
            ], 422);
        }

        $captureId = (string) Arr::get($captureResponse, 'purchase_units.0.payments.captures.0.id', '');

        $order = $this->createOrder(
            payload: (array) ($pendingOrder['payload'] ?? []),
            normalizedItems: (array) ($pendingOrder['normalized_items'] ?? []),
            subtotal: (float) ($pendingOrder['subtotal'] ?? 0),
            discountTotal: (float) ($pendingOrder['discount_total'] ?? 0),
            total: (float) ($pendingOrder['total'] ?? 0),
            couponCodes: is_array($pendingOrder['coupon_codes'] ?? null) ? $pendingOrder['coupon_codes'] : [],
            couponBreakdown: is_array($pendingOrder['coupon_breakdown'] ?? null) ? $pendingOrder['coupon_breakdown'] : [],
            paymentMethod: 'paypal',
            paymentStatus: 'paid',
            paypalOrderId: $paypalOrderId,
            paypalCaptureId: $captureId !== '' ? $captureId : null,
            markAsPaid: true,
            regionalDiscountTotal: (float) ($pendingOrder['regional_discount_total'] ?? 0),
            bundleDiscountTotal: (float) ($pendingOrder['bundle_discount_total'] ?? 0),
            customer: $request->user(),
        );

        $request->session()->forget(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId);
        $request->session()->forget(self::CART_SESSION_KEY);
        $this->forgetCouponSession($request);

        $email = strtolower((string) data_get($pendingOrder, 'payload.email', ''));
        $recaptchaVerifier->clearSignals('checkout', $request, $email);

        Mail::to($order->email)->send(new OrderConfirmation($order->load('items')));

        return response()->json([
            'redirect_url' => route('checkout.success', $order),
        ]);
    }

    public function success(Order $order): View
    {
        $order->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product.media' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        $order->setRelation('items', $order->items->map(function ($item) {
            $primaryMedia = $item->product?->media->first();

            if ($primaryMedia !== null) {
                $primaryMedia->setAttribute('thumbnail_path', $this->resolveCheckoutThumbnailPath($primaryMedia));
            }

            return $item;
        }));

        return view('checkout.success', [
            'order' => $order,
        ]);
    }

    private function resolveCheckoutThumbnailPath(ProductMedia $media): string
    {
        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        $disk = (string) ($media->disk ?: 'public');
        $thumbnailPath = $media->getThumbnailPath();
        $candidates = [$thumbnailPath];

        if (str_contains($thumbnailPath, '/fallback/')) {
            $galleryThumbnailPath = str_replace('/fallback/', '/gallery/', $thumbnailPath);
            array_unshift($candidates, $galleryThumbnailPath);
        }

        $candidates[] = $media->path;

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $media->path;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCheckoutPayload(StoreCheckoutRequest $request, RecaptchaVerifier $recaptchaVerifier): array
    {
        $email = strtolower($request->string('email')->trim()->toString());
        $requiresChallenge = $recaptchaVerifier->shouldChallenge('checkout', $request, $email);

        $payload = $request->validated();

        if ($requiresChallenge) {
            $recaptchaToken = (string) ($payload['recaptcha_token'] ?? '');

            if ($recaptchaToken === '') {
                $recaptchaVerifier->registerSignal('checkout', $request, $email);

                throw ValidationException::withMessages([
                    'recaptcha_token' => 'Security verification is required. Please try again.',
                ]);
            }

            if (! $recaptchaVerifier->verifyCheckoutToken($recaptchaToken, $request->ip())) {
                $recaptchaVerifier->registerSignal('checkout', $request, $email);

                throw ValidationException::withMessages([
                    'recaptcha_token' => 'Security verification failed. Please try again.',
                ]);
            }
        }

        unset($payload['recaptcha_token']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $pendingOrderData
     */
    private function signPendingOrderData(array $pendingOrderData): string
    {
        $payload = [
            'payload' => $pendingOrderData['payload'] ?? [],
            'normalized_items' => $pendingOrderData['normalized_items'] ?? [],
            'subtotal' => $pendingOrderData['subtotal'] ?? 0,
            'discount_total' => $pendingOrderData['discount_total'] ?? 0,
            'total' => $pendingOrderData['total'] ?? 0,
            'coupon_codes' => $pendingOrderData['coupon_codes'] ?? [],
            'coupon_breakdown' => $pendingOrderData['coupon_breakdown'] ?? [],
            'regional_discount_total' => $pendingOrderData['regional_discount_total'] ?? 0,
            'bundle_discount_total' => $pendingOrderData['bundle_discount_total'] ?? 0,
        ];

        try {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $encodedPayload = '{}';
        }

        return hash_hmac('sha256', $encodedPayload, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $pendingOrderData
     */
    private function hasValidPendingOrderSignature(array $pendingOrderData): bool
    {
        $providedHash = (string) ($pendingOrderData['integrity_hash'] ?? '');

        if ($providedHash === '') {
            return $this->isLegacyPendingOrderData($pendingOrderData);
        }

        return hash_equals($this->signPendingOrderData($pendingOrderData), $providedHash);
    }

    /**
     * @param  array<string, mixed>  $pendingOrderData
     */
    private function isLegacyPendingOrderData(array $pendingOrderData): bool
    {
        return is_array($pendingOrderData['payload'] ?? null)
            && is_array($pendingOrderData['normalized_items'] ?? null)
            && is_numeric($pendingOrderData['subtotal'] ?? null);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{normalized_items: array<int, array<string, mixed>>, subtotal: float}
     */
    private function buildCheckoutContext(array $items): array
    {
        $variantIds = collect($items)->pluck('variant_id')->filter()->map(fn ($id): int => (int) $id)->values();

        $variants = ProductVariant::query()
            ->with('product:id,status')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $normalizedItems = [];

        foreach ($items as $item) {
            $variantId = (int) Arr::get($item, 'variant_id');
            $quantity = (int) Arr::get($item, 'quantity', 0);
            $variant = $variants->get($variantId);

            if ($variant === null || ! $variant->is_active || $variant->product?->status !== 'active') {
                throw ValidationException::withMessages([
                    'cart' => 'One or more cart items are no longer available.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'cart' => 'Cart contains an invalid quantity.',
                ]);
            }

            if ($variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder && $quantity > $variant->stock_quantity) {
                throw ValidationException::withMessages([
                    'cart' => "Insufficient stock for {$variant->name}. Please update your cart quantity.",
                ]);
            }

            $unitPrice = (float) $variant->price;

            $normalizedItems[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => (string) Arr::get($item, 'product_name', ''),
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        return [
            'normalized_items' => $normalizedItems,
            'subtotal' => (float) collect($normalizedItems)->sum('line_total'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $normalizedItems
     */
    private function createOrder(
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
        return app(CreateOrderAction::class)->execute(
            payload: $payload,
            normalizedItems: $normalizedItems,
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            total: $total,
            couponCodes: $couponCodes,
            couponBreakdown: $couponBreakdown,
            paymentMethod: $paymentMethod,
            paymentStatus: $paymentStatus,
            paypalOrderId: $paypalOrderId,
            paypalCaptureId: $paypalCaptureId,
            markAsPaid: $markAsPaid,
            regionalDiscountTotal: $regionalDiscountTotal,
            bundleDiscountTotal: $bundleDiscountTotal,
            customer: $customer,
        );
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{subtotal: float, discount_total: float, regional_discount_total: float, bundle_discount_total: float, total: float, coupon: ?App\Models\Coupon, coupons: Collection<int, App\Models\Coupon>, coupon_code: ?string, coupon_codes: array<int, string>, coupon_breakdown: array<string, float>, requested_coupon_code: ?string, requested_coupon_codes: array<int, string>, invalid_coupon_messages: array<string, string>, recommendation_message: ?string, error: ?string, regional_discount: ?App\Models\RegionalDiscount, bundle_discount: ?App\Models\BundleDiscount}
     */
    private function resolvePricing(Request $request, array $items, CartPricing $cartPricing, string $countryCode = ''): array
    {
        $pricing = $cartPricing->summarize($items, $this->getSelectedCouponCodes($request), $countryCode !== '' ? $countryCode : null, $request->user());

        if ($pricing['error'] !== null) {
            $this->forgetCouponSession($request);

            throw ValidationException::withMessages([
                'coupon_code' => $pricing['error'],
            ]);
        }

        return $pricing;
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

    private function forgetCouponSession(Request $request): void
    {
        $request->session()->forget(self::COUPON_SESSION_KEY);
        $request->session()->forget(self::LEGACY_COUPON_SESSION_KEY);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getCartItems(Request $request): array
    {
        $items = $request->session()->get(self::CART_SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @return array<string, string>
     */
    private function getCountries(): array
    {
        return [
            'AD' => 'Andorra',
            'AE' => 'United Arab Emirates',
            'AF' => 'Afghanistan',
            'AG' => 'Antigua & Barbuda',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AO' => 'Angola',
            'AR' => 'Argentina',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AZ' => 'Azerbaijan',
            'BA' => 'Bosnia & Herzegovina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BF' => 'Burkina Faso',
            'BG' => 'Bulgaria',
            'BH' => 'Bahrain',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BN' => 'Brunei',
            'BO' => 'Bolivia',
            'BR' => 'Brazil',
            'BS' => 'Bahamas',
            'BT' => 'Bhutan',
            'BW' => 'Botswana',
            'BY' => 'Belarus',
            'BZ' => 'Belize',
            'CA' => 'Canada',
            'CD' => 'DR Congo',
            'CF' => 'Central African Republic',
            'CG' => 'Congo',
            'CH' => 'Switzerland',
            'CI' => "Côte d'Ivoire",
            'CL' => 'Chile',
            'CM' => 'Cameroon',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'CV' => 'Cape Verde',
            'CY' => 'Cyprus',
            'CZ' => 'Czechia',
            'DE' => 'Germany',
            'DJ' => 'Djibouti',
            'DK' => 'Denmark',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'DZ' => 'Algeria',
            'EC' => 'Ecuador',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'ER' => 'Eritrea',
            'ES' => 'Spain',
            'ET' => 'Ethiopia',
            'FI' => 'Finland',
            'FJ' => 'Fiji',
            'FM' => 'Micronesia',
            'FR' => 'France',
            'GA' => 'Gabon',
            'GB' => 'United Kingdom',
            'GD' => 'Grenada',
            'GE' => 'Georgia',
            'GH' => 'Ghana',
            'GM' => 'Gambia',
            'GN' => 'Guinea',
            'GQ' => 'Equatorial Guinea',
            'GR' => 'Greece',
            'GT' => 'Guatemala',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HN' => 'Honduras',
            'HR' => 'Croatia',
            'HT' => 'Haiti',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IN' => 'India',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'IS' => 'Iceland',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JO' => 'Jordan',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KG' => 'Kyrgyzstan',
            'KH' => 'Cambodia',
            'KI' => 'Kiribati',
            'KM' => 'Comoros',
            'KN' => 'St Kitts & Nevis',
            'KR' => 'South Korea',
            'KW' => 'Kuwait',
            'KZ' => 'Kazakhstan',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LC' => 'St Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LV' => 'Latvia',
            'LY' => 'Libya',
            'MA' => 'Morocco',
            'MC' => 'Monaco',
            'MD' => 'Moldova',
            'ME' => 'Montenegro',
            'MG' => 'Madagascar',
            'MH' => 'Marshall Islands',
            'MK' => 'North Macedonia',
            'ML' => 'Mali',
            'MM' => 'Myanmar',
            'MN' => 'Mongolia',
            'MR' => 'Mauritania',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Maldives',
            'MW' => 'Malawi',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NI' => 'Nicaragua',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NZ' => 'New Zealand',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PG' => 'Papua New Guinea',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PS' => 'Palestine',
            'PT' => 'Portugal',
            'PW' => 'Palau',
            'PY' => 'Paraguay',
            'QA' => 'Qatar',
            'RO' => 'Romania',
            'RS' => 'Serbia',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia',
            'SB' => 'Solomon Islands',
            'SC' => 'Seychelles',
            'SD' => 'Sudan',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SI' => 'Slovenia',
            'SK' => 'Slovakia',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Suriname',
            'SS' => 'South Sudan',
            'ST' => 'São Tomé & Príncipe',
            'SV' => 'El Salvador',
            'SY' => 'Syria',
            'SZ' => 'Eswatini',
            'TD' => 'Chad',
            'TG' => 'Togo',
            'TH' => 'Thailand',
            'TJ' => 'Tajikistan',
            'TL' => 'Timor-Leste',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunisia',
            'TO' => 'Tonga',
            'TR' => 'Turkey',
            'TT' => 'Trinidad & Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Taiwan',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraine',
            'UG' => 'Uganda',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VA' => 'Vatican City',
            'VC' => 'St Vincent & Grenadines',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'VU' => 'Vanuatu',
            'WS' => 'Samoa',
            'YE' => 'Yemen',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }
}
