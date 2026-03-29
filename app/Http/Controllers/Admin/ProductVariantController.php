<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(Product $product): View
    {
        return view('admin.products.variants.create', [
            'product' => $product,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        $validated['track_inventory'] = $request->boolean('track_inventory');
        $validated['allow_backorder'] = $request->boolean('allow_backorder');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_preorder'] = $request->boolean('is_preorder');
        $validated['option_values'] = $request->filled('option_values_json')
            ? json_decode((string) $request->input('option_values_json'), true)
            : null;
        unset($validated['option_values_json']);

        $product->variants()->create($validated);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Variant created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product, ProductVariant $variant): View
    {
        return view('admin.products.variants.edit', [
            'product' => $product,
            'variant' => $variant,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $validated = $request->validated();

        $validated['track_inventory'] = $request->boolean('track_inventory');
        $validated['allow_backorder'] = $request->boolean('allow_backorder');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_preorder'] = $request->boolean('is_preorder');
        $validated['option_values'] = $request->filled('option_values_json')
            ? json_decode((string) $request->input('option_values_json'), true)
            : null;
        unset($validated['option_values_json']);

        $variant->update($validated);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Variant updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->delete();

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Variant deleted successfully.');
    }
}
