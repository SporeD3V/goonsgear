<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionalDiscountRequest;
use App\Http\Requests\UpdateRegionalDiscountRequest;
use App\Models\RegionalDiscount;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegionalDiscountController extends Controller
{
    public function index(): View
    {
        $discounts = RegionalDiscount::query()
            ->orderBy('country_code')
            ->paginate((int) config('pagination.admin_regional_discount_per_page', 50));
        $countries = Countries::all();

        return view('admin.regional-discounts.index', [
            'discounts' => $discounts,
            'countries' => $countries,
        ]);
    }

    public function create(): View
    {
        return view('admin.regional-discounts.create', [
            'discount' => new RegionalDiscount,
            'types' => RegionalDiscount::supportedTypes(),
            'countries' => Countries::all(),
        ]);
    }

    public function store(StoreRegionalDiscountRequest $request): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        RegionalDiscount::query()->create($validated);

        return redirect()->route('admin.regional-discounts.index')->with('status', 'Regional discount created successfully.');
    }

    public function edit(RegionalDiscount $regionalDiscount): View
    {
        return view('admin.regional-discounts.edit', [
            'discount' => $regionalDiscount,
            'types' => RegionalDiscount::supportedTypes(),
            'countries' => Countries::all(),
        ]);
    }

    public function update(UpdateRegionalDiscountRequest $request, RegionalDiscount $regionalDiscount): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        $regionalDiscount->update($validated);

        return redirect()->route('admin.regional-discounts.index')->with('status', 'Regional discount updated successfully.');
    }

    public function destroy(RegionalDiscount $regionalDiscount): RedirectResponse
    {
        $regionalDiscount->delete();

        return redirect()->route('admin.regional-discounts.index')->with('status', 'Regional discount deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, bool $isActive): array
    {
        $validated['country_code'] = strtoupper(trim((string) $validated['country_code']));
        $validated['is_active'] = $isActive;

        return $validated;
    }
}
