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
        <div class="flex items-center gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search country or reason…"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                New Rule
            </button>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div wire:loading.delay class="mb-2 text-xs text-slate-500">Loading…</div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach (['country_code' => 'Country', 'discount_type' => 'Type', 'discount_value' => 'Value'] as $field => $label)
                        <th wire:click="sortBy('{{ $field }}')" class="cursor-pointer border border-slate-200 px-3 py-2 text-left select-none hover:bg-slate-100">
                            {{ $label }}
                            @if ($sortField === $field)
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    @endforeach
                    <th class="border border-slate-200 px-3 py-2 text-left">Reason</th>
                    <th wire:click="sortBy('is_active')" class="cursor-pointer border border-slate-200 px-3 py-2 text-left select-none hover:bg-slate-100">
                        Status
                        @if ($sortField === 'is_active')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->discounts as $discount)
                    <tr wire:key="discount-{{ $discount->id }}" class="hover:bg-slate-50">
                        <td class="border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-900">{{ $this->countries[$discount->country_code] ?? $discount->country_code }}</p>
                            <p class="text-xs text-slate-500">{{ $discount->country_code }}</p>
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($discount->discount_type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($discount->discount_type === 'percent')
                                {{ rtrim(rtrim(number_format((float) $discount->discount_value, 2), '0'), '.') }}%
                            @else
                                ${{ number_format((float) $discount->discount_value, 2) }}
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2 max-w-xs truncate">{{ $discount->reason }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            <button wire:click="toggleActive({{ $discount->id }})" class="text-xs font-medium">
                                @if ($discount->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">Active</span>
                                @else
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">Inactive</span>
                                @endif
                            </button>
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <button wire:click="openEdit({{ $discount->id }})" class="text-blue-700 hover:underline">Edit</button>
                            <button wire:click="delete({{ $discount->id }})" wire:confirm="Delete this rule?" class="ml-2 text-red-700 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">
                            {{ $search ? 'No discounts match your search.' : 'No regional discount rules yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                        <select wire:model="country_code" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
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
                            <select wire:model="discount_type" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="fixed">Fixed</option>
                                <option value="percent">Percent</option>
                            </select>
                            @error('discount_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Value</label>
                            <input type="number" wire:model="discount_value" step="0.01" min="0.01" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('discount_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Reason <span class="text-xs text-slate-400">(shown to customer at checkout)</span></label>
                        <input type="text" wire:model="reason" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="500">
                        @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" id="modal-rd-active" class="h-4 w-4 rounded border-slate-300">
                        <label for="modal-rd-active" class="text-sm font-medium">Active</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Rule' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
