<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const CART_SESSION_KEY = 'cart.items';

    public function index(Request $request): View|RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity']);

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'countries' => $this->getCountries(),
            'formDefaults' => [
                'email' => (string) old('email', ''),
                'first_name' => (string) old('first_name', ''),
                'last_name' => (string) old('last_name', ''),
                'phone' => (string) old('phone', ''),
                'country' => (string) old('country', 'DE'),
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

    public function store(Request $request): RedirectResponse
    {
        $items = $this->getCartItems($request);

        if ($items === []) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $payload = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'street_name' => ['required', 'string', 'max:200'],
            'street_number' => ['required', 'string', 'max:20'],
            'apartment_block' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
        ]);

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
                return redirect()->route('cart.index')->withErrors(['cart' => 'One or more cart items are no longer available.']);
            }

            if ($quantity < 1) {
                return redirect()->route('cart.index')->withErrors(['cart' => 'Cart contains an invalid quantity.']);
            }

            if ($variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder && $quantity > $variant->stock_quantity) {
                return redirect()->route('cart.index')->withErrors([
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

        $subtotal = collect($normalizedItems)->sum('line_total');

        $order = DB::transaction(function () use ($payload, $normalizedItems, $variants, $subtotal) {
            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'email' => $payload['email'],
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'phone' => $payload['phone'] ?? null,
                'country' => strtoupper($payload['country']),
                'city' => $payload['city'],
                'postal_code' => $payload['postal_code'],
                'street_name' => $payload['street_name'],
                'street_number' => $payload['street_number'],
                'apartment_block' => $payload['apartment_block'] ?? null,
                'entrance' => $payload['entrance'] ?? null,
                'floor' => $payload['floor'] ?? null,
                'apartment_number' => $payload['apartment_number'] ?? null,
                'currency' => 'EUR',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'placed_at' => now(),
            ]);

            $order->items()->createMany($normalizedItems);

            foreach ($normalizedItems as $item) {
                $variant = $variants->get((int) $item['product_variant_id']);

                if ($variant !== null && $variant->track_inventory && ! $variant->allow_backorder && ! $variant->is_preorder) {
                    $variant->decrement('stock_quantity', (int) $item['quantity']);
                }
            }

            return $order;
        });

        $request->session()->forget(self::CART_SESSION_KEY);

        return redirect()->route('checkout.success', $order)->with('status', 'Order placed successfully.');
    }

    public function success(Order $order): View
    {
        $order->load('items');

        return view('checkout.success', [
            'order' => $order,
        ]);
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
