@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Bundle Discounts</h2>
        <a href="{{ route('admin.bundle-discounts.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Rule</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Type</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Value</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Items</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bundleDiscounts as $bundleDiscount)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-900">{{ $bundleDiscount->name }}</p>
                            @if ($bundleDiscount->description)
                                <p class="text-xs text-slate-500">{{ $bundleDiscount->description }}</p>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($bundleDiscount->discount_type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($bundleDiscount->discount_type === \App\Models\BundleDiscount::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $bundleDiscount->discount_value, 2), '0'), '.') }}%
                            @else
                                ${{ number_format((float) $bundleDiscount->discount_value, 2) }}
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $bundleDiscount->items_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $bundleDiscount->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.bundle-discounts.edit', $bundleDiscount) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.bundle-discounts.destroy', $bundleDiscount) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this bundle discount?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No bundle discounts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bundleDiscounts->links() }}</div>
@endsection
