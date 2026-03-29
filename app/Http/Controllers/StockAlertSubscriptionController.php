<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAlertSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'subscribe_stock_alert' => ['accepted'],
        ]);

        $variant = ProductVariant::query()
            ->with('product:id,status')
            ->findOrFail((int) $payload['variant_id']);

        if (! $variant->is_active || $variant->product?->status !== 'active') {
            return back()->withErrors(['stock_alert' => 'This variant is not available for alerts.']);
        }

        $isOutOfStock = $variant->track_inventory
            && (int) $variant->stock_quantity <= 0
            && ! $variant->allow_backorder
            && ! $variant->is_preorder;

        if (! $isOutOfStock) {
            return back()->withErrors(['stock_alert' => 'This variant is currently available.']);
        }

        StockAlertSubscription::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'is_active' => true,
                'notified_at' => null,
            ],
        );

        return back()->with('status', 'You will be notified when this item is back in stock.');
    }
}
