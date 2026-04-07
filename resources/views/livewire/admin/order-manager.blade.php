<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterPaymentStatus = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPaymentStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterStatus', 'filterPaymentStatus');
        $this->resetPage();
    }

    /** @return list<string> */
    #[Computed]
    public function statusOptions(): array
    {
        return ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    }

    /** @return list<string> */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        return ['pending', 'paid', 'failed', 'refunded'];
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return \App\Models\Order::query()
            ->withCount('items')
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterPaymentStatus !== '', fn ($query) => $query->where('payment_status', $this->filterPaymentStatus))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery
                        ->where('order_number', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }
}; ?>

<div>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Orders</h2>
    </div>

    <div class="mb-4 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-4">
        <div>
            <label for="search" class="mb-1 block text-xs font-medium text-slate-700">Search</label>
            <input id="search" type="text" wire:model.live.debounce.300ms="search" placeholder="Order no / email / customer" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="filterStatus" class="mb-1 block text-xs font-medium text-slate-700">Order status</label>
            <select id="filterStatus" wire:model.live="filterStatus" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($this->statusOptions as $statusOption)
                    <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterPaymentStatus" class="mb-1 block text-xs font-medium text-slate-700">Payment status</label>
            <select id="filterPaymentStatus" wire:model.live="filterPaymentStatus" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($this->paymentStatusOptions as $paymentStatusOption)
                    <option value="{{ $paymentStatusOption }}">{{ ucfirst($paymentStatusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="button" wire:click="resetFilters" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Reset</button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Order</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Customer</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Payment</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Items</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Total</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Placed</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="order-{{ $order->id }}">
                        <td class="border border-slate-200 px-3 py-2">
                            <div class="font-medium text-slate-900">{{ $order->order_number }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($order->status) }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            <div>{{ $order->first_name }} {{ $order->last_name }}</div>
                            <div class="text-xs text-slate-500">{{ $order->email }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            <div>{{ ucfirst($order->payment_method) }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($order->payment_status) }}</div>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $order->items_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">${{ number_format((float) $order->total, 2) }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ optional($order->placed_at)->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->orders->links() }}</div>
</div>
