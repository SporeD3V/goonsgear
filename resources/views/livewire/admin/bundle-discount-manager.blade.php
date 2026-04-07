<?php

use App\Models\AdminActivityLog;
use App\Models\BundleDiscount;
use App\Models\EditHistory;
use App\Models\ProductVariant;
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

    public string $name = '';
    public string $description = '';
    public string $discount_type = BundleDiscount::TYPE_FIXED;
    public string $discount_value = '';
    public bool $is_active = true;

    /** @var array<int, bool> */
    public array $selectedVariants = [];

    /** @var array<int, int> */
    public array $quantities = [];

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
        $bundle = BundleDiscount::with('items')->findOrFail($id);
        $this->editingId = $bundle->id;
        $this->name = $bundle->name;
        $this->description = $bundle->description ?? '';
        $this->discount_type = $bundle->discount_type;
        $this->discount_value = (string) $bundle->discount_value;
        $this->is_active = $bundle->is_active;

        $this->selectedVariants = [];
        $this->quantities = [];
        foreach ($bundle->items as $item) {
            $variantId = (int) $item->product_variant_id;
            $this->selectedVariants[$variantId] = true;
            $this->quantities[$variantId] = (int) $item->min_quantity;
        }

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $uniqueRule = Rule::unique('bundle_discounts', 'name');
        if ($this->editingId) {
            $uniqueRule = $uniqueRule->ignore($this->editingId);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120', $uniqueRule],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', 'string', Rule::in(BundleDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
        ]);

        $variantIds = collect($this->selectedVariants)
            ->filter(fn (bool $selected): bool => $selected)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($variantIds->isEmpty()) {
            $this->addError('selectedVariants', 'Select at least one product variant.');
            return;
        }

        $variantItems = $variantIds->map(fn (int $variantId, int $position): array => [
            'product_variant_id' => $variantId,
            'min_quantity' => max(1, (int) ($this->quantities[$variantId] ?? 1)),
            'position' => $position,
        ])->all();

        if ($this->editingId) {
            $bundle = BundleDiscount::findOrFail($this->editingId);

            foreach (['name', 'description', 'discount_type', 'discount_value', 'is_active'] as $field) {
                $oldValue = (string) $bundle->getAttribute($field);
                $newValue = (string) $validated[$field];
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($bundle, $field, $oldValue, $newValue);
                }
            }

            $bundle->update($validated);
            $bundle->items()->delete();
            $bundle->items()->createMany($variantItems);

            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $bundle, "Updated bundle discount \"{$bundle->name}\"");
            session()->flash('status', 'Bundle discount updated.');
        } else {
            $bundle = BundleDiscount::create($validated);
            $bundle->items()->createMany($variantItems);

            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $bundle, "Created bundle discount \"{$bundle->name}\"");
            session()->flash('status', 'Bundle discount created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $bundle = BundleDiscount::findOrFail($id);
        $oldValue = (string) $bundle->is_active;
        $bundle->update(['is_active' => ! $bundle->is_active]);
        EditHistory::recordChange($bundle, 'is_active', $oldValue, (string) $bundle->is_active);
        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $bundle,
            ($bundle->is_active ? 'Activated' : 'Deactivated') . " bundle discount \"{$bundle->name}\""
        );
    }

    public function delete(int $id): void
    {
        $bundle = BundleDiscount::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $bundle, "Deleted bundle discount \"{$bundle->name}\"");
        $bundle->delete();
        session()->flash('status', 'Bundle discount deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->discount_type = BundleDiscount::TYPE_FIXED;
        $this->discount_value = '';
        $this->is_active = true;
        $this->selectedVariants = [];
        $this->quantities = [];
        $this->resetValidation();
    }

    #[Computed]
    public function bundleDiscounts()
    {
        $allowedSorts = ['id', 'name', 'discount_type', 'discount_value', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'id';

        return BundleDiscount::query()
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('description', 'like', '%' . $this->search . '%'))
            ->orderBy($sortField, $this->sortDirection)
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    #[Computed]
    public function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'product_id', 'name', 'sku'])
            ->map(fn (ProductVariant $variant): array => [
                'id' => (int) $variant->id,
                'label' => trim((string) $variant->product?->name) . ' - ' . $variant->name . ' (' . $variant->sku . ')',
            ])
            ->all();
    }
}; ?>

