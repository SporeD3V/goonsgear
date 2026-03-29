@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Abandoned Cart Reminder Settings</h2>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-700 hover:underline">Back to Products</a>
    </div>

    <p class="mb-4 text-sm text-slate-600">
        Configure whether reminder emails are sent, the delay before sending, and which coupon code (if any) should be included.
    </p>

    <form method="POST" action="{{ route('admin.maintenance.abandoned-cart.update') }}" class="grid gap-4 rounded border border-slate-200 bg-slate-50 p-4 md:max-w-2xl">
        @csrf

        <div class="flex items-center gap-2">
            <input id="is_enabled" type="checkbox" name="is_enabled" value="1" @checked((bool) old('is_enabled', $settings->is_enabled))>
            <label for="is_enabled" class="text-sm font-medium text-slate-800">Enable abandoned cart reminder emails</label>
        </div>

        <div>
            <label for="delay_minutes" class="mb-1 block text-sm font-medium text-slate-700">Send delay (minutes)</label>
            <input
                id="delay_minutes"
                type="number"
                name="delay_minutes"
                min="15"
                max="10080"
                step="1"
                value="{{ old('delay_minutes', $settings->delay_minutes) }}"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-slate-500">Minimum 15, maximum 10080 (7 days).</p>
        </div>

        <div>
            <label for="coupon_code" class="mb-1 block text-sm font-medium text-slate-700">Coupon code for reminder email (optional)</label>
            <select id="coupon_code" name="coupon_code" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">No coupon in reminder</option>
                @foreach ($coupons as $coupon)
                    <option value="{{ $coupon->code }}" @selected(old('coupon_code', $settings->coupon_code) === $coupon->code)>
                        {{ $coupon->code }}{{ $coupon->is_active ? '' : ' (inactive)' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Only active and valid coupons are included per-cart when reminders are sent.</p>
        </div>

        <div>
            <button type="submit" class="rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-800">Save Settings</button>
        </div>
    </form>
@endsection
