<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegionalDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalDiscountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        $subtotal = (float) $request->query('subtotal', 0);

        if ($country === '') {
            return response()->json(['discount_total' => null]);
        }

        $discount = RegionalDiscount::findForCountry($country);

        if ($discount === null) {
            return response()->json(['discount_total' => null]);
        }

        return response()->json([
            'discount_total' => $discount->discountFor($subtotal),
            'reason' => $discount->reason,
            'type' => $discount->discount_type,
            'value' => $discount->discount_value,
        ]);
    }
}
