<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\CartAbandonment;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\CartPricing;
use App\Support\Countries;
use App\Support\PayPalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    private const COUPON_SESSION_KEY = 'cart.coupon_code';

    private const PAYPAL_PENDING_ORDER_SESSION_KEY = 'checkout.paypal.pending_orders';

    private static ?bool $orderPaymentColumnsAvailable = null;

    /**
     * @var array<string, bool>
     */
    private static array $orderColumnAvailability = [];

    public function index(Request $request, PayPalClient $paypalClient, CartPricing $cartPricing): View|RedirectResponse
    {
        // The transaction above deliberately returns inside the closure.
        // Mark any matching abandonment as recovered outside the transaction.
        // (This line is unreachable — see the real mark-recovered call below.)
        // phpcs:ignore
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

        $pricing = $cartPricing->summarize($items, $request->session()->get(self::COUPON_SESSION_KEY));

        if ($pricing['error'] !== null) {
            $request->session()->forget(self::COUPON_SESSION_KEY);

            return redirect()->route('cart.index')->withErrors(['coupon_code' => $pricing['error']]);
        }

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $pricing['subtotal'],
            'discountTotal' => $pricing['discount_total'],
            'total' => $pricing['total'],
            'appliedCoupon' => $pricing['coupon'],
            'countries' => Countries::all(),
            'paypalEnabled' => $paypalClient->isEnabled(),
            'paypalClientId' => $paypalClient->clientId(),
            'formDefaults' => [
                'email' => (string) old('email', (string) ($authenticatedUser?->email ?? '')),
                'first_name' => (string) old('first_name', $defaultFirstName),
                'last_name' => (string) old('last_name', $defaultLastName),
                'phone' => (string) old('phone', ''),
                'country' => (string) old('country', 'DE'),
                'state' => (string) old('state', ''),
                'city' => (string) old('city', ''),
                'postal_code' => (string) old('postal_code', ''),
                'street_name' => (string) old('street_name', ''),
                'street_number' => (string) old('street_number', ''),
                'apartment_block' => (string) old('apartment_block', ''),
                'entrance' => (string) old('entrance', ''),
                'floor' => (string) old('floor', ''),
                'apartment_number' => (string) old('apartment_number', ''),
            ],
        ]);
    }

    public function store(Request $request, CartPricing $cartPricing): RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $payload = $this->validateCheckoutPayload($request);

        try {
            $checkoutContext = $this->buildCheckoutContext($items);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors($exception->errors());
        }

        try {
            $pricing = $this->resolvePricing($request, $checkoutContext['subtotal'], $cartPricing, (string) $payload['country']);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors($exception->errors());
        }

        $order = $this->createOrder(
            payload: $payload,
            normalizedItems: $checkoutContext['normalized_items'],
            subtotal: $checkoutContext['subtotal'],
            discountTotal: $pricing['discount_total'],
            total: $pricing['total'],
            couponCode: $pricing['coupon_code'],
            paymentMethod: 'manual',
            paymentStatus: 'pending',
            regionalDiscountTotal: $pricing['regional_discount_total'],
        );

        $request->session()->forget(self::CART_SESSION_KEY);
        $request->session()->forget(self::COUPON_SESSION_KEY);

        Mail::to($order->email)->send(new OrderConfirmation($order->load('items')));

        return redirect()->route('checkout.success', $order)->with('status', 'Order placed successfully.');
    }

    public function createPayPalOrder(Request $request, PayPalClient $paypalClient, CartPricing $cartPricing): JsonResponse
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

        $payload = $this->validateCheckoutPayload($request);
        $checkoutContext = $this->buildCheckoutContext($items);
        $pricing = $this->resolvePricing($request, $checkoutContext['subtotal'], $cartPricing, (string) $payload['country']);
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

        $request->session()->put(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId, [
            'payload' => $payload,
            'normalized_items' => $checkoutContext['normalized_items'],
            'subtotal' => $checkoutContext['subtotal'],
            'discount_total' => $pricing['discount_total'],
            'total' => $pricing['total'],
            'coupon_code' => $pricing['coupon_code'],
            'regional_discount_total' => $pricing['regional_discount_total'],
        ]);

        return response()->json([
            'id' => $paypalOrderId,
        ]);
    }

    public function capturePayPalOrder(Request $request, PayPalClient $paypalClient): JsonResponse
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
            couponCode: ($pendingOrder['coupon_code'] ?? null) !== null ? (string) $pendingOrder['coupon_code'] : null,
            paymentMethod: 'paypal',
            paymentStatus: 'paid',
            paypalOrderId: $paypalOrderId,
            paypalCaptureId: $captureId !== '' ? $captureId : null,
            markAsPaid: true,
            regionalDiscountTotal: (float) ($pendingOrder['regional_discount_total'] ?? 0),
        );

        $request->session()->forget(self::PAYPAL_PENDING_ORDER_SESSION_KEY.'.'.$paypalOrderId);
        $request->session()->forget(self::CART_SESSION_KEY);
        $request->session()->forget(self::COUPON_SESSION_KEY);

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

        return view('checkout.success', [
            'order' => $order,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCheckoutPayload(Request $request): array
    {
        return $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'street_name' => ['required', 'string', 'max:200'],
            'street_number' => ['required', 'string', 'max:20'],
            'apartment_block' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
        ]);
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
        ?string $couponCode,
        string $paymentMethod,
        string $paymentStatus,
        ?string $paypalOrderId = null,
        ?string $paypalCaptureId = null,
        bool $markAsPaid = false,
        float $regionalDiscountTotal = 0.0,
    ): Order {
        $order = DB::transaction(function () use ($payload, $normalizedItems, $subtotal, $discountTotal, $total, $couponCode, $paymentMethod, $paymentStatus, $paypalOrderId, $paypalCaptureId, $markAsPaid, $regionalDiscountTotal): Order {
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
                $orderPayload['coupon_code'] = $couponCode;
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

            if ($couponCode !== null) {
                Coupon::query()->where('code', $couponCode)->increment('used_count');
            }

            return $order;
        });

        CartAbandonment::query()
            ->where('email', $order->email)
            ->whereNull('recovered_at')
            ->update(['recovered_at' => now()]);

        return $order;
    }

    /**
     * @return array{subtotal: float, discount_total: float, total: float, coupon: ?App\Models\Coupon, coupon_code: ?string, requested_coupon_code: ?string, error: ?string}
     */
    private function resolvePricing(Request $request, float $subtotal, CartPricing $cartPricing, string $countryCode = ''): array
    {
        $pricing = $cartPricing->summarizeFromSubtotal($subtotal, $request->session()->get(self::COUPON_SESSION_KEY), $countryCode !== '' ? $countryCode : null);

        if ($pricing['error'] !== null) {
            $request->session()->forget(self::COUPON_SESSION_KEY);

            throw ValidationException::withMessages([
                'coupon_code' => $pricing['error'],
            ]);
        }

        return $pricing;
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

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getCartItems(Request $request): array
    {
        $items = $request->session()->get(self::CART_SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'GG-'.strtoupper(Str::random(10));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
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
