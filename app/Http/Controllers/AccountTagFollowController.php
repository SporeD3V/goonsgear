<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTagFollowPreferencesRequest;
use App\Http\Requests\UpsertTagFollowRequest;
use App\Models\TagFollow;
use Illuminate\Http\RedirectResponse;

class AccountTagFollowController extends Controller
{
    public function store(UpsertTagFollowRequest $request): RedirectResponse
    {
        $this->authorize('create', TagFollow::class);

        $validated = $request->validated();

        TagFollow::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'tag_id' => $validated['tag_id'],
            ],
            [
                'notify_new_drops' => $request->boolean('notify_new_drops'),
                'notify_discounts' => $request->boolean('notify_discounts'),
            ]
        );

        return redirect()
            ->route('account.index')
            ->with('status', 'Tag follow preferences updated.');
    }

    public function update(UpdateTagFollowPreferencesRequest $request, TagFollow $tagFollow): RedirectResponse
    {
        $this->authorize('update', $tagFollow);

        $tagFollow->update([
            'notify_new_drops' => $request->boolean('notify_new_drops'),
            'notify_discounts' => $request->boolean('notify_discounts'),
        ]);

        return redirect()
            ->route('account.index')
            ->with('status', 'Artist/brand notification settings saved.');
    }

    public function destroy(TagFollow $tagFollow): RedirectResponse
    {
        $this->authorize('delete', $tagFollow);

        $tagFollow->delete();

        return redirect()
            ->route('account.index')
            ->with('status', 'Artist/brand removed from favorites.');
    }
}
