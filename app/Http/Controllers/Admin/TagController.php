<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        $tags = Tag::query()
            ->withCount([
                'followers as followers_count',
                'products as active_products_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->orderBy('type')
            ->orderBy('name')
            ->paginate((int) config('pagination.admin_tag_per_page', 30));

        return view('admin.tags.index', [
            'tags' => $tags,
        ]);
    }

    public function create(): View
    {
        return view('admin.tags.create');
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');

        Tag::query()->create($validated);

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag created successfully.');
    }

    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', [
            'tag' => $tag,
        ]);
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');

        $tag->update($validated);

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()
            ->route('admin.tags.index')
            ->with('status', 'Tag deleted successfully.');
    }
}
