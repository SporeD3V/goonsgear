<?php

namespace App\Http\Controllers;

use App\Models\SizeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SizeProfileController extends Controller
{
    private const VALIDATION_RULES = [
        'name' => ['required', 'string', 'max:100'],
        'is_self' => ['sometimes', 'boolean'],
        'top_size' => ['nullable', 'string', 'max:20'],
        'bottom_size' => ['nullable', 'string', 'max:20'],
        'shoe_size' => ['nullable', 'string', 'max:20'],
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(self::VALIDATION_RULES);

        $user = $request->user();
        $isSelf = $request->boolean('is_self');

        // Only one self-profile per user
        if ($isSelf && $user->sizeProfiles()->where('is_self', true)->exists()) {
            return redirect()
                ->route('account.index')
                ->withErrors(['is_self' => 'You already have a personal size profile.']);
        }

        $user->sizeProfiles()->create([
            'name' => $validated['name'],
            'is_self' => $isSelf,
            'top_size' => $validated['top_size'] ?? null,
            'bottom_size' => $validated['bottom_size'] ?? null,
            'shoe_size' => $validated['shoe_size'] ?? null,
        ]);

        $redirect = $request->string('_redirect')->trim()->toString();

        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect($redirect)->with('status', 'Size profile saved.');
        }

        return redirect()
            ->route('account.index')
            ->with('status', 'Size profile saved.');
    }

    public function update(Request $request, SizeProfile $sizeProfile): RedirectResponse
    {
        $this->authorize('update', $sizeProfile);

        $validated = $request->validate(self::VALIDATION_RULES);

        $sizeProfile->update([
            'name' => $validated['name'],
            'top_size' => $validated['top_size'] ?? null,
            'bottom_size' => $validated['bottom_size'] ?? null,
            'shoe_size' => $validated['shoe_size'] ?? null,
        ]);

        $redirect = $request->string('_redirect')->trim()->toString();

        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect($redirect)->with('status', 'Size profile updated.');
        }

        return redirect()
            ->route('account.index')
            ->with('status', 'Size profile updated.');
    }

    public function destroy(Request $request, SizeProfile $sizeProfile): RedirectResponse
    {
        $this->authorize('delete', $sizeProfile);

        $sizeProfile->delete();

        return redirect()
            ->route('account.index')
            ->with('status', 'Size profile removed.');
    }
}
