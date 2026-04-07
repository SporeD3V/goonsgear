<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Tag;
use App\Models\TagFollow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $tagFollows = TagFollow::query()
            ->where('user_id', $user->id)
            ->with('tag:id,name,slug,type,is_active')
            ->orderByDesc('id')
            ->paginate((int) config('pagination.account_tag_follows_per_page', 25))
            ->withQueryString();

        $availableTags = Tag::query()
            ->where('is_active', true)
            ->whereNotIn('id', TagFollow::query()->where('user_id', $user->id)->select('tag_id'))
            ->orderBy('type')
            ->orderBy('name')
            ->limit((int) config('pagination.account_available_tags_limit', 500))
            ->get(['id', 'name', 'type']);

        $recentOrders = Order::query()
            ->where('email', $user->email)
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'order_number',
                'status',
                'payment_status',
                'currency',
                'total',
                'placed_at',
            ]);

        $availableCoupons = $user->coupons()
            ->where('coupon_user.is_active', true)
            ->where('coupons.is_active', true)
            ->where(function ($query): void {
                $query->whereNull('coupons.starts_at')->orWhere('coupons.starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('coupons.ends_at')->orWhere('coupons.ends_at', '>=', now());
            })
            ->orderBy('coupons.code')
            ->get(['coupons.id', 'coupons.code', 'coupons.type', 'coupons.value', 'coupons.is_stackable', 'coupons.stack_group', 'coupons.scope_type']);

        $sizeProfiles = $user->sizeProfiles()
            ->orderByDesc('is_self')
            ->orderBy('name')
            ->get();

        return view('account.index', [
            'tagFollows' => $tagFollows,
            'availableTags' => $availableTags,
            'recentOrders' => $recentOrders,
            'availableCoupons' => $availableCoupons,
            'sizeProfiles' => $sizeProfiles,
        ]);
    }

    public function updateEmailPreferences(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'notify_cart_discounts' => ['sometimes', 'boolean'],
            'notify_cart_low_stock' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            'notify_cart_discounts' => $payload['notify_cart_discounts'] ?? false,
            'notify_cart_low_stock' => $payload['notify_cart_low_stock'] ?? false,
        ]);

        return redirect()->route('account.index')->with('status', 'Email preferences updated.');
    }

    public function updateDeliveryAddress(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'delivery_phone' => ['nullable', 'string', 'max:40'],
            'delivery_country' => ['nullable', 'string', 'size:2'],
            'delivery_state' => ['nullable', 'string', 'max:120'],
            'delivery_city' => ['nullable', 'string', 'max:120'],
            'delivery_postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_street_name' => ['nullable', 'string', 'max:200'],
            'delivery_street_number' => ['nullable', 'string', 'max:20'],
            'delivery_apartment_block' => ['nullable', 'string', 'max:50'],
            'delivery_entrance' => ['nullable', 'string', 'max:50'],
            'delivery_floor' => ['nullable', 'string', 'max:20'],
            'delivery_apartment_number' => ['nullable', 'string', 'max:20'],
        ]);

        if (isset($payload['delivery_country'])) {
            $payload['delivery_country'] = strtoupper((string) $payload['delivery_country']);
        }

        $request->user()->update($payload);

        return redirect()->route('account.index')->with('status', 'Delivery address saved.');
    }
}