<div>
    {{-- Flash message --}}
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header row --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold">Bundle Discounts</h2>
        <div class="flex items-center gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search bundles…"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                New Rule
            </button>
        </div>
    </div>

    {{-- Loading indicator --}}
    <div wire:loading.delay class="mb-2 text-xs text-slate-500">Loading…</div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach (['name' => 'Name', 'discount_type' => 'Type', 'discount_value' => 'Value'] as $field => $label)
                        <th wire:click="sortBy('{{ $field }}')" class="cursor-pointer border border-slate-200 px-3 py-2 text-left select-none hover:bg-slate-100">
                            {{ $label }}
                            @if ($sortField === $field)
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    @endforeach
                    <th class="border border-slate-200 px-3 py-2 text-left">Items</th>
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
                @forelse ($this->bundleDiscounts as $bundle)
                    <tr wire:key="bundle-{{ $bundle->id }}" class="hover:bg-slate-50">
                        <td class="border border-slate-200 px-3 py-2">
                            <p class="font-medium text-slate-900">{{ $bundle->name }}</p>
                            @if ($bundle->description)
                                <p class="text-xs text-slate-500">{{ $bundle->description }}</p>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($bundle->discount_type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($bundle->discount_type === \App\Models\BundleDiscount::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $bundle->discount_value, 2), '0'), '.') }}%
                            @else
                                ${{ number_format((float) $bundle->discount_value, 2) }}
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ $bundle->items_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            <button wire:click="toggleActive({{ $bundle->id }})" class="text-xs font-medium">
                                @if ($bundle->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">Active</span>
                                @else
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">Inactive</span>
                                @endif
                            </button>
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <button wire:click="openEdit({{ $bundle->id }})" class="text-blue-700 hover:underline">Edit</button>
                            <button wire:click="delete({{ $bundle->id }})" wire:confirm="Delete this bundle discount?" class="ml-2 text-red-700 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-slate-200 px-3 py-6 text-center text-slate-500">
                            {{ $search ? 'No bundles match your search.' : 'No bundle discounts yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $this->bundleDiscounts->links() }}</div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Dialog --}}
            <div class="relative z-10 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Bundle Discount' : 'New Bundle Discount' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Bundle name</label>
                            <input type="text" wire:model="name" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="120">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Discount type</label>
                            <select wire:model="discount_type" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @foreach (\App\Models\BundleDiscount::supportedTypes() as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                            @error('discount_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Discount value</label>
                            <input type="number" step="0.01" min="0.01" wire:model="discount_value" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('discount_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center gap-2 pt-7">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-slate-300">
                            <label for="modal-is-active" class="text-sm font-medium">Active</label>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <input type="text" wire:model="description" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="500">
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Variant requirements --}}
                    <div class="rounded border border-slate-200 p-4">
                        <p class="mb-2 text-sm font-medium text-slate-900">Variant requirements</p>
                        <p class="mb-3 text-xs text-slate-500">Select one or more variants and set minimum quantities required for this bundle.</p>

                        @error('selectedVariants') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                        <div class="max-h-72 overflow-auto border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left">Use</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left">Variant</th>
                                        <th class="border-b border-slate-200 px-3 py-2 text-left">Min Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->variantOptions as $variant)
                                        <tr wire:key="variant-{{ $variant['id'] }}">
                                            <td class="border-b border-slate-200 px-3 py-2">
                                                <input
                                                    type="checkbox"
                                                    wire:model="selectedVariants.{{ $variant['id'] }}"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                >
                                            </td>
                                            <td class="border-b border-slate-200 px-3 py-2">{{ $variant['label'] }}</td>
                                            <td class="border-b border-slate-200 px-3 py-2">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="99"
                                                    wire:model="quantities.{{ $variant['id'] }}"
                                                    class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Bundle Discount' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
