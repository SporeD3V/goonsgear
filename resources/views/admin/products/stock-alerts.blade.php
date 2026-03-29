@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Customers Waiting for {{ $product->name }}</h2>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-700 hover:underline">Back to Products</a>
    </div>

    <div class="mb-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
        <p>Total customers waiting: <strong>{{ $subscriptions->total() }}</strong></p>
    </div>

    @if ($subscriptions->isEmpty())
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            No customers are currently waiting for this product.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full border border-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="border border-slate-200 px-3 py-2 text-left">Customer</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Email</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Variant</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Subscribed</th>
                        <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td class="border border-slate-200 px-3 py-2">{{ $subscription->user->name }}</td>
                            <td class="border border-slate-200 px-3 py-2">
                                <a href="mailto:{{ $subscription->user->email }}" class="text-blue-700 hover:underline">
                                    {{ $subscription->user->email }}
                                </a>
                            </td>
                            <td class="border border-slate-200 px-3 py-2">
                                <span class="text-slate-700">{{ $subscription->variant->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $subscription->variant->sku }}</span>
                            </td>
                            <td class="border border-slate-200 px-3 py-2">{{ $subscription->created_at->format('M d, Y') }}</td>
                            <td class="border border-slate-200 px-3 py-2">
                                @if ($subscription->notified_at)
                                    <span class="inline-block rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">
                                        Notified {{ $subscription->notified_at->format('M d') }}
                                    </span>
                                @else
                                    <span class="inline-block rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">
                                        Waiting
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    @endif
@endsection
