<?php

use App\Models\Order;
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

    public string $filterPlaced = '';

    public string $filterFulfillment = '';

    public function mount(): void
    {
        $search = trim((string) request()->query('search', ''));
        if ($search !== '') {
            $this->search = $search;
        }

        $status = trim((string) request()->query('status', request()->query('filterStatus', '')));
        if (in_array($status, $this->statusOptions(), true)) {
            $this->filterStatus = $status;
        }

        $paymentStatus = trim((string) request()->query('payment_status', request()->query('filterPaymentStatus', '')));
        if (in_array($paymentStatus, $this->paymentStatusOptions(), true)) {
            $this->filterPaymentStatus = $paymentStatus;
        }

        $placed = trim((string) request()->query('placed', ''));
        if ($placed === 'today') {
            $this->filterPlaced = 'today';
        }

        $fulfillment = trim((string) request()->query('fulfillment', ''));
        if ($fulfillment === 'to_ship') {
            $this->filterFulfillment = 'to_ship';
        }
    }

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
        $this->reset('search', 'filterStatus', 'filterPaymentStatus', 'filterPlaced', 'filterFulfillment');
        $this->resetPage();
    }

    /** @return list<string> */
    #[Computed]
    public function statusOptions(): array
    {
        return ['pending', 'paid', 'processing', 'on-hold', 'pre-ordered', 'shipped', 'completed', 'cancelled', 'refunded'];
    }

    /** @return list<string> */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        return ['pending', 'paid', 'failed', 'refunded', 'cancelled'];
    }

    /**
     * Operational counts for the tappable quick-filter tiles.
     *
     * @return array{today: int, pending: int, to_ship: int, preorders: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'today' => Order::query()->whereDate('placed_at', today())->count(),
            'pending' => Order::query()->where('status', 'pending')->count(),
            'to_ship' => Order::query()
                ->whereIn('payment_status', ['paid', 'completed'])
                ->whereNull('shipped_at')
                ->whereNotIn('status', ['cancelled', 'refunded', 'completed', 'pre-ordered'])
                ->count(),
            'preorders' => Order::query()->where('status', 'pre-ordered')->count(),
        ];
    }

    /**
     * Toggle one of the quick-filter tiles above the list.
     */
    public function quickFilter(string $key): void
    {
        match ($key) {
            'today' => $this->filterPlaced = $this->filterPlaced === 'today' ? '' : 'today',
            'pending' => $this->filterStatus = $this->filterStatus === 'pending' ? '' : 'pending',
            'to_ship' => $this->filterFulfillment = $this->filterFulfillment === 'to_ship' ? '' : 'to_ship',
            'preorders' => $this->filterStatus = $this->filterStatus === 'pre-ordered' ? '' : 'pre-ordered',
            default => null,
        };

        $this->resetPage();
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->withCount('items')
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterPaymentStatus !== '', fn ($query) => $query->where('payment_status', $this->filterPaymentStatus))
            ->when($this->filterPlaced === 'today', fn ($query) => $query->whereDate('placed_at', today()))
            ->when($this->filterFulfillment === 'to_ship', fn ($query) => $query
                ->whereIn('payment_status', ['paid', 'completed'])
                ->whereNull('shipped_at')
                ->whereNotIn('status', ['cancelled', 'refunded', 'completed', 'pre-ordered']))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery
                        ->where('order_number', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('country', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }
}; ?>

