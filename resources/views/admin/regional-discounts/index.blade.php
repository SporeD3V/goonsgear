@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Regional Discounts</h2>
        <a href="{{ route('admin.regional-discounts.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Rule</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Country</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Type</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Value</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Reason</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($discounts as $discount)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-900">{{ $countries[$discount->country_code] ?? $discount->country_code }}</p>
                            <p class="text-xs text-slate-500">{{ $discount->country_code }}</p>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($discount->discount_type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($discount->discount_type === \App\Models\RegionalDiscount::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $discount->discount_value, 2), '0'), '.') }}%
                            @else
                                ${{ number_format((float) $discount->discount_value, 2) }}
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2 max-w-xs truncate">{{ $discount->reason }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $discount->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.regional-discounts.edit', $discount) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.regional-discounts.destroy', $discount) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this rule?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No regional discount rules yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $discounts->links() }}</div>
@endsection
