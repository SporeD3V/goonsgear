<?php

use App\Models\AdminActivityLog;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $action = '';
    public string $subjectType = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedSubjectType(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->action = '';
        $this->subjectType = '';
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return AdminActivityLog::query()
            ->with('user:id,name,email')
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->subjectType !== '', fn ($q) => $q->where('subject_type', $this->subjectType))
            ->when($this->search !== '', fn ($q) => $q->where('description', 'like', '%' . $this->search . '%'))
            ->latest('id')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    #[Computed]
    public function subjectTypes(): array
    {
        return AdminActivityLog::query()
            ->distinct()
            ->pluck('subject_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold">Sync Log</h1>
    </div>

    {{-- Filters --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Filters</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Search</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search descriptions…"
                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Action</label>
            <select wire:model.live="action" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Actions</option>
                <option value="created">Created</option>
                <option value="updated">Updated</option>
                <option value="deleted">Deleted</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Type</label>
            <select wire:model.live="subjectType" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Types</option>
                @foreach ($this->subjectTypes as $type)
                    <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button wire:click="resetFilters" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Reset</button>
        </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-slate-500">Loading…</div>

        {{-- Log Table --}}
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">When</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">User</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Action</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500" title="{{ $log->created_at->toDateTimeString() }}">
                            {{ $log->created_at->diffForHumans() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            {{ $log->user?->name ?? $log->user?->email ?? 'System' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @php
                                $actionColors = [
                                    'created' => 'bg-green-100 text-green-700',
                                    'updated' => 'bg-blue-100 text-blue-700',
                                    'deleted' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $actionColors[$log->action] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ ucfirst($log->action) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                            {{ class_basename($log->subject_type) }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $log->description }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                            {{ $search || $action || $subjectType ? 'No logs match your filters.' : 'No activity recorded yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->logs->hasPages())
        <div class="mt-4">{{ $this->logs->links() }}</div>
    @endif
    </div>
</div>
