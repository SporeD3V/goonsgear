@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Coupons</h2>
        <a href="{{ route('admin.coupons.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Coupon</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Code</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Type</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Value</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Usage</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coupons as $coupon)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-900">{{ $coupon->code }}</p>
                            <p class="text-xs text-slate-500">{{ $coupon->description ?: '-' }}</p>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($coupon->type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($coupon->type === \App\Models\Coupon::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}%
                            @else
                                ${{ number_format((float) $coupon->value, 2) }}
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $coupon->used_count }}@if ($coupon->usage_limit !== null) / {{ $coupon->usage_limit }} @endif</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this coupon?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No coupons yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $coupons->links() }}</div>
@endsection