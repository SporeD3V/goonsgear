<?php

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\EditHistory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterCategory = '';

    public string $filterSales = '';

    public string $filterStock = '';

    public string $filterPreorder = '';

    public string $filterStockAlerts = '';

    public function mount(): void
    {
        $search = trim((string) request()->query('search', ''));
        if ($search !== '') {
            $this->search = $search;
        }

        $status = trim((string) request()->query('status', request()->query('filterStatus', '')));
        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $this->filterStatus = $status;
        }

        $category = (string) request()->query('category', request()->query('filterCategory', ''));
        if ($category !== '' && ctype_digit($category)) {
            $this->filterCategory = $category;
        }

        $sales = trim((string) request()->query('sales', request()->query('filterSales', '')));
        if (in_array($sales, ['never_sold', 'sold'], true)) {
            $this->filterSales = $sales;
        }

        $stock = trim((string) request()->query('stock', request()->query('filterStock', '')));
        if (in_array($stock, ['zero_stock', 'in_stock', 'low_stock', 'out_of_stock'], true)) {
            $this->filterStock = $stock;
        }

        $preorder = trim((string) request()->query('preorder', request()->query('filterPreorder', '')));
        if (in_array($preorder, ['only_preorder'], true)) {
            $this->filterPreorder = $preorder;
        }

        $stockAlerts = trim((string) request()->query('stock_alerts', request()->query('filterStockAlerts', '')));
        if (in_array($stockAlerts, ['waiting'], true)) {
            $this->filterStockAlerts = $stockAlerts;
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

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSales(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStock(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPreorder(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStockAlerts(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterStatus', 'filterCategory', 'filterSales', 'filterStock', 'filterPreorder', 'filterStockAlerts');
        $this->resetPage();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Catalog-wide counts for the tappable quick-filter tiles.
     *
     * @return array{active: int, draft: int, out_of_stock: int, alerts: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'active' => Product::query()->where('status', 'active')->count(),
            'draft' => Product::query()->where('status', 'draft')->count(),
            'out_of_stock' => Product::query()
                ->whereHas('variants', fn ($vq) => $vq->where('is_active', true))
                ->whereDoesntHave('variants', fn ($vq) => $vq
                    ->where('is_active', true)
                    ->where('stock_quantity', '>', 0))
                ->count(),
            'alerts' => Product::query()
                ->whereHas('stockAlertSubscriptions', fn ($q) => $q
                    ->where('stock_alert_subscriptions.is_active', true)
                    ->whereNull('stock_alert_subscriptions.notified_at'))
                ->count(),
        ];
    }

    /**
     * Toggle one of the quick-filter tiles above the list.
     */
    public function quickFilter(string $key): void
    {
        match ($key) {
            'active' => $this->filterStatus = $this->filterStatus === 'active' ? '' : 'active',
            'draft' => $this->filterStatus = $this->filterStatus === 'draft' ? '' : 'draft',
            'out_of_stock' => $this->filterStock = $this->filterStock === 'out_of_stock' ? '' : 'out_of_stock',
            'alerts' => $this->filterStockAlerts = $this->filterStockAlerts === 'waiting' ? '' : 'waiting',
            default => null,
        };

        $this->resetPage();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->with([
                'primaryCategory:id,name',
                'media' => fn ($q) => $q->where('is_primary', true)->limit(1),
            ])
            ->withCount(['variants', 'media', 'orderItems', 'editHistories'])
            ->withCount([
                'stockAlertSubscriptions as active_stock_alert_subscriptions_count' => function ($query) {
                    $query->where('stock_alert_subscriptions.is_active', true);
                },
                'variants as active_preorder_variants_count' => function ($query) {
                    $query->where('product_variants.is_active', true)
                        ->where('product_variants.is_preorder', true);
                },
            ])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('slug', 'like', "%{$this->search}%")
                        ->orWhere('excerpt', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterCategory !== '', fn ($query) => $query->where('primary_category_id', $this->filterCategory))
            ->when($this->filterSales === 'never_sold', fn ($query) => $query->whereDoesntHave('orderItems'))
            ->when($this->filterSales === 'sold', fn ($query) => $query->whereHas('orderItems'))
            ->when(
                $this->filterStock === 'zero_stock',
                fn ($query) => $query->whereDoesntHave('variants', fn ($vq) => $vq
                    ->where('is_active', true)
                    ->where('stock_quantity', '>', 0))
            )
            ->when(
                $this->filterStock === 'in_stock',
                fn ($query) => $query->whereHas('variants', fn ($vq) => $vq
                    ->where('is_active', true)
                    ->where('stock_quantity', '>', 0))
            )
            ->when(
                $this->filterStock === 'low_stock',
                fn ($query) => $query->whereHas('variants', fn ($vq) => $vq
                    ->where('is_active', true)
                    ->whereBetween('stock_quantity', [1, 5]))
            )
            ->when(
                $this->filterStock === 'out_of_stock',
                fn ($query) => $query
                    ->whereHas('variants', fn ($vq) => $vq->where('is_active', true))
                    ->whereDoesntHave('variants', fn ($vq) => $vq
                        ->where('is_active', true)
                        ->where('stock_quantity', '>', 0))
            )
            ->when(
                $this->filterPreorder === 'only_preorder',
                fn ($query) => $query->where(function ($pq) {
                    $pq->where('is_preorder', true)
                        ->orWhereHas('variants', fn ($vq) => $vq
                            ->where('is_active', true)
                            ->where('is_preorder', true));
                })
            )
            ->when(
                // Subscriptions are keyed by variant — go through the
                // hasManyThrough relation, not a (nonexistent) product_id.
                $this->filterStockAlerts === 'waiting',
                fn ($query) => $query->whereHas('stockAlertSubscriptions', fn ($q) => $q
                    ->where('stock_alert_subscriptions.is_active', true)
                    ->whereNull('stock_alert_subscriptions.notified_at'))
            )
            ->latest('id')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    /**
     * Return fields that have undo history for the current page of products.
     *
     * @return \Illuminate\Support\Collection<int, list<string>>
     */
    #[Computed]
    public function fieldsWithHistory(): \Illuminate\Support\Collection
    {
        $productIds = $this->products->pluck('id');

        return EditHistory::query()
            ->where('editable_type', Product::class)
            ->whereIn('editable_id', $productIds)
            ->selectRaw('editable_id, field')
            ->groupBy('editable_id', 'field')
            ->get()
            ->groupBy('editable_id')
            ->map(fn ($group) => $group->pluck('field')->toArray());
    }

    /**
     * Inline update a single field for a product.
     *
     * @return array{success: bool, value?: mixed, unchanged?: bool, error?: string}
     */
    public function inlineUpdate(int $productId, string $field, mixed $value): array
    {
        $allowedFields = ['name', 'slug', 'status', 'is_featured'];

        if (! in_array($field, $allowedFields, true)) {
            return ['success' => false, 'error' => 'Field not allowed.'];
        }

        $product = Product::findOrFail($productId);

        if ($field === 'is_featured') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($field === 'status' && ! in_array($value, ['draft', 'active', 'archived'], true)) {
            return ['success' => false, 'error' => 'Invalid status value.'];
        }

        if ($field === 'slug') {
            $value = Str::slug($value);
            $exists = Product::query()
                ->where('slug', $value)
                ->where('id', '!=', $product->id)
                ->exists();

            if ($exists) {
                return ['success' => false, 'error' => 'This slug is already in use.'];
            }
        }

        $oldValue = $product->getAttribute($field);

        if ($field === 'is_featured') {
            $oldValue = (bool) $oldValue;
        }

        if ((string) $oldValue === (string) $value) {
            return ['success' => true, 'unchanged' => true];
        }

        $product->update([$field => $value]);

        EditHistory::recordChange($product, $field, $oldValue, $value);

        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $product,
            "Inline updated {$field} on product \"{$product->name}\"",
        );

        unset($this->products, $this->fieldsWithHistory);

        return ['success' => true, 'value' => $product->getAttribute($field)];
    }

    /**
     * Revert a product field to its previous value.
     *
     * @return array{success: bool, value?: mixed, has_more_history?: bool, error?: string}
     */
    public function revertField(int $productId, string $field): array
    {
        if (! in_array($field, ['name', 'slug', 'status', 'is_featured'], true)) {
            return ['success' => false, 'error' => 'Field not allowed.'];
        }

        $product = Product::findOrFail($productId);
        $lastEdit = EditHistory::lastChange($product, $field);

        if (! $lastEdit) {
            return ['success' => false, 'error' => 'No edit history found for this field.'];
        }

        $revertValue = $lastEdit->old_value;

        if ($field === 'is_featured') {
            $revertValue = filter_var($revertValue, FILTER_VALIDATE_BOOLEAN);
        }

        $product->update([$field => $revertValue]);
        $lastEdit->delete();

        unset($this->products, $this->fieldsWithHistory);

        return [
            'success' => true,
            'value' => $product->getAttribute($field),
            'has_more_history' => EditHistory::hasHistory($product, $field),
        ];
    }

    public function deleteProduct(int $id): void
    {
        $product = Product::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $product, "Deleted product \"{$product->name}\"");
        $product->delete();
        unset($this->products);
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-stone-800">Products</h2>
            <p class="text-[13px] text-stone-500">{{ number_format($this->products->total()) }} {{ \Illuminate\Support\Str::plural('product', $this->products->total()) }} in current view</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New Product
        </a>
    </div>

    @php
        $productListNoteOptions = $this->products->getCollection()
            ->map(fn ($product) => [
                'key' => 'products-list::' . $product->id,
                'label' => 'Product - ' . $product->name,
                'value' => ucfirst($product->status),
                'meta' => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'status' => $product->status,
                    'slug' => $product->slug,
                ],
            ])
            ->values()
            ->all();

        $advancedFilterCount = collect([$this->filterCategory, $this->filterSales, $this->filterStock, $this->filterStockAlerts, $this->filterPreorder])
            ->filter(fn ($value) => $value !== '')
            ->count();
        $hasAnyFilter = $advancedFilterCount > 0 || $this->search !== '' || $this->filterStatus !== '';
    @endphp

    @include('admin._page-notes-card', [
        'context' => 'products-list',
        'label' => 'Products List',
        'anchorOptions' => $productListNoteOptions,
    ])

    {{-- Quick stats — tap to filter --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['key' => 'active', 'label' => 'Active', 'count' => $this->stats['active'], 'isOn' => $this->filterStatus === 'active', 'tone' => 'text-emerald-600'],
            ['key' => 'draft', 'label' => 'Drafts', 'count' => $this->stats['draft'], 'isOn' => $this->filterStatus === 'draft', 'tone' => 'text-amber-600'],
            ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'count' => $this->stats['out_of_stock'], 'isOn' => $this->filterStock === 'out_of_stock', 'tone' => 'text-red-600'],
            ['key' => 'alerts', 'label' => 'Alerts Waiting', 'count' => $this->stats['alerts'], 'isOn' => $this->filterStockAlerts === 'waiting', 'tone' => 'text-[#36a2eb]'],
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
                <div class="mt-0.5 text-[11px] {{ $tile['tone'] }}">{{ $tile['isOn'] ? 'Tap to clear filter' : 'Tap to filter' }}</div>
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm" data-delay="1"
         x-data="{ moreOpen: {{ $advancedFilterCount > 0 ? 'true' : 'false' }} }">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search name, slug, or excerpt…"
                       class="w-full rounded-lg border border-stone-200 py-2.5 pl-9 pr-3 text-sm text-stone-700 placeholder:text-stone-400 focus:border-[#36a2eb] focus:outline-none focus:ring-1 focus:ring-[#36a2eb]">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-stone-200 bg-stone-50 p-0.5">
                    @foreach (['' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $value => $label)
                        <button type="button"
                                wire:click="$set('filterStatus', '{{ $value }}')"
                                class="rounded-md px-3 py-1.5 text-[13px] font-medium transition {{ $this->filterStatus === $value ? 'bg-white text-[#36a2eb] shadow-sm' : 'text-stone-500 hover:text-stone-700' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        x-on:click="moreOpen = !moreOpen"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 px-3 py-2 text-[13px] font-medium text-stone-600 transition hover:bg-stone-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
                    Filters
                    @if ($advancedFilterCount > 0)
                        <span class="rounded-full bg-[#36a2eb] px-1.5 text-[11px] font-bold text-white">{{ $advancedFilterCount }}</span>
                    @endif
                </button>

                @if ($hasAnyFilter)
                    <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-2 text-[13px] font-medium text-stone-500 transition hover:bg-stone-50 hover:text-stone-700">
                        Reset
                    </button>
                @endif
            </div>
        </div>

        <div x-show="moreOpen" x-transition.origin.top x-cloak class="mt-3 grid gap-3 border-t border-stone-100 pt-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-500">Category</label>
                <select wire:model.live="filterCategory" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">All</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-500">Sales</label>
                <select wire:model.live="filterSales" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">All</option>
                    <option value="never_sold">Never sold</option>
                    <option value="sold">Has sales</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-500">Stock</label>
                <select wire:model.live="filterStock" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">All</option>
                    <option value="zero_stock">Zero stock</option>
                    <option value="in_stock">Has stock</option>
                    <option value="low_stock">Low stock (1-5)</option>
                    <option value="out_of_stock">Out of stock</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-500">Stock Alerts</label>
                <select wire:model.live="filterStockAlerts" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">All</option>
                    <option value="waiting">Waiting customers</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-500">Preorder</label>
                <select wire:model.live="filterPreorder" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 focus:border-[#36a2eb] focus:outline-none">
                    <option value="">All</option>
                    <option value="only_preorder">Only preorder</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Product list --}}
    <div class="admin-card overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-delay="2"
         wire:loading.class="pointer-events-none opacity-60"
         wire:target="search, filterStatus, filterCategory, filterSales, filterStock, filterPreorder, filterStockAlerts, quickFilter, resetFilters">

        {{-- Desktop column header --}}
        <div class="hidden gap-4 border-b border-stone-100 bg-stone-50/60 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-stone-500 md:grid md:grid-cols-[3.5rem_minmax(0,1fr)_8.5rem_4.5rem_8.5rem]">
            <span>Image</span>
            <span>Product</span>
            <span>Status</span>
            <span class="text-center">Featured</span>
            <span class="text-right">Actions</span>
        </div>

        <ul class="divide-y divide-stone-100">
            @forelse ($this->products as $product)
                @php
                    $primaryMedia = $product->media->first();
                    $productHistory = $this->fieldsWithHistory->get($product->id, []);
                @endphp
                <li wire:key="product-{{ $product->id }}" class="px-4 py-3 transition hover:bg-stone-50/60" x-data="{
                    id: {{ $product->id }},
                    name: @js($product->name),
                    slug: @js($product->slug),
                    status: @js($product->status),
                    isFeatured: {{ $product->is_featured ? 'true' : 'false' }},
                    editingField: null,
                    editValue: '',
                    saving: false,
                    error: '',
                    history: @js($productHistory),
                    async saveField(field, value) {
                        this.saving = true;
                        this.error = '';
                        const result = await $wire.inlineUpdate(this.id, field, value);
                        this.saving = false;
                        if (!result.success) {
                            this.error = result.error || 'Save failed';
                            return;
                        }
                        if (!result.unchanged) {
                            this[field === 'is_featured' ? 'isFeatured' : field] = result.value;
                            if (!this.history.includes(field)) {
                                this.history.push(field);
                            }
                        }
                        this.editingField = null;
                    },
                    async revertField(field) {
                        this.saving = true;
                        this.error = '';
                        const result = await $wire.revertField(this.id, field);
                        this.saving = false;
                        if (!result.success) {
                            this.error = result.error || 'Revert failed';
                            return;
                        }
                        this[field === 'is_featured' ? 'isFeatured' : field] = result.value;
                        if (!result.has_more_history) {
                            this.history = this.history.filter(f => f !== field);
                        }
                    },
                    startEdit(field) {
                        this.editingField = field;
                        this.editValue = this[field];
                        this.error = '';
                        this.$nextTick(() => {
                            const input = this.$refs[field + 'Input'];
                            if (input) { input.focus(); input.select(); }
                        });
                    }
                }">
                    <div class="flex items-start gap-3 md:grid md:grid-cols-[3.5rem_minmax(0,1fr)_8.5rem_4.5rem_8.5rem] md:items-center md:gap-4">
                        {{-- Image --}}
                        <div class="shrink-0">
                            @if ($primaryMedia)
                                <img src="{{ route('media.show', ['path' => $primaryMedia->getThumbnailPath()]) }}" alt="{{ $product->name }}" class="h-12 w-12 rounded-lg bg-stone-50 object-contain ring-1 ring-stone-100" loading="lazy">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-stone-100">
                                    <svg class="h-5 w-5 text-stone-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                                </div>
                            @endif
                        </div>

                        {{-- Product: name + slug (both inline editable) + meta chips --}}
                        <div class="min-w-0 flex-1">
                            <div x-show="editingField !== 'name'" class="group/cell flex items-center gap-1.5">
                                <button type="button" @click="startEdit('name')" class="truncate text-left text-sm font-semibold text-stone-800 hover:text-[#36a2eb]" x-text="name" title="Click to rename">{{ $product->name }}</button>
                                <button x-show="history.includes('name')" @click="revertField('name')" class="shrink-0 text-amber-500 hover:text-amber-600" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'name'" x-cloak class="flex items-center gap-1.5">
                                <input x-ref="nameInput" x-model="editValue" @keydown.enter="saveField('name', editValue)" @keydown.escape="editingField = null" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm focus:border-[#36a2eb] focus:outline-none" :disabled="saving">
                                <button @click="saveField('name', editValue)" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100" :disabled="saving" title="Save">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <button @click="editingField = null" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-stone-400 transition hover:bg-stone-100 hover:text-stone-600" title="Cancel">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p x-show="error && (editingField === 'name' || editingField === null)" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>

                            <div x-show="editingField !== 'slug'" class="mt-0.5 flex items-center gap-1.5">
                                <button type="button" @click="startEdit('slug')" class="truncate font-mono text-xs text-stone-400 hover:text-[#36a2eb]" x-text="slug" title="Click to edit slug">{{ $product->slug }}</button>
                                <button x-show="history.includes('slug')" @click="revertField('slug')" class="shrink-0 text-amber-500 hover:text-amber-600" title="Undo last change" x-cloak>
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'slug'" x-cloak class="mt-1 flex items-center gap-1.5">
                                <input x-ref="slugInput" x-model="editValue" @keydown.enter="saveField('slug', editValue)" @keydown.escape="editingField = null" class="w-full rounded-lg border border-stone-200 px-2.5 py-1 font-mono text-xs focus:border-[#36a2eb] focus:outline-none" :disabled="saving">
                                <button @click="saveField('slug', editValue)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100" :disabled="saving" title="Save">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <button @click="editingField = null" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-stone-400 transition hover:bg-stone-100 hover:text-stone-600" title="Cancel">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p x-show="error && editingField === 'slug'" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>

                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                @if ($product->primaryCategory)
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $product->primaryCategory->name }}</span>
                                @endif
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $product->variants_count }} {{ \Illuminate\Support\Str::plural('variant', $product->variants_count) }}</span>
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $product->media_count }} media</span>
                                @if ($product->is_preorder || $product->active_preorder_variants_count > 0)
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">Preorder</span>
                                @endif
                                @if ($product->active_stock_alert_subscriptions_count > 0)
                                    <a href="{{ route('admin.products.stock-alerts', $product) }}" class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700 transition hover:bg-amber-200" title="Customers waiting for stock alerts">
                                        {{ $product->active_stock_alert_subscriptions_count }} waiting
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Status (desktop) --}}
                        <div class="hidden md:block">
                            <div x-show="editingField !== 'status'">
                                <button type="button" @click="startEdit('status')"
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold transition hover:ring-1 hover:ring-stone-300"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': status === 'active',
                                        'bg-amber-100 text-amber-700': status === 'draft',
                                        'bg-stone-200 text-stone-600': status === 'archived'
                                    }"
                                    title="Click to change status"
                                    x-text="status.charAt(0).toUpperCase() + status.slice(1)">{{ ucfirst($product->status) }}</button>
                                <button x-show="history.includes('status')" @click="revertField('status')" class="ml-1 align-middle text-amber-500 hover:text-amber-600" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'status'" x-cloak>
                                <select x-ref="statusInput" x-model="editValue" @change="saveField('status', editValue)" @keydown.escape="editingField = null" class="rounded-lg border border-stone-200 px-2 py-1 text-xs focus:border-[#36a2eb] focus:outline-none" :disabled="saving">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <p x-show="error && editingField === 'status'" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>
                        </div>

                        {{-- Featured star (desktop) --}}
                        <div class="hidden justify-center md:flex">
                            <button type="button" @click="saveField('is_featured', !isFeatured)" :disabled="saving"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg transition hover:bg-stone-100"
                                    :class="isFeatured ? 'text-amber-400' : 'text-stone-300 hover:text-amber-400'"
                                    :title="isFeatured ? 'Featured — click to remove' : 'Click to feature'">
                                <svg class="h-5 w-5" :fill="isFeatured ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                            </button>
                        </div>

                        {{-- Actions (desktop) --}}
                        <div class="hidden items-center justify-end gap-1 md:flex">
                            <a href="{{ route('admin.products.edit', $product) }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Open full editor">
                                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                            </a>
                            <a href="{{ route('admin.products.variants.create', $product) }}" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-emerald-50 hover:text-emerald-600" title="Add variant">
                                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </a>
                            <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Delete this product?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete">
                                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Mobile action bar --}}
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 md:hidden">
                        <div class="flex items-center gap-2">
                            <div x-show="editingField !== 'status'">
                                <button type="button" @click="startEdit('status')"
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': status === 'active',
                                        'bg-amber-100 text-amber-700': status === 'draft',
                                        'bg-stone-200 text-stone-600': status === 'archived'
                                    }"
                                    x-text="status.charAt(0).toUpperCase() + status.slice(1)">{{ ucfirst($product->status) }}</button>
                            </div>
                            <div x-show="editingField === 'status'" x-cloak>
                                <select x-model="editValue" @change="saveField('status', editValue)" class="rounded-lg border border-stone-200 px-2 py-1 text-xs focus:border-[#36a2eb] focus:outline-none" :disabled="saving">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <button type="button" @click="saveField('is_featured', !isFeatured)" :disabled="saving"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg transition"
                                    :class="isFeatured ? 'text-amber-400' : 'text-stone-300'">
                                <svg class="h-5 w-5" :fill="isFeatured ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.products.edit', $product) }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-200 text-stone-500" title="Open full editor">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                            </a>
                            <a href="{{ route('admin.products.variants.create', $product) }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-200 text-stone-500" title="Add variant">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </a>
                            <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Delete this product?" class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-200 text-stone-500" title="Delete">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            </button>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-6 py-14 text-center">
                    <svg class="mx-auto h-10 w-10 text-stone-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                    <p class="mt-3 text-sm font-medium text-stone-600">
                        {{ $hasAnyFilter ? 'No products match these filters.' : 'No products yet.' }}
                    </p>
                    @if ($hasAnyFilter)
                        <button type="button" wire:click="resetFilters" class="mt-3 rounded-lg border border-stone-200 px-4 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-50">Clear filters</button>
                    @else
                        <a href="{{ route('admin.products.create') }}" class="mt-3 inline-block rounded-lg bg-[#36a2eb] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2b8ac9]">Create your first product</a>
                    @endif
                </li>
            @endforelse
        </ul>

        @if ($this->products->hasPages())
            <div class="border-t border-stone-100 px-4 py-3">{{ $this->products->links() }}</div>
        @endif
    </div>
</div>
