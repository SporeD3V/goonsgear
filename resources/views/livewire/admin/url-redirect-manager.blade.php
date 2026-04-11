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
        <div class="flex items-center gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search redirects…"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                New Redirect
            </button>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-slate-500">Loading…</div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach (['from_path' => 'From Path', 'to_url' => 'Destination', 'status_code' => 'Status', 'is_active' => 'Active'] as $field => $label)
                        <th wire:click="sortBy('{{ $field }}')" class="cursor-pointer border border-slate-200 px-3 py-2 text-left select-none hover:bg-slate-100">
                            {{ $label }}
                            @if ($sortField === $field)
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    @endforeach
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->redirects as $redirect)
                    <tr wire:key="redirect-{{ $redirect->id }}" class="hover:bg-slate-50">
                        <td class="border border-slate-200 px-3 py-2 font-medium text-slate-900">{{ $redirect->from_path }}</td>
                        <td class="border border-slate-200 px-3 py-2 break-all">{{ $redirect->to_url }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            <span @class([
                                'inline-block rounded px-2 py-0.5 text-xs font-medium',
                                'bg-purple-100 text-purple-800' => $redirect->status_code === 301,
                                'bg-amber-100 text-amber-800' => $redirect->status_code === 302,
                            ])>{{ $redirect->status_code }}</span>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            <button wire:click="toggleActive({{ $redirect->id }})" class="text-xs font-medium">
                                @if ($redirect->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">Active</span>
                                @else
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">Inactive</span>
                                @endif
                            </button>
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <button wire:click="openEdit({{ $redirect->id }})" class="text-blue-700 hover:underline">Edit</button>
                            <button wire:click="delete({{ $redirect->id }})" wire:confirm="Delete this redirect?" class="ml-2 text-red-700 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="border border-slate-200 px-3 py-6 text-center text-slate-500">
                            {{ $search ? 'No redirects match your search.' : 'No URL redirects yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                        <input type="text" wire:model="from_path" placeholder="/old-product-url" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="255">
                        <p class="mt-1 text-xs text-slate-500">Use a path from the old site, e.g. /shop/old-hoodie</p>
                        @error('from_path') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Destination URL or path</label>
                        <input type="text" wire:model="to_url" placeholder="/shop/new-hoodie or https://example.com/new" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="2048">
                        @error('to_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">HTTP Status</label>
                            <select wire:model="status_code" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                            </select>
                            @error('status_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-7">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-slate-300">
                            <label for="modal-is-active" class="text-sm font-medium">Active</label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Redirect' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
