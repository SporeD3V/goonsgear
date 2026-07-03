<?php

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use App\Models\RegionalDiscount;
use App\Support\Countries;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'country_code';
    public string $sortDirection = 'asc';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $country_code = '';
    public string $discount_type = 'fixed';
    public string $discount_value = '';
    public string $reason = '';
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
        $discount = RegionalDiscount::findOrFail($id);
        $this->editingId = $discount->id;
        $this->country_code = $discount->country_code;
        $this->discount_type = $discount->discount_type;
        $this->discount_value = (string) $discount->discount_value;
        $this->reason = $discount->reason ?? '';
        $this->is_active = $discount->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $uniqueRule = Rule::unique('regional_discounts', 'country_code');
        if ($this->editingId) {
            $uniqueRule = $uniqueRule->ignore($this->editingId);
        }

        $validated = $this->validate([
            'country_code' => ['required', 'string', 'size:2', $uniqueRule],
            'discount_type' => ['required', 'string', 'in:' . implode(',', RegionalDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['country_code'] = strtoupper(trim($validated['country_code']));

        if ($this->editingId) {
            $discount = RegionalDiscount::findOrFail($this->editingId);

            foreach (['country_code', 'discount_type', 'discount_value', 'reason', 'is_active'] as $field) {
                $oldValue = (string) $discount->getAttribute($field);
                $newValue = (string) $validated[$field];
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($discount, $field, $oldValue, $newValue);
                }
            }

            $discount->update($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $discount, "Updated regional discount for {$discount->country_code}");
            session()->flash('status', 'Regional discount updated.');
        } else {
            $discount = RegionalDiscount::create($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $discount, "Created regional discount for {$discount->country_code}");
            session()->flash('status', 'Regional discount created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $discount = RegionalDiscount::findOrFail($id);
        $oldValue = (string) $discount->is_active;
        $discount->update(['is_active' => ! $discount->is_active]);
        EditHistory::recordChange($discount, 'is_active', $oldValue, (string) $discount->is_active);
        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $discount,
            ($discount->is_active ? 'Activated' : 'Deactivated') . " regional discount for {$discount->country_code}"
        );
    }

    public function delete(int $id): void
    {
        $discount = RegionalDiscount::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $discount, "Deleted regional discount for {$discount->country_code}");
        $discount->delete();
        session()->flash('status', 'Regional discount deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->country_code = '';
        $this->discount_type = 'fixed';
        $this->discount_value = '';
        $this->reason = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    #[Computed]
    public function discounts()
    {
        $allowedSorts = ['country_code', 'discount_type', 'discount_value', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'country_code';
        $countries = Countries::all();

        return RegionalDiscount::query()
            ->when($this->search, function ($q) use ($countries) {
                $matchingCodes = collect($countries)
                    ->filter(fn ($name) => str_contains(strtolower($name), strtolower($this->search)))
                    ->keys()
                    ->all();

                $q->where(function ($q) use ($matchingCodes) {
                    $q->where('country_code', 'like', '%' . $this->search . '%')
                        ->orWhere('reason', 'like', '%' . $this->search . '%');
                    if ($matchingCodes) {
                        $q->orWhereIn('country_code', $matchingCodes);
                    }
                });
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(30);
    }

    #[Computed]
    public function countries(): array
    {
        return Countries::all();
    }
}; ?>

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold">Regional Discounts</h2>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search country or reason…"
                class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                New Rule
            </button>
        </div>
    </div>

    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <div wire:loading.delay class="mb-2 text-xs text-stone-500">Loading…</div>

    {{-- Rule list --}}
    <div class="mb-2 flex flex-wrap items-center gap-2 text-[11px] text-stone-400">
        <span>Sort:</span>
        @foreach (['country_code' => 'Country', 'discount_value' => 'Value', 'is_active' => 'Status'] as $field => $label)
            <button wire:click="sortBy('{{ $field }}')" class="rounded-full px-2 py-0.5 font-medium transition {{ $sortField === $field ? 'bg-[#36a2eb]/10 text-[#36a2eb]' : 'text-stone-500 hover:bg-stone-100' }}">
                {{ $label }}@if ($sortField === $field) {{ $sortDirection === 'asc' ? '↑' : '↓' }}@endif
            </button>
        @endforeach
    </div>

    <ul class="divide-y divide-stone-100">
        @forelse ($this->discounts as $discount)
            <li wire:key="discount-{{ $discount->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 transition hover:bg-stone-50/60">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-stone-800">{{ $this->countries[$discount->country_code] ?? $discount->country_code }}</p>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-medium text-stone-600">{{ $discount->country_code }}</span>
                        <span class="text-sm font-bold text-red-600">
                            @if ($discount->discount_type === 'percent')
                                {{ rtrim(rtrim(number_format((float) $discount->discount_value, 2), '0'), '.') }}% off
                            @else
                                &euro;{{ number_format((float) $discount->discount_value, 2) }} off
                            @endif
                        </span>
                    </div>
                    @if ($discount->reason)
                        <p class="mt-0.5 truncate text-xs text-stone-400">{{ $discount->reason }}</p>
                    @endif
                </div>

                <button wire:click="toggleActive({{ $discount->id }})" title="{{ $discount->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition hover:ring-1 hover:ring-stone-300 {{ $discount->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-500' }}">
                    {{ $discount->is_active ? 'Active' : 'Inactive' }}
                </button>

                <div class="flex items-center gap-1">
                    <button wire:click="openEdit({{ $discount->id }})" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit rule">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                    </button>
                    <button wire:click="delete({{ $discount->id }})" wire:confirm="Delete this rule?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete rule">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-stone-500">
                {{ $search ? 'No discounts match your search.' : 'No regional discount rules yet.' }}
            </li>
        @endforelse
    </ul>

        <div class="mt-4">{{ $this->discounts->links() }}</div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="fixed inset-0 bg-black/50" wire:click="closeModal"></div>

            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Regional Discount' : 'New Regional Discount' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Country</label>
                        <select wire:model="country_code" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                            <option value="">— Select country —</option>
                            @foreach ($this->countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        @error('country_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Discount type</label>
                            <select wire:model="discount_type" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                <option value="fixed">Fixed</option>
                                <option value="percent">Percent</option>
                            </select>
                            @error('discount_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Value</label>
                            <input type="number" wire:model="discount_value" step="0.01" min="0.01" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                            @error('discount_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Reason <span class="text-xs text-stone-400">(shown to customer at checkout)</span></label>
                        <input type="text" wire:model="reason" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="500">
                        @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" id="modal-rd-active" class="h-4 w-4 rounded border-stone-300">
                        <label for="modal-rd-active" class="text-sm font-medium">Active</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" class="rounded bg-stone-800 px-4 py-2 text-sm font-medium text-white hover:bg-stone-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Rule' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

