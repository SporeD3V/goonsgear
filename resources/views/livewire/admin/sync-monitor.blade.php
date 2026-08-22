<?php

use App\Models\WcSyncPayload;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $eventFilter = '';
    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->eventFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function processNow(): void
    {
        \Illuminate\Support\Facades\Artisan::call('sync:process', ['--limit' => 500]);
        $output = trim(\Illuminate\Support\Facades\Artisan::output());

        session()->flash('status', 'Sync processor ran: ' . $output);
    }

    public function retryPayload(int $id): void
    {
        WcSyncPayload::findOrFail($id)->resetForRetry();

        $this->afterMutation("Payload #{$id} reset — it will run on the next sync.");
    }

    public function dismissPayload(int $id): void
    {
        WcSyncPayload::findOrFail($id)->markDismissed();

        $this->afterMutation("Payload #{$id} dismissed without processing.");
    }

    public function deletePayload(int $id): void
    {
        WcSyncPayload::findOrFail($id)->delete();

        $this->afterMutation("Payload #{$id} deleted.");
    }

    public function retryAllFailed(): void
    {
        $count = WcSyncPayload::failed()->update([
            'attempts' => 0,
            'processing_error' => null,
        ]);

        $this->afterMutation("{$count} payload(s) reset — they will run on the next sync.");
    }

    public function dismissAllFailed(): void
    {
        $count = WcSyncPayload::failed()->update(['processed_at' => now()]);

        $this->afterMutation("{$count} failed payload(s) dismissed.");
    }

    /**
     * Drop cached computed values so the table and counters reflect the change.
     */
    private function afterMutation(string $message): void
    {
        unset($this->payloads, $this->health);

        session()->flash('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function health(): array
    {
        $total = WcSyncPayload::count();
        $pending = WcSyncPayload::pending()->count();
        $failed = WcSyncPayload::failed()->count();
        $blocked = WcSyncPayload::exhausted()->count();
        $processed = $total - $pending;
        $latestReceived = WcSyncPayload::max('received_at');
        $latestProcessed = WcSyncPayload::max('processed_at');

        // Consider "healthy" if no pending payloads, or if most recent received
        // was within the last 15 minutes and processed count is near total.
        $isHealthy = $pending === 0 || ($pending <= 5 && $failed === 0);

        return [
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'failed' => $failed,
            'blocked' => $blocked,
            'latest_received' => $latestReceived,
            'latest_processed' => $latestProcessed,
            'is_healthy' => $isHealthy,
        ];
    }

    /**
     * @return array<int, array{event: string, count: int}>
     */
    #[Computed]
    public function eventBreakdown(): array
    {
        // Grouped by full event in SQL (few distinct values), then folded to
        // domains in PHP so this does not depend on MySQL's SUBSTRING_INDEX.
        return WcSyncPayload::query()
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->get()
            ->groupBy(fn (WcSyncPayload $row) => explode('.', (string) $row->event)[0])
            ->map(fn ($rows) => (int) $rows->sum('total'))
            ->sortDesc()
            ->map(fn (int $count, string $domain) => ['event' => $domain, 'count' => $count])
            ->values()
            ->all();
    }

    #[Computed]
    public function payloads()
    {
        return WcSyncPayload::query()
            ->when($this->eventFilter !== '', fn ($q) => $q->where('event', 'like', $this->eventFilter . '%'))
            ->when($this->statusFilter === 'pending', fn ($q) => $q->pending())
            ->when($this->statusFilter === 'processed', fn ($q) => $q->whereNotNull('processed_at')->whereNull('processing_error'))
            ->when($this->statusFilter === 'failed', fn ($q) => $q->failed())
            ->when($this->statusFilter === 'blocked', fn ($q) => $q->exhausted())
            ->when($this->statusFilter === 'dismissed', fn ($q) => $q->whereNotNull('processed_at')->whereNotNull('processing_error'))
            ->when($this->search !== '', fn ($q) => $q->where('event', 'like', '%' . $this->search . '%'))
            ->latest('received_at')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function eventTypes(): array
    {
        return WcSyncPayload::query()
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values()
            ->all();
    }
}; ?>

<div class="space-y-6" wire:poll.60s>

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold">WooCommerce Sync</h1>
        <button
            wire:click="processNow"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-[#2b8fd4] disabled:opacity-50"
        >
            <svg wire:loading.remove wire:target="processNow" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 18.364a9 9 0 0 1 0-12.728m12.728 0a9 9 0 0 1 0 12.728M12 9v4l2.5 1.5"/></svg>
            <svg wire:loading wire:target="processNow" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Process Now
        </button>
    </div>

    {{-- Health Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Total --}}
        <div class="admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Total Payloads</p>
            <p class="mt-1 text-2xl font-bold text-stone-800">{{ number_format($this->health['total']) }}</p>
        </div>
        {{-- Processed --}}
        <div class="admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Processed</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($this->health['processed']) }}</p>
        </div>
        {{-- Pending --}}
        <div class="rounded-lg border {{ $this->health['pending'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-stone-200 bg-white' }} p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Pending</p>
            <p class="mt-1 text-2xl font-bold {{ $this->health['pending'] > 0 ? 'text-amber-600' : 'text-stone-800' }}">{{ $this->health['pending'] }}</p>
        </div>
        {{-- Failed --}}
        <div class="rounded-lg border {{ $this->health['failed'] > 0 ? 'border-red-200 bg-red-50' : 'border-stone-200 bg-white' }} p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Failed</p>
            <p class="mt-1 text-2xl font-bold {{ $this->health['failed'] > 0 ? 'text-red-600' : 'text-stone-800' }}">{{ $this->health['failed'] }}</p>
        </div>
    </div>

    {{-- Health Status + Timestamps --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
            @if ($this->health['is_healthy'])
                <span class="flex h-3 w-3">
                    <span class="absolute inline-flex h-3 w-3 animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                </span>
                <span class="text-sm font-medium text-emerald-700">Pipeline Healthy</span>
            @else
                <span class="flex h-3 w-3">
                    <span class="absolute inline-flex h-3 w-3 animate-ping rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-amber-500"></span>
                </span>
                <span class="text-sm font-medium text-amber-700">
                    {{ $this->health['failed'] > 0 ? 'Failures Detected' : 'Payloads Queued' }}
                </span>
            @endif
        </div>
        <div class="mt-3 grid grid-cols-1 gap-4 text-sm text-stone-600 sm:grid-cols-2">
            <div>
                <span class="font-medium">Last Received:</span>
                {{ $this->health['latest_received'] ? \Carbon\Carbon::parse($this->health['latest_received'])->diffForHumans() : 'Never' }}
            </div>
            <div>
                <span class="font-medium">Last Processed:</span>
                {{ $this->health['latest_processed'] ? \Carbon\Carbon::parse($this->health['latest_processed'])->diffForHumans() : 'Never' }}
            </div>
        </div>

        {{-- Bulk recovery for failures --}}
        @if ($this->health['failed'] > 0)
            <div class="mt-4 flex flex-col gap-3 border-t border-stone-100 pt-4 sm:flex-row sm:items-center">
                <p class="text-xs text-stone-500">
                    {{ $this->health['failed'] }} payload(s) failing.
                    @if ($this->health['blocked'] > 0)
                        {{ $this->health['blocked'] }} stopped retrying after {{ \App\Models\WcSyncPayload::MAX_ATTEMPTS }} attempts and need a manual retry.
                    @endif
                </p>
                <div class="flex gap-2 sm:ml-auto">
                    <button
                        wire:click="retryAllFailed"
                        wire:loading.attr="disabled"
                        class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm hover:bg-stone-50 disabled:opacity-50"
                    >
                        Retry all failed
                    </button>
                    <button
                        wire:click="dismissAllFailed"
                        wire:confirm="Dismiss all failed payloads? They will never be processed, and any data they carry will not reach the shop."
                        wire:loading.attr="disabled"
                        class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm hover:bg-stone-50 disabled:opacity-50"
                    >
                        Dismiss all failed
                    </button>
                </div>
            </div>
        @endif

        {{-- Event breakdown --}}
        @if (count($this->eventBreakdown) > 0)
            <div class="mt-4 border-t border-stone-100 pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">By Domain</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->eventBreakdown as $item)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                            {{ $item['event'] }}
                            <span class="rounded-full bg-stone-200 px-1.5 py-0.5 text-[10px]">{{ $item['count'] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-700">Filters</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">Search</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search events…"
                    class="mt-1 block w-full rounded-md border-stone-300 text-sm shadow-sm focus:border-blue-500 focus:ring-[#36a2eb]"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700">Event</label>
                <select wire:model.live="eventFilter" class="mt-1 block w-full rounded-md border-stone-300 text-sm shadow-sm focus:border-blue-500 focus:ring-[#36a2eb]">
                    <option value="">All Events</option>
                    @foreach ($this->eventTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700">Status</label>
                <select wire:model.live="statusFilter" class="mt-1 block w-full rounded-md border-stone-300 text-sm shadow-sm focus:border-blue-500 focus:ring-[#36a2eb]">
                    <option value="">All</option>
                    <option value="processed">Processed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="blocked">Stopped retrying</option>
                    <option value="dismissed">Dismissed</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button wire:click="resetFilters" class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 shadow-sm hover:bg-stone-50">Reset</button>
            </div>
        </div>
    </div>

    {{-- Payload Table --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <div wire:loading.delay class="mb-2 text-xs text-stone-500">Loading…</div>

        <div class="-mx-5 overflow-x-auto px-5">
            <table class="admin-mobile-table min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">Event</th>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">Entity</th>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">Received</th>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-stone-600">Error</th>
                        <th class="px-4 py-3 text-right font-medium text-stone-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($this->payloads as $payload)
                        <tr wire:key="payload-{{ $payload->id }}" class="hover:bg-stone-50">
                            <td class="whitespace-nowrap px-4 py-3 text-stone-500">#{{ $payload->id }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @php
                                    $eventColors = [
                                        'order' => 'bg-blue-100 text-blue-700',
                                        'product' => 'bg-purple-100 text-purple-700',
                                        'customer' => 'bg-teal-100 text-teal-700',
                                        'coupon' => 'bg-amber-100 text-amber-700',
                                        'note' => 'bg-stone-100 text-stone-700',
                                        'ping' => 'bg-green-100 text-green-700',
                                    ];
                                    $domain = explode('.', $payload->event)[0] ?? '';
                                    $color = $eventColors[$domain] ?? 'bg-stone-100 text-stone-700';
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">
                                    {{ $payload->event }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-stone-600">
                                @if ($payload->wc_entity_type && $payload->wc_entity_id)
                                    {{ ucfirst($payload->wc_entity_type) }} #{{ $payload->wc_entity_id }}
                                @else
                                    <span class="text-stone-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-stone-500" title="{{ $payload->received_at?->toDateTimeString() }}">
                                {{ $payload->received_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($payload->isDismissed())
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-stone-500" title="Dismissed {{ $payload->processed_at->toDateTimeString() }} — never processed">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM6.28 6.28a.75.75 0 0 1 1.06 0L10 8.94l2.66-2.66a.75.75 0 1 1 1.06 1.06L11.06 10l2.66 2.66a.75.75 0 1 1-1.06 1.06L10 11.06l-2.66 2.66a.75.75 0 0 1-1.06-1.06L8.94 10 6.28 7.34a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                                        Dismissed
                                    </span>
                                @elseif ($payload->processed_at)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600" title="Processed {{ $payload->processed_at->toDateTimeString() }}">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                        Done
                                    </span>
                                @elseif ($payload->isExhausted())
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700" title="Stopped retrying after {{ $payload->attempts }} attempts">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                        Stopped ({{ $payload->attempts }}×)
                                    </span>
                                @elseif ($payload->attempts > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                        Failed ({{ $payload->attempts }}×)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600">
                                        <svg class="h-3.5 w-3.5 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-xs text-red-600" title="{{ $payload->processing_error }}">
                                {{ $payload->processing_error ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @unless ($payload->processed_at && ! $payload->isDismissed())
                                        <button
                                            wire:click="retryPayload({{ $payload->id }})"
                                            wire:loading.attr="disabled"
                                            class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline disabled:opacity-50"
                                            title="Clear the error and run this payload on the next sync"
                                        >
                                            Retry
                                        </button>
                                    @endunless

                                    @unless ($payload->processed_at)
                                        <button
                                            wire:click="dismissPayload({{ $payload->id }})"
                                            wire:confirm="Dismiss payload #{{ $payload->id }}? It will never be processed, and any data it carries will not reach the shop."
                                            wire:loading.attr="disabled"
                                            class="text-xs font-medium text-stone-600 hover:text-stone-800 hover:underline disabled:opacity-50"
                                            title="Retire this payload without processing it"
                                        >
                                            Dismiss
                                        </button>
                                    @endunless

                                    <button
                                        wire:click="deletePayload({{ $payload->id }})"
                                        wire:confirm="Permanently delete payload #{{ $payload->id }}? This cannot be undone."
                                        wire:loading.attr="disabled"
                                        class="text-xs font-medium text-red-600 hover:text-red-800 hover:underline disabled:opacity-50"
                                        title="Permanently remove this row"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-stone-400">
                                {{ $search || $eventFilter || $statusFilter ? 'No payloads match your filters.' : 'No webhook payloads received yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->payloads->hasPages())
            <div class="mt-4">{{ $this->payloads->links() }}</div>
        @endif
    </div>
</div>

