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

    public function resetFilters(): void
    {
        $this->reset('search', 'filterStatus', 'filterCategory', 'filterSales', 'filterStock', 'filterPreorder');
        $this->resetPage();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get(['id', 'name']);
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
                $this->filterPreorder === 'only_preorder',
                fn ($query) => $query->where(function ($pq) {
                    $pq->where('is_preorder', true)
                        ->orWhereHas('variants', fn ($vq) => $vq
                            ->where('is_active', true)
                            ->where('is_preorder', true));
                })
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
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Products</h2>
        <a href="{{ route('admin.products.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Product</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Filters</h3>
        <div class="grid gap-3 md:grid-cols-7">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Search</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name / slug / excerpt" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
            <select wire:model.live="filterStatus" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (['draft', 'active', 'archived'] as $statusOption)
                    <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Category</label>
            <select wire:model.live="filterCategory" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Sales</label>
            <select wire:model.live="filterSales" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="never_sold">Never sold</option>
                <option value="sold">Has sales</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Stock</label>
            <select wire:model.live="filterStock" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="zero_stock">Zero stock</option>
                <option value="in_stock">Has stock</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Preorder</label>
            <select wire:model.live="filterPreorder" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="only_preorder">Only preorder</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="resetFilters" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Reset</button>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Image</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left lg:table-cell">Slug</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-center lg:table-cell">Featured</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left lg:table-cell">Primary Category</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left xl:table-cell">Variants</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left xl:table-cell">Media</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-center xl:table-cell" title="Stock alert subscriptions">Stock Alerts</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->products as $product)
                    @php
                        $primaryMedia = $product->media->first();
                        $productHistory = $this->fieldsWithHistory->get($product->id, []);
                    @endphp
                    <tr wire:key="product-{{ $product->id }}" x-data="{
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
                        {{-- Image --}}
                        <td class="border border-slate-200 px-2 py-2">
                            @if ($primaryMedia)
                                <img src="{{ route('media.show', ['path' => $primaryMedia->getThumbnailPath()]) }}" alt="{{ $product->name }}" class="h-12 w-12 rounded object-contain bg-slate-50" loading="lazy">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded bg-slate-100">
                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                                </div>
                            @endif
                        </td>

                        {{-- Name (inline editable) --}}
                        <td class="border border-slate-200 px-3 py-2">
                            <div x-show="editingField !== 'name'" class="group/cell flex items-center gap-1">
                                <span x-text="name" class="cursor-pointer hover:underline" @click="startEdit('name')">{{ $product->name }}</span>
                                <button @click="startEdit('name')" class="invisible text-slate-400 hover:text-slate-600 group-hover/cell:visible" title="Edit">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                </button>
                                <button x-show="history.includes('name')" @click="revertField('name')" class="text-amber-500 hover:text-amber-700" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'name'" x-cloak class="flex items-center gap-1">
                                <input x-ref="nameInput" x-model="editValue" @keydown.enter="saveField('name', editValue)" @keydown.escape="editingField = null" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" :disabled="saving">
                                <button @click="saveField('name', editValue)" class="text-emerald-600 hover:text-emerald-800" :disabled="saving">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <button @click="editingField = null" class="text-slate-400 hover:text-slate-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p x-show="error && editingField === 'name'" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>
                        </td>

                        {{-- Slug (inline editable) --}}
                        <td class="hidden border border-slate-200 px-3 py-2 lg:table-cell">
                            <div x-show="editingField !== 'slug'" class="group/cell flex items-center gap-1">
                                <span x-text="slug" class="cursor-pointer font-mono text-xs hover:underline" @click="startEdit('slug')">{{ $product->slug }}</span>
                                <button @click="startEdit('slug')" class="invisible text-slate-400 hover:text-slate-600 group-hover/cell:visible" title="Edit">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                </button>
                                <button x-show="history.includes('slug')" @click="revertField('slug')" class="text-amber-500 hover:text-amber-700" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'slug'" x-cloak class="flex items-center gap-1">
                                <input x-ref="slugInput" x-model="editValue" @keydown.enter="saveField('slug', editValue)" @keydown.escape="editingField = null" class="w-full rounded border border-slate-300 px-2 py-1 font-mono text-xs" :disabled="saving">
                                <button @click="saveField('slug', editValue)" class="text-emerald-600 hover:text-emerald-800" :disabled="saving">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <button @click="editingField = null" class="text-slate-400 hover:text-slate-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p x-show="error && editingField === 'slug'" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>
                        </td>

                        {{-- Status (inline editable) --}}
                        <td class="border border-slate-200 px-3 py-2">
                            <div x-show="editingField !== 'status'" class="group/cell flex items-center gap-1">
                                <span @click="startEdit('status')" class="cursor-pointer rounded px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': status === 'active',
                                        'bg-slate-100 text-slate-600': status === 'draft',
                                        'bg-orange-100 text-orange-700': status === 'archived'
                                    }"
                                    x-text="status.charAt(0).toUpperCase() + status.slice(1)">{{ ucfirst($product->status) }}</span>
                                <button @click="startEdit('status')" class="invisible text-slate-400 hover:text-slate-600 group-hover/cell:visible" title="Edit">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                </button>
                                <button x-show="history.includes('status')" @click="revertField('status')" class="text-amber-500 hover:text-amber-700" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                            <div x-show="editingField === 'status'" x-cloak class="flex items-center gap-1">
                                <select x-ref="statusInput" x-model="editValue" @change="saveField('status', editValue)" class="rounded border border-slate-300 px-2 py-1 text-xs" :disabled="saving">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <button @click="editingField = null" class="text-slate-400 hover:text-slate-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p x-show="error && editingField === 'status'" x-text="error" class="mt-1 text-xs text-red-600" x-cloak></p>
                        </td>

                        {{-- Featured (checkbox, live update) --}}
                        <td class="hidden border border-slate-200 px-3 py-2 text-center lg:table-cell">
                            <div class="flex items-center justify-center gap-1">
                                <input type="checkbox" :checked="isFeatured" @change="saveField('is_featured', $event.target.checked)" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" :disabled="saving">
                                <button x-show="history.includes('is_featured')" @click="revertField('is_featured')" class="text-amber-500 hover:text-amber-700" title="Undo last change" x-cloak>
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                </button>
                            </div>
                        </td>

                        <td class="hidden border border-slate-200 px-3 py-2 lg:table-cell">{{ $product->primaryCategory?->name ?? '-' }}</td>
                        <td class="hidden border border-slate-200 px-3 py-2 xl:table-cell">{{ $product->variants_count }}</td>
                        <td class="hidden border border-slate-200 px-3 py-2 xl:table-cell">{{ $product->media_count }}</td>
                        <td class="hidden border border-slate-200 px-3 py-2 text-center xl:table-cell">
                            @if ($product->active_stock_alert_subscriptions_count > 0)
                                <a href="{{ route('admin.products.stock-alerts', $product) }}" class="inline-block rounded bg-amber-100 px-2 py-1 text-amber-700 hover:bg-amber-200">
                                    {{ $product->active_stock_alert_subscriptions_count }}
                                </a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.products.variants.create', $product) }}" class="text-emerald-700 hover:underline">Add Variant</a>
                            <span class="mx-1 text-slate-300">|</span>
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-700 hover:underline">Edit</a>
                            <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Delete this product?" class="ml-2 text-red-700 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No products yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

        <div class="mt-4">{{ $this->products->links() }}</div>
    </div>
</div>
