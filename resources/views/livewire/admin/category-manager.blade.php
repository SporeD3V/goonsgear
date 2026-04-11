<?php

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\EditHistory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'sort_order';
    public string $sortDirection = 'asc';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $slug = '';
    public ?int $parent_id = null;
    public string $description = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public int $sort_order = 0;
    public string $size_type = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if (! $this->editingId) {
            $this->slug = str($value)->slug()->toString();
        }
    }

    public function updatedParentId(mixed $value): void
    {
        $this->parent_id = $value !== '' && $value !== null ? (int) $value : null;
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
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->parent_id = $category->parent_id;
        $this->description = $category->description ?? '';
        $this->meta_title = $category->meta_title ?? '';
        $this->meta_description = $category->meta_description ?? '';
        $this->sort_order = (int) $category->sort_order;
        $this->size_type = $category->size_type ?? '';
        $this->is_active = $category->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $nameUnique = Rule::unique('categories', 'name');
        $slugUnique = Rule::unique('categories', 'slug');

        if ($this->editingId) {
            $nameUnique = $nameUnique->ignore($this->editingId);
            $slugUnique = $slugUnique->ignore($this->editingId);
        }

        $parentRules = ['nullable', 'exists:categories,id'];
        if ($this->editingId) {
            $parentRules[] = Rule::notIn([$this->editingId]);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', $nameUnique],
            'slug' => ['required', 'string', 'max:255', $slugUnique],
            'parent_id' => $parentRules,
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'size_type' => ['nullable', 'string', 'in:top,bottom,shoe'],
            'is_active' => ['boolean'],
        ]);

        $validated['size_type'] = $validated['size_type'] ?: null;
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);

            foreach (['name', 'slug', 'is_active', 'parent_id', 'size_type'] as $field) {
                $oldValue = (string) $category->getAttribute($field);
                $newValue = (string) ($validated[$field] ?? '');
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($category, $field, $oldValue, $newValue);
                }
            }

            $category->update($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $category, "Updated category \"{$category->name}\"");
            session()->flash('status', 'Category updated.');
        } else {
            $category = Category::create($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $category, "Created category \"{$category->name}\"");
            session()->flash('status', 'Category created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $category = Category::findOrFail($id);
        $oldValue = (string) $category->is_active;
        $category->update(['is_active' => ! $category->is_active]);
        EditHistory::recordChange($category, 'is_active', $oldValue, (string) $category->is_active);
        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $category,
            ($category->is_active ? 'Activated' : 'Deactivated') . " category \"{$category->name}\""
        );
    }

    public function delete(int $id): void
    {
        $category = Category::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $category, "Deleted category \"{$category->name}\"");
        $category->delete();
        session()->flash('status', 'Category deleted.');
    }

    public function handleSort(int|string $id, int $position): void
    {
        $allIds = Category::orderBy('sort_order')->orderBy('name')->pluck('id')->toArray();

        $currentIdx = array_search((int) $id, $allIds);
        if ($currentIdx === false) {
            return;
        }

        array_splice($allIds, $currentIdx, 1);
        array_splice($allIds, $position, 0, [(int) $id]);

        foreach ($allIds as $index => $catId) {
            Category::where('id', $catId)->update(['sort_order' => $index]);
        }

        $this->dispatch('reorder-saved');
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
        $this->slug = '';
        $this->parent_id = null;
        $this->description = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->sort_order = 0;
        $this->size_type = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    #[Computed]
    public function categories()
    {
        $allowedSorts = ['sort_order', 'name', 'slug', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'sort_order';

        return Category::query()
            ->with('parent:id,name')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('slug', 'like', '%' . $this->search . '%'))
            ->orderBy($sortField, $this->sortDirection)
            ->when($sortField !== 'name', fn ($q) => $q->orderBy('name'))
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    #[Computed]
    public function parentOptions(): array
    {
        return Category::query()
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'name' => $c->name])
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
        <h2 class="text-lg font-semibold">Categories</h2>
        <div class="flex items-center gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search categories…"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                New Category
            </button>
        </div>
    </div>

    {{-- Sortable List --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <p class="mb-4 text-sm text-slate-500">Drag the handle to reorder categories. Changes are saved automatically.</p>

        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-slate-500">Loading…</div>

    {{-- Sortable list --}}
    <div wire:sort="handleSort" class="space-y-1">
        @forelse ($this->categories as $category)
            <div
                wire:key="category-{{ $category->id }}"
                wire:sort:item="{{ $category->id }}"
                class="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 {{ $category->parent_id ? 'ml-8' : '' }}"
            >
                <span wire:sort:handle class="cursor-grab text-slate-400 hover:text-slate-600 active:cursor-grabbing">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </span>
                <div class="flex-1">
                    <span class="font-medium">{{ $category->name }}</span>
                    <span class="ml-2 text-xs text-slate-400">/{{ $category->slug }}</span>
                    @if ($category->parent)
                        <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">{{ $category->parent->name }}</span>
                    @endif
                    @if ($category->size_type)
                        <span class="ml-2 rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-600">{{ $category->size_type }}</span>
                    @endif
                </div>
                <button wire:click="toggleActive({{ $category->id }})" class="text-xs font-medium">
                    @if ($category->is_active)
                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">Active</span>
                    @else
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-500">Inactive</span>
                    @endif
                </button>
                <button wire:click="openEdit({{ $category->id }})" class="text-sm text-blue-600 hover:underline">Edit</button>
                <button wire:click="delete({{ $category->id }})" wire:confirm="Delete this category?" class="text-sm text-red-600 hover:underline">Delete</button>
            </div>
        @empty
            <p class="py-8 text-center text-slate-500">
                {{ $search ? 'No categories match your search.' : 'No categories yet.' }}
            </p>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $this->categories->links() }}</div>
    </div>

    {{-- Reorder status toast --}}
    <div
        x-data="{ show: false, message: '' }"
        x-on:reorder-saved.window="show = true; message = 'Order saved'; setTimeout(() => show = false, 1500)"
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white shadow-lg"
        style="display: none"
    ></div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Dialog --}}
            <div class="relative z-10 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Category' : 'New Category' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input type="text" wire:model.live.debounce.300ms="name" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="255">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input type="text" wire:model="slug" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="255">
                            @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Parent Category</label>
                            <select wire:model="parent_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($this->parentOptions as $parent)
                                    <option value="{{ $parent['id'] }}">{{ $parent['name'] }}</option>
                                @endforeach
                            </select>
                            @error('parent_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Size Type</label>
                            <select wire:model="size_type" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                <option value="">None (not sized)</option>
                                <option value="top">Top (shirts, hoodies)</option>
                                <option value="bottom">Bottom (pants, shorts)</option>
                                <option value="shoe">Shoe (socks, footwear)</option>
                            </select>
                            @error('size_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Meta Title</label>
                            <input type="text" wire:model="meta_title" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="255">
                            @error('meta_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Sort Order</label>
                            <input type="number" min="0" wire:model="sort_order" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @error('sort_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Meta Description</label>
                        <textarea wire:model="meta_description" rows="2" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" maxlength="1000"></textarea>
                        @error('meta_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-slate-300">
                        <label for="modal-is-active" class="text-sm font-medium">Active</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Category' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
