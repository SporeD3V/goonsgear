<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('account.index');
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
}