<div class="space-y-6">
    @php
        $statusBadge = fn (string $status): string => match ($status) {
            'paid', 'completed', 'delivered' => 'bg-emerald-100 text-emerald-700',
            'processing', 'shipped', 'pre-ordered' => 'bg-blue-100 text-blue-700',
            'pending', 'on-hold' => 'bg-amber-100 text-amber-700',
            'cancelled', 'refunded', 'failed' => 'bg-red-100 text-red-700',
            default => 'bg-stone-100 text-stone-600',
        };

        $hasAnyFilter = $this->search !== '' || $this->filterStatus !== '' || $this->filterPaymentStatus !== ''
            || $this->filterPlaced !== '' || $this->filterFulfillment !== '';
    @endphp

    {{-- Header --}}
    <div>
        <h2 class="text-lg font-semibold text-stone-800">Orders</h2>
        <p class="text-[13px] text-stone-500">{{ number_format($this->orders->total()) }} {{ \Illuminate\Support\Str::plural('order', $this->orders->total()) }} in current view</p>
    </div>

    @php
        $orderListNoteOptions = $this->orders->getCollection()
            ->map(fn ($order) => [
                'key' => 'order-list::' . $order->id,
                'label' => 'Order ' . $order->order_number,
                'value' => '€' . number_format((float) $order->total, 2),
                'meta' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ])
            ->values()
            ->all();
    @endphp

    @include('admin._page-notes-card', [
        'context' => 'orders-list',
        'label' => 'Orders List',
        'anchorOptions' => $orderListNoteOptions,
    ])

    {{-- Quick stats — tap to filter --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['key' => 'today', 'label' => 'Placed Today', 'count' => $this->stats['today'], 'isOn' => $this->filterPlaced === 'today', 'tone' => 'text-stone-500'],
            ['key' => 'pending', 'label' => 'Pending', 'count' => $this->stats['pending'], 'isOn' => $this->filterStatus === 'pending', 'tone' => 'text-amber-600'],
            ['key' => 'to_ship', 'label' => 'To Ship', 'count' => $this->stats['to_ship'], 'isOn' => $this->filterFulfillment === 'to_ship', 'tone' => 'text-[#36a2eb]'],
            ['key' => 'preorders', 'label' => 'Pre-orders', 'count' => $this->stats['preorders'], 'isOn' => $this->filterStatus === 'pre-ordered', 'tone' => 'text-blue-600'],
        ] as $tile)
            <button type="button"
                    wire:click="quickFilter('{{ $tile['key'] }}')"
                    class="admin-card rounded-xl border bg-white p-4 text-left shadow-sm transition {{ $tile['isOn'] ? 'border-[#36a2eb] ring-1 ring-[#36a2eb]' : 'border-stone-200 hover:border-stone-300 hover:shadow' }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[12px] font-medium uppercase tracking-wide text-stone-500">{{ $tile['label'] }}</span>
                    @if ($tile['isOn'])
                        <span class="rounded-full bg-[#36a2eb]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#36a2eb]">On</span>
                    @endif
                </div>
                <div class="mt-1 text-2xl font-bold {{ $tile['isOn'] ? 'text-[#36a2eb]' : 'text-stone-800' }}">{{ number_format($tile['count']) }}</div>
                <div class="mt-0.5 text-[11px] {{ $tile['tone'] }}">
                    <span class="pointer-coarse:hidden">{{ $tile['isOn'] ? 'Click to clear filter' : 'Click to filter' }}</span>
                    <span class="hidden pointer-coarse:inline">{{ $tile['isOn'] ? 'Tap to clear filter' : 'Tap to filter' }}</span>
                </div>
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm" data-delay="1">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input id="search" type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search order no, email, customer, or country…"
                       class="w-full rounded-lg border border-stone-200 py-2.5 pl-9 pr-3 text-sm text-stone-700 placeholder:text-stone-400 focus:border-[#36a2eb] focus:outline-none focus:ring-1 focus:ring-[#36a2eb]">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex flex-wrap rounded-lg border border-stone-200 bg-stone-50 p-0.5">
                    @foreach (['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $value => $label)
                        <button type="button"
                                wire:click="$set('filterPaymentStatus', '{{ $value }}')"
                                class="rounded-md px-3 py-1.5 text-[13px] font-medium transition {{ $this->filterPaymentStatus === $value ? 'bg-white text-[#36a2eb] shadow-sm' : 'text-stone-500 hover:text-stone-700' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <select id="filterStatus" wire:model.live="filterStatus" class="rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-600 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">Any status</option>
                    @foreach ($this->statusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                    @endforeach
                </select>

                @if ($hasAnyFilter)
                    <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-2 text-[13px] font-medium text-stone-500 transition hover:bg-stone-50 hover:text-stone-700">
                        Reset
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Order list --}}
    <div class="admin-card overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-delay="2"
         wire:loading.class="pointer-events-none opacity-60"
         wire:target="search, filterStatus, filterPaymentStatus, quickFilter, resetFilters">

        {{-- Desktop column header --}}
        <div class="hidden gap-4 border-b border-stone-100 bg-stone-50/60 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-stone-500 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.4fr)_13rem_6rem_7rem_1.5rem]">
            <span>Order</span>
            <span>Customer</span>
            <span>Status</span>
            <span class="text-right">Total</span>
            <span class="text-right">Placed</span>
            <span></span>
        </div>

        <ul class="divide-y divide-stone-100">
            @forelse ($this->orders as $order)
                <li wire:key="order-{{ $order->id }}">
                    <a href="{{ route('admin.orders.show', $order) }}"
                       class="group flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 transition hover:bg-stone-50/60 md:grid md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.4fr)_13rem_6rem_7rem_1.5rem]">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-stone-800 group-hover:text-[#36a2eb]">{{ $order->order_number }}</p>
                            <p class="mt-0.5 text-xs text-stone-400">{{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }} &middot; {{ ucfirst($order->payment_method) }}</p>
                        </div>

                        <div class="min-w-0 basis-full md:basis-auto">
                            <p class="truncate text-sm text-stone-700">{{ $order->first_name }} {{ $order->last_name }}</p>
                            <p class="truncate text-xs text-stone-400">{{ $order->email }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($order->status) }}">{{ ucfirst($order->status) }}</span>
                            @if ($order->payment_status !== $order->status)
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($order->payment_status) }}">{{ ucfirst($order->payment_status) }}</span>
                            @endif
                        </div>

                        <p class="text-sm font-bold text-stone-800 md:text-right">&euro;{{ number_format((float) $order->total, 2) }}</p>

                        <p class="text-xs text-stone-400 md:text-right">{{ optional($order->placed_at)->format('M d, Y') ?? '—' }}</p>

                        <svg class="hidden h-4 w-4 shrink-0 text-stone-300 transition group-hover:translate-x-0.5 group-hover:text-[#36a2eb] md:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </li>
            @empty
                <li class="px-6 py-14 text-center">
                    <svg class="mx-auto h-10 w-10 text-stone-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/></svg>
                    <p class="mt-3 text-sm font-medium text-stone-600">
                        {{ $hasAnyFilter ? 'No orders match these filters.' : 'No orders yet.' }}
                    </p>
                    @if ($hasAnyFilter)
                        <button type="button" wire:click="resetFilters" class="mt-3 rounded-lg border border-stone-200 px-4 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-50">Clear filters</button>
                    @endif
                </li>
            @endforelse
        </ul>

        @if ($this->orders->hasPages())
            <div class="border-t border-stone-100 px-4 py-3">{{ $this->orders->links() }}</div>
        @endif
    </div>
</div>
