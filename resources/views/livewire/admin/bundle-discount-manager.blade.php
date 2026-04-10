<?php

use App\Models\AdminActivityLog;
use App\Models\BundleDiscount;
use App\Models\Category;
use App\Models\EditHistory;
use App\Models\Product;
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

    // Mode: 'product' (new bundle-as-product) or 'rule' (legacy discount rule)
    public string $bundle_mode = 'product';

    // Common fields
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    // Product mode fields
    public string $bundle_price = '';
    public ?int $product_id = null;
    public string $productLinkSearch = '';

    /** @var array<int, bool> */
    public array $selectedProducts = [];

    /** @var array<int, int> */
    public array $productQuantities = [];

    public string $productSearch = '';

    // Rule mode fields (legacy)
    public string $discount_type = BundleDiscount::TYPE_FIXED;
    public string $discount_value = '';

    /** @var array<int, bool> */
    public array $selectedVariants = [];

    /** @var array<int, int> */
    public array $quantities = [];

    public string $variantSearch = '';

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
        $this->is_active = $bundle->is_active;

        // Detect mode from existing data
        if ($bundle->bundle_price !== null) {
            $this->bundle_mode = 'product';
            $this->bundle_price = (string) $bundle->bundle_price;
            $this->product_id = $bundle->product_id;

            $this->selectedProducts = [];
            $this->productQuantities = [];
            foreach ($bundle->items as $item) {
                if ($item->product_id) {
                    $productId = (int) $item->product_id;
                    $this->selectedProducts[$productId] = true;
                    $this->productQuantities[$productId] = (int) $item->min_quantity;
                }
            }
        } else {
            $this->bundle_mode = 'rule';
            $this->discount_type = $bundle->discount_type ?? BundleDiscount::TYPE_FIXED;
            $this->discount_value = (string) $bundle->discount_value;

            $this->selectedVariants = [];
            $this->quantities = [];
            foreach ($bundle->items as $item) {
                if ($item->product_variant_id) {
                    $variantId = (int) $item->product_variant_id;
                    $this->selectedVariants[$variantId] = true;
                    $this->quantities[$variantId] = (int) $item->min_quantity;
                }
            }
        }

        $this->resetValidation();
        $this->showModal = true;
    }

    public function selectLinkedProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->productLinkSearch = '';

        // Auto-populate bundle_price from the product's cheapest active variant
        if ($this->bundle_price === '') {
            $cheapestPrice = ProductVariant::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->min('price');

            if ($cheapestPrice !== null) {
                $this->bundle_price = number_format((float) $cheapestPrice, 2, '.', '');
            }
        }
    }

    public function clearLinkedProduct(): void
    {
        $this->product_id = null;
    }

    public function save(): void
    {
        $uniqueRule = Rule::unique('bundle_discounts', 'name');
        if ($this->editingId) {
            $uniqueRule = $uniqueRule->ignore($this->editingId);
        }

        if ($this->bundle_mode === 'product') {
            $this->saveProductBundle($uniqueRule);
        } else {
            $this->saveRuleBundle($uniqueRule);
        }
    }

    private function saveProductBundle(mixed $uniqueRule): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120', $uniqueRule],
            'description' => ['nullable', 'string', 'max:500'],
            'bundle_price' => ['required', 'numeric', 'min:0.01'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'is_active' => ['boolean'],
        ]);

        $productIds = collect($this->selectedProducts)
            ->filter(fn (mixed $selected): bool => (bool) $selected)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($productIds->isEmpty()) {
            $this->addError('selectedProducts', 'Select at least one component product.');
            return;
        }

        $items = $productIds->map(fn (int $productId, int $position): array => [
            'product_id' => $productId,
            'product_variant_id' => null,
            'min_quantity' => max(1, (int) ($this->productQuantities[$productId] ?? 1)),
            'position' => $position,
        ])->all();

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'bundle_price' => $validated['bundle_price'],
            'product_id' => $validated['product_id'],
            'discount_type' => null,
            'discount_value' => null,
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            $bundle = BundleDiscount::findOrFail($this->editingId);

            foreach (['name', 'description', 'bundle_price', 'product_id', 'is_active'] as $field) {
                $oldValue = (string) $bundle->getAttribute($field);
                $newValue = (string) ($data[$field] ?? '');
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($bundle, $field, $oldValue, $newValue);
                }
            }

            $bundle->update($data);
            $bundle->items()->delete();
            $bundle->items()->createMany($items);

            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $bundle, "Updated bundle discount \"{$bundle->name}\"");
            $this->ensureSaleCategory($bundle);
            session()->flash('status', 'Bundle updated.');
        } else {
            $bundle = BundleDiscount::create($data);
            $bundle->items()->createMany($items);

            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $bundle, "Created bundle discount \"{$bundle->name}\"");
            $this->ensureSaleCategory($bundle);
            session()->flash('status', 'Bundle created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    private function ensureSaleCategory(BundleDiscount $bundle): void
    {
        if (! $bundle->product_id || ! $bundle->is_active) {
            return;
        }

        $bundlesCategory = Category::where('slug', 'bundles')
            ->whereHas('parent', fn ($q) => $q->where('slug', 'sale'))
            ->first();

        $categoryId = $bundlesCategory?->id
            ?? Category::where('slug', 'sale')->first()?->id;

        if ($categoryId) {
            Product::find($bundle->product_id)?->categories()->syncWithoutDetaching([$categoryId]);
        }
    }

    private function saveRuleBundle(mixed $uniqueRule): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120', $uniqueRule],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', 'string', Rule::in(BundleDiscount::supportedTypes())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
        ]);

        $variantIds = collect($this->selectedVariants)
            ->filter(fn (mixed $selected): bool => (bool) $selected)
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

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'bundle_price' => null,
            'product_id' => null,
            'is_active' => $validated['is_active'],
        ];

        if ($this->editingId) {
            $bundle = BundleDiscount::findOrFail($this->editingId);

            foreach (['name', 'description', 'discount_type', 'discount_value', 'is_active'] as $field) {
                $oldValue = (string) $bundle->getAttribute($field);
                $newValue = (string) ($data[$field] ?? '');
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($bundle, $field, $oldValue, $newValue);
                }
            }

            $bundle->update($data);
            $bundle->items()->delete();
            $bundle->items()->createMany($variantItems);

            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $bundle, "Updated bundle discount \"{$bundle->name}\"");
            session()->flash('status', 'Bundle discount updated.');
        } else {
            $bundle = BundleDiscount::create($data);
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
        $this->bundle_mode = 'product';
        $this->name = '';
        $this->description = '';
        $this->is_active = true;
        $this->bundle_price = '';
        $this->product_id = null;
        $this->productLinkSearch = '';
        $this->selectedProducts = [];
        $this->productQuantities = [];
        $this->productSearch = '';
        $this->discount_type = BundleDiscount::TYPE_FIXED;
        $this->discount_value = '';
        $this->selectedVariants = [];
        $this->quantities = [];
        $this->variantSearch = '';
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
    public function linkedProduct(): ?Product
    {
        if ($this->product_id === null) {
            return null;
        }

        return Product::find($this->product_id);
    }

    #[Computed]
    public function linkableProducts(): array
    {
        if ($this->productLinkSearch === '') {
            return [];
        }

        $search = $this->productLinkSearch;

        return Product::query()
            ->where('status', 'active')
            ->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'label' => $product->name,
                'slug' => $product->slug,
            ])
            ->all();
    }

    #[Computed]
    public function productOptions(): array
    {
        $selectedIds = collect($this->selectedProducts)
            ->filter(fn (mixed $selected): bool => (bool) $selected)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = Product::query()
            ->where('status', 'active')
            ->orderBy('name');

        if ($this->productSearch !== '') {
            $search = $this->productSearch;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        $searchResults = $query->limit(50)
            ->get(['id', 'name']);

        if ($selectedIds !== []) {
            $alreadyFoundIds = $searchResults->pluck('id')->all();
            $missingSelectedIds = array_diff($selectedIds, $alreadyFoundIds);

            if ($missingSelectedIds !== []) {
                $selectedProducts = Product::query()
                    ->whereIn('id', $missingSelectedIds)
                    ->get(['id', 'name']);

                $searchResults = $selectedProducts->merge($searchResults);
            }
        }

        // Get cheapest active variant price per product for savings display
        $productIds = $searchResults->pluck('id')->all();
        $cheapestPrices = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->selectRaw('product_id, MIN(price) as min_price')
            ->groupBy('product_id')
            ->pluck('min_price', 'product_id')
            ->all();

        return $searchResults
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'label' => $product->name,
                'price' => (float) ($cheapestPrices[$product->id] ?? 0),
                'selected' => in_array((int) $product->id, $selectedIds, true),
            ])
            ->sortByDesc('selected')
            ->values()
            ->all();
    }

    #[Computed]
    public function componentTotal(): float
    {
        $selectedIds = collect($this->selectedProducts)
            ->filter(fn (mixed $selected): bool => (bool) $selected)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        if ($selectedIds === []) {
            return 0.0;
        }

        $cheapestPrices = ProductVariant::query()
            ->whereIn('product_id', $selectedIds)
            ->where('is_active', true)
            ->selectRaw('product_id, MIN(price) as min_price')
            ->groupBy('product_id')
            ->pluck('min_price', 'product_id');

        return (float) collect($selectedIds)->sum(function (int $productId) use ($cheapestPrices): float {
            $price = (float) ($cheapestPrices[$productId] ?? 0);
            $qty = max(1, (int) ($this->productQuantities[$productId] ?? 1));
            return $price * $qty;
        });
    }

    #[Computed]
    public function calculatedSavings(): float
    {
        $total = $this->componentTotal;
        $price = (float) $this->bundle_price;

        if ($total <= 0 || $price <= 0) {
            return 0.0;
        }

        return max(0.0, round($total - $price, 2));
    }

    #[Computed]
    public function variantOptions(): array
    {
        $selectedIds = collect($this->selectedVariants)
            ->filter(fn (mixed $selected): bool => (bool) $selected)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id');

        if ($this->variantSearch !== '') {
            $search = $this->variantSearch;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', '%' . $search . '%'));
            });
        }

        $searchResults = $query->limit(50)
            ->get(['id', 'product_id', 'name', 'sku']);

        if ($selectedIds !== []) {
            $alreadyFoundIds = $searchResults->pluck('id')->all();
            $missingSelectedIds = array_diff($selectedIds, $alreadyFoundIds);

            if ($missingSelectedIds !== []) {
                $selectedVariants = ProductVariant::query()
                    ->with('product:id,name')
                    ->whereIn('id', $missingSelectedIds)
                    ->get(['id', 'product_id', 'name', 'sku']);

                $searchResults = $selectedVariants->merge($searchResults);
            }
        }

        return $searchResults
            ->map(fn (ProductVariant $variant): array => [
                'id' => (int) $variant->id,
                'label' => trim((string) $variant->product?->name) . ' - ' . $variant->name . ' (' . $variant->sku . ')',
                'selected' => in_array((int) $variant->id, $selectedIds, true),
            ])
            ->sortByDesc('selected')
            ->values()
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
                New Bundle
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
                    <th wire:click="sortBy('name')" class="cursor-pointer border border-slate-200 px-3 py-2 text-left select-none hover:bg-slate-100">
                        Name
                        @if ($sortField === 'name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Mode</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Price / Discount</th>
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
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($bundle->bundle_price !== null)
                                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Bundle</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Rule</span>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($bundle->bundle_price !== null)
                                &euro;{{ number_format((float) $bundle->bundle_price, 2) }}
                            @elseif ($bundle->discount_type === \App\Models\BundleDiscount::TYPE_PERCENT)
                                {{ rtrim(rtrim(number_format((float) $bundle->discount_value, 2), '0'), '.') }}%
                            @else
                                &euro;{{ number_format((float) $bundle->discount_value, 2) }}
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
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Bundle' : 'New Bundle' }}</h3>

                {{-- Mode toggle --}}
                <div class="mb-4 flex gap-2">
                    <button
                        type="button"
                        wire:click="$set('bundle_mode', 'product')"
                        class="rounded px-3 py-1.5 text-sm font-medium {{ $bundle_mode === 'product' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        Product Bundle
                    </button>
                    <button
                        type="button"
                        wire:click="$set('bundle_mode', 'rule')"
                        class="rounded px-3 py-1.5 text-sm font-medium {{ $bundle_mode === 'rule' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        Discount Rule
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    {{-- Common: Name + Description --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium">Bundle name</label>
                        <input type="text" wire:model="name" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="120">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <input type="text" wire:model="description" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="500">
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($bundle_mode === 'product')
                        {{-- PRODUCT BUNDLE MODE --}}

                        {{-- Link product page --}}
                        <div class="rounded border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-medium text-slate-900">Linked product page</p>
                            <p class="mb-3 text-xs text-slate-500">Link this bundle to an existing product so it has its own shop page with photos.</p>

                            @if ($this->linkedProduct)
                                <div class="flex items-center justify-between rounded bg-blue-50 px-3 py-2">
                                    <div>
                                        <span class="text-sm font-medium text-blue-900">{{ $this->linkedProduct->name }}</span>
                                        <span class="ml-2 text-xs text-blue-600">/shop/{{ $this->linkedProduct->slug }}</span>
                                    </div>
                                    <button type="button" wire:click="clearLinkedProduct" class="text-xs text-red-600 hover:underline">Remove</button>
                                </div>
                            @else
                                <input
                                    wire:model.live.debounce.300ms="productLinkSearch"
                                    type="text"
                                    placeholder="Search product by name…"
                                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                >
                                @if (count($this->linkableProducts) > 0)
                                    <div class="mt-1 max-h-40 overflow-auto rounded border border-slate-200 bg-white">
                                        @foreach ($this->linkableProducts as $option)
                                            <button
                                                type="button"
                                                wire:click="selectLinkedProduct({{ $option['id'] }})"
                                                wire:key="link-product-{{ $option['id'] }}"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                                            >
                                                {{ $option['label'] }}
                                                <span class="text-xs text-slate-400">/shop/{{ $option['slug'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                            @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Bundle price + savings --}}
                        <div class="rounded border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-medium text-slate-900">Bundle pricing</p>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Bundle price (&euro;)</label>
                                    <input type="number" step="0.01" min="0.01" wire:model.live.debounce.500ms="bundle_price" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    @error('bundle_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex items-center gap-2 pt-7">
                                    <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-slate-300">
                                    <label for="modal-is-active" class="text-sm font-medium">Active</label>
                                </div>
                            </div>

                            {{-- Live savings display --}}
                            @if ($this->componentTotal > 0)
                                <div class="mt-3 rounded bg-slate-50 p-3 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">Components total:</span>
                                        <span class="font-medium">&euro;{{ number_format($this->componentTotal, 2) }}</span>
                                    </div>
                                    @if ((float) $bundle_price > 0)
                                        <div class="flex justify-between">
                                            <span class="text-slate-600">Bundle price:</span>
                                            <span class="font-medium">&euro;{{ number_format((float) $bundle_price, 2) }}</span>
                                        </div>
                                        <div class="mt-1 flex justify-between border-t border-slate-200 pt-1">
                                            <span class="font-medium text-emerald-700">Customer saves:</span>
                                            <span class="font-bold text-emerald-700">&euro;{{ number_format($this->calculatedSavings, 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Component products --}}
                        <div class="rounded border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-medium text-slate-900">Component products</p>
                            <p class="mb-3 text-xs text-slate-500">Select the products included in this bundle. Customers will choose specific variants (size/color) on the bundle page.</p>

                            @error('selectedProducts') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                            <input
                                wire:model.live.debounce.300ms="productSearch"
                                type="text"
                                placeholder="Search by product name…"
                                class="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            >

                            <div class="max-h-72 overflow-auto border border-slate-200">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 sticky top-0">
                                        <tr>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left">Use</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left">Product</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left">From price</th>
                                            <th class="border-b border-slate-200 px-3 py-2 text-left">Min Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->productOptions as $product)
                                            <tr wire:key="product-{{ $product['id'] }}">
                                                <td class="border-b border-slate-200 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="selectedProducts.{{ $product['id'] }}"
                                                        class="h-4 w-4 rounded border-slate-300"
                                                    >
                                                </td>
                                                <td class="border-b border-slate-200 px-3 py-2">{{ $product['label'] }}</td>
                                                <td class="border-b border-slate-200 px-3 py-2 text-slate-500">&euro;{{ number_format($product['price'], 2) }}</td>
                                                <td class="border-b border-slate-200 px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="99"
                                                        wire:model.live="productQuantities.{{ $product['id'] }}"
                                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                                    >
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    @else
                        {{-- DISCOUNT RULE MODE (legacy) --}}

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Discount type</label>
                                <select wire:model="discount_type" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    @foreach (\App\Models\BundleDiscount::supportedTypes() as $type)
                                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                @error('discount_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Discount value</label>
                                <input type="number" step="0.01" min="0.01" wire:model="discount_value" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                @error('discount_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active-rule" class="h-4 w-4 rounded border-slate-300">
                            <label for="modal-is-active-rule" class="text-sm font-medium">Active</label>
                        </div>

                        {{-- Variant requirements --}}
                        <div class="rounded border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-medium text-slate-900">Variant requirements</p>
                            <p class="mb-3 text-xs text-slate-500">Search and select variants required for this bundle. Set minimum quantities per variant.</p>

                            @error('selectedVariants') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                            <input
                                wire:model.live.debounce.300ms="variantSearch"
                                type="text"
                                placeholder="Search by product name, variant, or SKU…"
                                class="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            >

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
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Bundle' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
