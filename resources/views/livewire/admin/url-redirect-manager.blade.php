<?php

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use App\Models\UrlRedirect;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'id';
    public string $sortDirection = 'desc';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $from_path = '';
    public string $to_url = '';
    public int $status_code = 301;
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $redirect = UrlRedirect::findOrFail($id);
        $this->editingId = $redirect->id;
        $this->from_path = $redirect->from_path;
        $this->to_url = $redirect->to_url;
        $this->status_code = $redirect->status_code;
        $this->is_active = $redirect->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $uniqueRule = Rule::unique('url_redirects', 'from_path');
        if ($this->editingId) {
            $uniqueRule = $uniqueRule->ignore($this->editingId);
        }

        $validated = $this->validate([
            'from_path' => ['required', 'string', 'max:255', $uniqueRule],
            'to_url' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
            'is_active' => ['boolean'],
        ]);

        $validated['from_path'] = UrlRedirect::normalizePath($validated['from_path']);
        $validated['to_url'] = trim($validated['to_url']);

        if ($this->editingId) {
            $redirect = UrlRedirect::findOrFail($this->editingId);

            foreach (['from_path', 'to_url', 'status_code', 'is_active'] as $field) {
                $oldValue = (string) $redirect->getAttribute($field);
                $newValue = (string) $validated[$field];
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($redirect, $field, $oldValue, $newValue);
                }
            }

            $redirect->update($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $redirect, "Updated URL redirect \"{$redirect->from_path}\"");
            session()->flash('status', 'URL redirect updated.');
        } else {
            $redirect = UrlRedirect::create($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $redirect, "Created URL redirect \"{$redirect->from_path}\"");
            session()->flash('status', 'URL redirect created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $redirect = UrlRedirect::findOrFail($id);
        $oldValue = (string) $redirect->is_active;
        $redirect->update(['is_active' => ! $redirect->is_active]);
        EditHistory::recordChange($redirect, 'is_active', $oldValue, (string) $redirect->is_active);
        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $redirect,
            ($redirect->is_active ? 'Activated' : 'Deactivated') . " URL redirect \"{$redirect->from_path}\""
        );
    }

    public function delete(int $id): void
    {
        $redirect = UrlRedirect::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $redirect, "Deleted URL redirect \"{$redirect->from_path}\"");
        $redirect->delete();
        session()->flash('status', 'URL redirect deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->from_path = '';
        $this->to_url = '';
        $this->status_code = 301;
        $this->is_active = true;
        $this->resetValidation();
    }

    #[Computed]
    public function redirects()
    {
        $allowedSorts = ['id', 'from_path', 'to_url', 'status_code', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'id';

        return UrlRedirect::query()
            ->when($this->search, fn ($q) => $q->where('from_path', 'like', '%' . $this->search . '%')
                ->orWhere('to_url', 'like', '%' . $this->search . '%'))
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(20);
    }
}; ?>

<div class="space-y-6">
    {{-- Flash message --}}
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold">URL Redirects</h2>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search redirects…"
                class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                New Redirect
            </button>
        </div>
    </div>

    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-stone-500">Loading…</div>

    {{-- Redirect list --}}
    <div class="mb-2 flex flex-wrap items-center gap-2 text-[11px] text-stone-400">
        <span>Sort:</span>
        @foreach (['from_path' => 'From Path', 'status_code' => 'Status', 'is_active' => 'Active'] as $field => $label)
            <button wire:click="sortBy('{{ $field }}')" class="rounded-full px-2 py-0.5 font-medium transition {{ $sortField === $field ? 'bg-[#36a2eb]/10 text-[#36a2eb]' : 'text-stone-500 hover:bg-stone-100' }}">
                {{ $label }}@if ($sortField === $field) {{ $sortDirection === 'asc' ? '↑' : '↓' }}@endif
            </button>
        @endforeach
    </div>

    <ul class="divide-y divide-stone-100">
        @forelse ($this->redirects as $redirect)
            <li wire:key="redirect-{{ $redirect->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 transition hover:bg-stone-50/60">
                <div class="min-w-0 flex-1">
                    <p class="break-all font-mono text-sm font-semibold text-stone-800">{{ $redirect->from_path }}</p>
                    <p class="mt-0.5 flex items-center gap-1.5 break-all font-mono text-xs text-stone-400">
                        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        {{ $redirect->to_url }}
                    </p>
                </div>

                <span @class([
                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-purple-100 text-purple-800' => $redirect->status_code === 301,
                    'bg-amber-100 text-amber-800' => $redirect->status_code === 302,
                ])>{{ $redirect->status_code }}</span>

                <button wire:click="toggleActive({{ $redirect->id }})" title="{{ $redirect->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition hover:ring-1 hover:ring-stone-300 {{ $redirect->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-500' }}">
                    {{ $redirect->is_active ? 'Active' : 'Inactive' }}
                </button>

                <div class="flex items-center gap-1">
                    <button wire:click="openEdit({{ $redirect->id }})" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit redirect">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                    </button>
                    <button wire:click="delete({{ $redirect->id }})" wire:confirm="Delete this redirect?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete redirect">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-stone-500">
                {{ $search ? 'No redirects match your search.' : 'No URL redirects yet.' }}
            </li>
        @endforelse
    </ul>

        {{-- Pagination --}}
        <div class="mt-4">{{ $this->redirects->links() }}</div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Dialog --}}
            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Redirect' : 'New Redirect' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">From path</label>
                        <input type="text" wire:model="from_path" placeholder="/old-product-url" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="255">
                        <p class="mt-1 text-xs text-stone-500">Use a path from the old site, e.g. /shop/old-hoodie</p>
                        @error('from_path') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Destination URL or path</label>
                        <input type="text" wire:model="to_url" placeholder="/shop/new-hoodie or https://example.com/new" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="2048">
                        @error('to_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">HTTP Status</label>
                            <select wire:model="status_code" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                            </select>
                            @error('status_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-7">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-stone-300">
                            <label for="modal-is-active" class="text-sm font-medium">Active</label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" class="rounded bg-stone-800 px-4 py-2 text-sm font-medium text-white hover:bg-stone-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Redirect' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

