<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::query()->latest('id')->paginate(20);

        return view('admin.coupons.index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'coupon' => new Coupon,
            'types' => Coupon::supportedTypes(),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        Coupon::query()->create($validated);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.edit', [
            'coupon' => $coupon,
            'types' => Coupon::supportedTypes(),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        $coupon->update($validated);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, bool $isActive): array
    {
        $validated['code'] = Str::upper(trim((string) $validated['code']));
        $validated['is_active'] = $isActive;

        return $validated;
    }
}
