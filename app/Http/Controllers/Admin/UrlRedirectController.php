<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUrlRedirectRequest;
use App\Http\Requests\UpdateUrlRedirectRequest;
use App\Models\UrlRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UrlRedirectController extends Controller
{
    public function index(): View
    {
        $redirects = UrlRedirect::query()->latest('id')->paginate(30);

        return view('admin.url-redirects.index', [
            'redirects' => $redirects,
        ]);
    }

    public function create(): View
    {
        return view('admin.url-redirects.create', [
            'urlRedirect' => new UrlRedirect,
        ]);
    }

    public function store(StoreUrlRedirectRequest $request): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        UrlRedirect::query()->create($validated);

        return redirect()->route('admin.url-redirects.index')->with('status', 'URL redirect created successfully.');
    }

    public function edit(UrlRedirect $urlRedirect): View
    {
        return view('admin.url-redirects.edit', [
            'urlRedirect' => $urlRedirect,
        ]);
    }

    public function update(UpdateUrlRedirectRequest $request, UrlRedirect $urlRedirect): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        $urlRedirect->update($validated);

        return redirect()->route('admin.url-redirects.index')->with('status', 'URL redirect updated successfully.');
    }

    public function destroy(UrlRedirect $urlRedirect): RedirectResponse
    {
        $urlRedirect->delete();

        return redirect()->route('admin.url-redirects.index')->with('status', 'URL redirect deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, bool $isActive): array
    {
        $validated['from_path'] = UrlRedirect::normalizePath((string) $validated['from_path']);
        $validated['to_url'] = trim((string) $validated['to_url']);
        $validated['is_active'] = $isActive;

        return $validated;
    }
}
