<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAlertSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', StockAlertSubscription::class);

        $user = $request->user();

        $rules = [
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        ];

        if ($user) {
            $rules['subscribe_stock_alert'] = ['accepted'];
        } else {
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $payload = $request->validate($rules);

        $variant = ProductVariant::query()
            ->with('product:id,status')
            ->findOrFail((int) $payload['variant_id']);

        if (! $variant->is_active || $variant->product?->status !== 'active') {
            return $this->respond($request, false, 'This variant is not available for alerts.');
        }

        $isOutOfStock = $variant->track_inventory
            && (int) $variant->stock_quantity <= 0
            && ! $variant->allow_backorder
            && ! $variant->is_preorder;

        if (! $isOutOfStock) {
            return $this->respond($request, false, 'This variant is currently available.');
        }

        $matchAttributes = [
            'product_variant_id' => $variant->id,
        ];

        if ($user) {
            $matchAttributes['user_id'] = $user->id;
        } else {
            $matchAttributes['email'] = $payload['email'];
        }

        StockAlertSubscription::query()->updateOrCreate(
            $matchAttributes,
            [
                'user_id' => $user?->id,
                'email' => $user ? $user->email : $payload['email'],
                'is_active' => true,
                'notified_at' => null,
            ],
        );

        return $this->respond($request, true, 'You will be notified when this item is back in stock.');
    }

    private function respond(Request $request, bool $success, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return $success
                ? response()->json(['message' => $message])
                : response()->json(['message' => $message], 422);
        }

        return $success
            ? back()->with('status', $message)
            : back()->withErrors(['stock_alert' => $message]);
    }
}
