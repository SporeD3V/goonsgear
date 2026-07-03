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

    public function mount(): void
    {
        $prefillProducts = $this->parsePrefillIds((string) request()->query('prefill_products', ''));

        if ($prefillProducts === []) {
            return;
        }

        $this->openCreate();
        $this->bundle_mode = request()->query('bundle_mode', 'product') === 'rule' ? 'rule' : 'product';

        $name = trim((string) request()->query('prefill_name', ''));
        if ($name !== '') {
            $this->name = $name;
        }

        $description = trim((string) request()->query('prefill_description', ''));
        if ($description !== '') {
            $this->description = $description;
        }

        if ($this->bundle_mode === 'product') {
            foreach ($prefillProducts as $productId) {
                $this->selectedProducts[$productId] = true;
                $this->productQuantities[$productId] = 1;
            }

            $prefillBundleProductId = (int) request()->query('prefill_bundle_product_id', 0);
            if ($prefillBundleProductId > 0) {
                $this->product_id = $prefillBundleProductId;
            }
        }
    }

    /**
     * @return list<int>
     */
    private function parsePrefillIds(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

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

<div class="space-y-6">
    {{-- Flash message --}}
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold">Bundle Discounts</h2>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search bundles…"
                class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                New Bundle
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-stone-500">Loading…</div>

        <div class="mb-2 flex flex-wrap items-center gap-2 text-[11px] text-stone-400">
            <span>Sort:</span>
            @foreach (['name' => 'Name', 'is_active' => 'Status'] as $field => $label)
                <button wire:click="sortBy('{{ $field }}')" class="rounded-full px-2 py-0.5 font-medium transition {{ $sortField === $field ? 'bg-[#36a2eb]/10 text-[#36a2eb]' : 'text-stone-500 hover:bg-stone-100' }}">
                    {{ $label }}@if ($sortField === $field) {{ $sortDirection === 'asc' ? '↑' : '↓' }}@endif
                </button>
            @endforeach
        </div>

        <ul class="divide-y divide-stone-100">
            @forelse ($this->bundleDiscounts as $bundle)
                <li wire:key="bundle-{{ $bundle->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 transition hover:bg-stone-50/60">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-stone-800">{{ $bundle->name }}</p>
                            <span class="text-sm font-bold text-red-600">
                                @if ($bundle->bundle_price !== null)
                                    &euro;{{ number_format((float) $bundle->bundle_price, 2) }}
                                @elseif ($bundle->discount_type === \App\Models\BundleDiscount::TYPE_PERCENT)
                                    {{ rtrim(rtrim(number_format((float) $bundle->discount_value, 2), '0'), '.') }}% off
                                @else
                                    &euro;{{ number_format((float) $bundle->discount_value, 2) }} off
                                @endif
                            </span>
                        </div>
                        @if ($bundle->description)
                            <p class="mt-0.5 truncate text-xs text-stone-400">{{ $bundle->description }}</p>
                        @endif
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                            @if ($bundle->bundle_price !== null)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-800">Fixed-price bundle</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">Discount rule</span>
                            @endif
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $bundle->items_count }} {{ \Illuminate\Support\Str::plural('item', $bundle->items_count) }}</span>
                        </div>
                    </div>

                    <button wire:click="toggleActive({{ $bundle->id }})" title="{{ $bundle->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition hover:ring-1 hover:ring-stone-300 {{ $bundle->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-500' }}">
                        {{ $bundle->is_active ? 'Active' : 'Inactive' }}
                    </button>

                    <div class="flex items-center gap-1">
                        <button wire:click="openEdit({{ $bundle->id }})" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit bundle">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                        </button>
                        <button wire:click="delete({{ $bundle->id }})" wire:confirm="Delete this bundle discount?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete bundle">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </li>
            @empty
                <li class="px-6 py-10 text-center text-sm text-stone-500">
                    {{ $search ? 'No bundles match your search.' : 'No bundle discounts yet.' }}
                </li>
            @endforelse
        </ul>

    {{-- Pagination --}}
        <div class="mt-4">{{ $this->bundleDiscounts->links() }}</div>
    </div>

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
                        class="rounded px-3 py-1.5 text-sm font-medium {{ $bundle_mode === 'product' ? 'bg-[#36a2eb] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' }}"
                    >
                        Product Bundle
                    </button>
                    <button
                        type="button"
                        wire:click="$set('bundle_mode', 'rule')"
                        class="rounded px-3 py-1.5 text-sm font-medium {{ $bundle_mode === 'rule' ? 'bg-[#36a2eb] text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' }}"
                    >
                        Discount Rule
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    {{-- Common: Name + Description --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium">Bundle name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="120">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <input type="text" wire:model="description" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="500">
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($bundle_mode === 'product')
                        {{-- PRODUCT BUNDLE MODE --}}

                        {{-- Link product page --}}
                        <div class="rounded-lg border border-stone-200 p-4">
                            <p class="mb-2 text-sm font-medium text-stone-900">Linked product page</p>
                            <p class="mb-3 text-xs text-stone-500">Link this bundle to an existing product so it has its own shop page with photos.</p>

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
                                    class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm"
                                >
                                @if (count($this->linkableProducts) > 0)
                                    <div class="mt-1 max-h-40 overflow-auto rounded-lg border border-stone-200 bg-white">
                                        @foreach ($this->linkableProducts as $option)
                                            <button
                                                type="button"
                                                wire:click="selectLinkedProduct({{ $option['id'] }})"
                                                wire:key="link-product-{{ $option['id'] }}"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-stone-50"
                                            >
                                                {{ $option['label'] }}
                                                <span class="text-xs text-stone-400">/shop/{{ $option['slug'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                            @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Bundle price + savings --}}
                        <div class="rounded-lg border border-stone-200 p-4">
                            <p class="mb-2 text-sm font-medium text-stone-900">Bundle pricing</p>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Bundle price (&euro;)</label>
                                    <input type="number" step="0.01" min="0.01" wire:model.live.debounce.500ms="bundle_price" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                    @error('bundle_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex items-center gap-2 pt-7">
                                    <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-stone-300">
                                    <label for="modal-is-active" class="text-sm font-medium">Active</label>
                                </div>
                            </div>

                            {{-- Live savings display --}}
                            @if ($this->componentTotal > 0)
                                <div class="mt-3 rounded bg-stone-50 p-3 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-stone-600">Components total:</span>
                                        <span class="font-medium">&euro;{{ number_format($this->componentTotal, 2) }}</span>
                                    </div>
                                    @if ((float) $bundle_price > 0)
                                        <div class="flex justify-between">
                                            <span class="text-stone-600">Bundle price:</span>
                                            <span class="font-medium">&euro;{{ number_format((float) $bundle_price, 2) }}</span>
                                        </div>
                                        <div class="mt-1 flex justify-between border-t border-stone-200 pt-1">
                                            <span class="font-medium text-emerald-700">Customer saves:</span>
                                            <span class="font-bold text-emerald-700">&euro;{{ number_format($this->calculatedSavings, 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Component products --}}
                        <div class="rounded-lg border border-stone-200 p-4">
                            <p class="mb-2 text-sm font-medium text-stone-900">Component products</p>
                            <p class="mb-3 text-xs text-stone-500">Select the products included in this bundle. Customers will choose specific variants (size/color) on the bundle page.</p>

                            @error('selectedProducts') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                            <input
                                wire:model.live.debounce.300ms="productSearch"
                                type="text"
                                placeholder="Search by product name…"
                                class="mb-3 w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm"
                            >

                            <div class="max-h-72 overflow-auto border border-stone-200">
                                <table class="admin-mobile-table min-w-full text-sm">
                                    <thead class="bg-stone-50 sticky top-0">
                                        <tr>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Use</th>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Product</th>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">From price</th>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Min Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->productOptions as $product)
                                            <tr wire:key="product-{{ $product['id'] }}">
                                                <td class="border-b border-stone-200 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="selectedProducts.{{ $product['id'] }}"
                                                        class="h-4 w-4 rounded border-stone-300"
                                                    >
                                                </td>
                                                <td class="border-b border-stone-200 px-3 py-2">{{ $product['label'] }}</td>
                                                <td class="border-b border-stone-200 px-3 py-2 text-stone-500">&euro;{{ number_format($product['price'], 2) }}</td>
                                                <td class="border-b border-stone-200 px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="99"
                                                        wire:model.live="productQuantities.{{ $product['id'] }}"
                                                        class="w-20 rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-2 py-1 text-sm"
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
                                <select wire:model="discount_type" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                    @foreach (\App\Models\BundleDiscount::supportedTypes() as $type)
                                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                @error('discount_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Discount value</label>
                                <input type="number" step="0.01" min="0.01" wire:model="discount_value" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                @error('discount_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active-rule" class="h-4 w-4 rounded border-stone-300">
                            <label for="modal-is-active-rule" class="text-sm font-medium">Active</label>
                        </div>

                        {{-- Variant requirements --}}
                        <div class="rounded-lg border border-stone-200 p-4">
                            <p class="mb-2 text-sm font-medium text-stone-900">Variant requirements</p>
                            <p class="mb-3 text-xs text-stone-500">Search and select variants required for this bundle. Set minimum quantities per variant.</p>

                            @error('selectedVariants') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

                            <input
                                wire:model.live.debounce.300ms="variantSearch"
                                type="text"
                                placeholder="Search by product name, variant, or SKU…"
                                class="mb-3 w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm"
                            >

                            <div class="max-h-72 overflow-auto border border-stone-200">
                                <table class="admin-mobile-table min-w-full text-sm">
                                    <thead class="bg-stone-50 sticky top-0">
                                        <tr>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Use</th>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Variant</th>
                                            <th class="border-b border-stone-200 px-3 py-2 text-left">Min Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->variantOptions as $variant)
                                            <tr wire:key="variant-{{ $variant['id'] }}">
                                                <td class="border-b border-stone-200 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="selectedVariants.{{ $variant['id'] }}"
                                                        class="h-4 w-4 rounded border-stone-300"
                                                    >
                                                </td>
                                                <td class="border-b border-stone-200 px-3 py-2">{{ $variant['label'] }}</td>
                                                <td class="border-b border-stone-200 px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="99"
                                                        wire:model="quantities.{{ $variant['id'] }}"
                                                        class="w-20 rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-2 py-1 text-sm"
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
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" class="rounded bg-stone-800 px-4 py-2 text-sm font-medium text-white hover:bg-stone-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Bundle' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

