<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBundleDiscountRequest;
use App\Http\Requests\UpdateBundleDiscountRequest;
use App\Models\BundleDiscount;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BundleDiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $bundleDiscounts = BundleDiscount::query()->withCount('items')->latest('id')->paginate(20);

        return view('admin.bundle-discounts.index', [
            'bundleDiscounts' => $bundleDiscounts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.bundle-discounts.create', [
            'bundleDiscount' => new BundleDiscount,
            'types' => BundleDiscount::supportedTypes(),
            'variants' => $this->variantOptions(),
            'selectedVariantIds' => [],
            'itemQuantities' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBundleDiscountRequest $request): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));
        $variantItems = $this->buildVariantItems();

        $bundleDiscount = BundleDiscount::query()->create($validated);
        $bundleDiscount->items()->createMany($variantItems);

        return redirect()->route('admin.bundle-discounts.index')->with('status', 'Bundle discount created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function edit(BundleDiscount $bundleDiscount): View
    {
        $bundleDiscount->load('items');

        return view('admin.bundle-discounts.edit', [
            'bundleDiscount' => $bundleDiscount,
            'types' => BundleDiscount::supportedTypes(),
            'variants' => $this->variantOptions(),
            'selectedVariantIds' => $bundleDiscount->items->pluck('product_variant_id')->map(fn ($id): int => (int) $id)->all(),
            'itemQuantities' => $bundleDiscount->items->mapWithKeys(fn ($item): array => [(int) $item->product_variant_id => (int) $item->min_quantity])->all(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function update(UpdateBundleDiscountRequest $request, BundleDiscount $bundleDiscount): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));
        $variantItems = $this->buildVariantItems();

        $bundleDiscount->update($validated);
        $bundleDiscount->items()->delete();
        $bundleDiscount->items()->createMany($variantItems);

        return redirect()->route('admin.bundle-discounts.index')->with('status', 'Bundle discount updated successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function destroy(BundleDiscount $bundleDiscount): RedirectResponse
    {
        $bundleDiscount->delete();

        return redirect()->route('admin.bundle-discounts.index')->with('status', 'Bundle discount deleted successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    private function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'product_id', 'name', 'sku'])
            ->map(function (ProductVariant $variant): array {
                return [
                    'id' => (int) $variant->id,
                    'label' => trim((string) $variant->product?->name).' - '.$variant->name.' ('.$variant->sku.')',
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, bool $isActive): array
    {
        unset($validated['variant_ids'], $validated['quantities']);
        $validated['is_active'] = $isActive;

        return $validated;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function buildVariantItems(): array
    {
        $variantIds = collect(request()->input('variant_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            throw ValidationException::withMessages([
                'variant_ids' => 'Select at least one product variant.',
            ]);
        }

        /** @var array<string, mixed> $quantities */
        $quantities = request()->input('quantities', []);

        return $variantIds->values()->map(function (int $variantId, int $position) use ($quantities): array {
            $quantity = (int) ($quantities[(string) $variantId] ?? $quantities[$variantId] ?? 1);

            return [
                'product_variant_id' => $variantId,
                'min_quantity' => max(1, $quantity),
                'position' => $position,
            ];
        })->all();
    }
}
