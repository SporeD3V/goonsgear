<?php

namespace App\Http\Controllers;

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
            ->get();

        $availableTags = Tag::query()
            ->where('is_active', true)
            ->whereNotIn('id', $tagFollows->pluck('tag_id'))
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return view('account.index', [
            'tagFollows' => $tagFollows,
            'availableTags' => $availableTags,
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
}
