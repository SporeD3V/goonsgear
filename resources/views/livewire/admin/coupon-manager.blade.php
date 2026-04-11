<?php

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\EditHistory;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    // Form fields
    public string $code = '';

    public ?string $description = '';

    public string $type = Coupon::TYPE_FIXED;

    public ?string $value = '';

    public ?string $minimum_subtotal = '';

    public ?string $usage_limit = '';

    public int $used_count = 0;

    public ?string $starts_at = '';

    public ?string $ends_at = '';

    public bool $is_active = true;

    public bool $is_stackable = false;

    public ?string $stack_group = '';

    public string $scope_type = Coupon::SCOPE_ALL;

    public ?int $scope_product_id = null;

    public ?int $scope_category_id = null;

    public ?int $scope_tag_id = null;

    public bool $is_personal = false;

    /** @var list<int> */
    public array $assigned_user_ids = [];

    public ?string $user_usage_limit = '';

    public string $userSearch = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedScopeType(): void
    {
        $this->scope_product_id = null;
        $this->scope_category_id = null;
        $this->scope_tag_id = null;
    }

    #[Computed]
    public function coupons(): LengthAwarePaginator
    {
        return Coupon::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('code', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate((int) config('pagination.admin_per_page', 20));
    }

    #[Computed]
    public function products(): Collection
    {
        return Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'name']);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function tags(): Collection
    {
        return Tag::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
    }

    #[Computed]
    public function searchableUsers(): Collection
    {
        if ($this->userSearch === '') {
            // When editing, show already-assigned users even without search
            if ($this->assigned_user_ids !== []) {
                return User::query()
                    ->whereIn('id', $this->assigned_user_ids)
                    ->orderBy('email')
                    ->get(['id', 'email']);
            }

            return new Collection;
        }

        $query = User::query()
            ->where('email', 'like', '%' . $this->userSearch . '%')
            ->orderBy('email')
            ->limit(100)
            ->get(['id', 'email']);

        // Merge already-assigned so they remain visible
        if ($this->assigned_user_ids !== []) {
            $assigned = User::query()
                ->whereIn('id', $this->assigned_user_ids)
                ->orderBy('email')
                ->get(['id', 'email']);

            return $query->merge($assigned)->unique('id')->sortBy('email')->values();
        }

        return $query;
    }

    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->load(['users' => fn ($query) => $query->select(['users.id', 'users.email'])->orderBy('users.email')]);

        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? '';
        $this->type = $coupon->type;
        $this->value = (string) $coupon->value;
        $this->minimum_subtotal = $coupon->minimum_subtotal !== null ? (string) $coupon->minimum_subtotal : '';
        $this->usage_limit = $coupon->usage_limit !== null ? (string) $coupon->usage_limit : '';
        $this->used_count = (int) $coupon->used_count;
        $this->starts_at = $coupon->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $coupon->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->is_active = $coupon->is_active;
        $this->is_stackable = $coupon->is_stackable;
        $this->stack_group = $coupon->stack_group ?? '';
        $this->scope_type = $coupon->scope_type ?? Coupon::SCOPE_ALL;
        $this->scope_product_id = $coupon->scope_type === Coupon::SCOPE_PRODUCT ? $coupon->scope_id : null;
        $this->scope_category_id = $coupon->scope_type === Coupon::SCOPE_CATEGORY ? $coupon->scope_id : null;
        $this->scope_tag_id = $coupon->scope_type === Coupon::SCOPE_TAG ? $coupon->scope_id : null;
        $this->is_personal = $coupon->is_personal;
        $this->assigned_user_ids = $coupon->users->pluck('id')->all();
        $this->user_usage_limit = '';
        $this->userSearch = '';

        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Coupon::supportedTypes())],
            'value' => ['required', 'numeric', 'min:0.01'],
            'minimum_subtotal' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'used_count' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_stackable' => ['boolean'],
            'stack_group' => ['nullable', 'string', 'max:50'],
            'scope_type' => ['nullable', 'string', Rule::in(Coupon::supportedScopes())],
            'is_personal' => ['boolean'],
            'user_usage_limit' => ['nullable', 'integer', 'min:1'],
        ];

        // Unique code rule
        $uniqueRule = Rule::unique('coupons', 'code');
        if ($this->editingId) {
            $uniqueRule = $uniqueRule->ignore($this->editingId);
        }
        $rules['code'][] = $uniqueRule;

        // Conditional scope rules
        if ($this->scope_type === Coupon::SCOPE_PRODUCT) {
            $rules['scope_product_id'] = ['required', 'integer', 'exists:products,id'];
        }
        if ($this->scope_type === Coupon::SCOPE_CATEGORY) {
            $rules['scope_category_id'] = ['required', 'integer', 'exists:categories,id'];
        }
        if ($this->scope_type === Coupon::SCOPE_TAG) {
            $rules['scope_tag_id'] = ['required', 'integer', 'exists:tags,id'];
        }

        // Personal coupon requires assigned users
        if ($this->is_personal) {
            $rules['assigned_user_ids'] = ['required', 'array', 'min:1'];
            $rules['assigned_user_ids.*'] = ['integer', 'exists:users,id'];
        }

        $this->validate($rules);

        $scopeId = match ($this->scope_type) {
            Coupon::SCOPE_PRODUCT => $this->scope_product_id,
            Coupon::SCOPE_CATEGORY => $this->scope_category_id,
            Coupon::SCOPE_TAG => $this->scope_tag_id,
            default => null,
        };

        $data = [
            'code' => Str::upper(trim($this->code)),
            'description' => $this->description ?: null,
            'type' => $this->type,
            'value' => $this->value,
            'minimum_subtotal' => $this->minimum_subtotal !== '' ? $this->minimum_subtotal : null,
            'usage_limit' => $this->usage_limit !== '' ? (int) $this->usage_limit : null,
            'used_count' => $this->used_count,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'is_active' => $this->is_active,
            'is_stackable' => $this->is_stackable,
            'stack_group' => trim($this->stack_group ?? '') !== '' ? trim($this->stack_group) : null,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_type === Coupon::SCOPE_ALL ? null : $scopeId,
            'is_personal' => $this->is_personal,
        ];

        if ($this->editingId) {
            $coupon = Coupon::findOrFail($this->editingId);

            foreach (['code', 'type', 'value', 'is_active', 'is_stackable', 'stack_group', 'scope_type', 'scope_id', 'is_personal', 'minimum_subtotal', 'usage_limit', 'starts_at', 'ends_at'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                $oldValue = $coupon->getOriginal($field);
                $newValue = $data[$field];
                $oldStr = is_bool($oldValue) ? ($oldValue ? '1' : '0') : (string) ($oldValue ?? '');
                $newStr = is_bool($newValue) ? ($newValue ? '1' : '0') : (string) ($newValue ?? '');
                if ($oldStr !== $newStr) {
                    EditHistory::recordChange($coupon, $field, $oldValue, $newValue);
                }
            }

            $coupon->update($data);
            $this->syncAssignedUsers($coupon);
            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $coupon, "Updated coupon \"{$coupon->code}\"");
        } else {
            $coupon = Coupon::query()->create($data);
            $this->syncAssignedUsers($coupon);
            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $coupon, "Created coupon \"{$coupon->code}\"");
        }

        unset($this->coupons);
        $this->showModal = false;
    }

    public function deleteCoupon(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $coupon, "Deleted coupon \"{$coupon->code}\"");
        $coupon->delete();
        unset($this->coupons);
    }

    private function syncAssignedUsers(Coupon $coupon): void
    {
        $userIds = collect($this->assigned_user_ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            $coupon->users()->detach();

            return;
        }

        $usageLimit = $this->user_usage_limit !== '' && $this->user_usage_limit !== null
            ? (int) $this->user_usage_limit
            : null;

        $existingUsageCounts = $coupon->users()
            ->whereIn('users.id', $userIds)
            ->pluck('coupon_user.used_count', 'users.id');

        $syncPayload = [];

        foreach ($userIds as $userId) {
            $syncPayload[$userId] = [
                'usage_limit' => $usageLimit,
                'used_count' => (int) ($existingUsageCounts[$userId] ?? 0),
                'is_active' => true,
            ];
        }

        $coupon->users()->sync($syncPayload);
    }

    private function resetFormFields(): void
    {
        $this->code = '';
        $this->description = '';
        $this->type = Coupon::TYPE_FIXED;
        $this->value = '';
        $this->minimum_subtotal = '';
        $this->usage_limit = '';
        $this->used_count = 0;
        $this->starts_at = '';
        $this->ends_at = '';
        $this->is_active = true;
        $this->is_stackable = false;
        $this->stack_group = '';
        $this->scope_type = Coupon::SCOPE_ALL;
        $this->scope_product_id = null;
        $this->scope_category_id = null;
        $this->scope_tag_id = null;
        $this->is_personal = false;
        $this->assigned_user_ids = [];
        $this->user_usage_limit = '';
        $this->userSearch = '';
        $this->resetErrorBag();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Coupons</h2>
        <button wire:click="openCreate" class="rounded bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-900">New Coupon</button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by code or description…" class="w-full rounded border border-slate-300 px-3 py-2 text-sm md:w-1/3">
        </div>

        <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Code</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Type</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Value</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left lg:table-cell">Rules</th>
                    <th class="hidden border border-slate-200 px-3 py-2 text-left lg:table-cell">Usage</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->coupons as $coupon)
                    <tr wire:key="coupon-{{ $coupon->id }}">
                        <td class="border border-slate-200 px-3 py-2">
                            <div class="font-medium text-slate-900">{{ $coupon->code }}</div>
                            @if ($coupon->description)
                                <div class="text-xs text-slate-500">{{ Str::limit($coupon->description, 40) }}</div>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($coupon->type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($coupon->type === App\Models\Coupon::TYPE_PERCENT)
                                {{ $coupon->value }}%
                            @else
                                ${{ number_format((float) $coupon->value, 2) }}
                            @endif
                        </td>
                        <td class="hidden border border-slate-200 px-3 py-2 text-xs text-slate-600 lg:table-cell">
                            @if ($coupon->is_stackable)
                                <span class="rounded bg-blue-100 px-1.5 py-0.5 text-blue-700">Stackable</span>
                            @endif
                            @if ($coupon->stack_group)
                                <span class="rounded bg-slate-200 px-1.5 py-0.5">{{ $coupon->stack_group }}</span>
                            @endif
                            @if ($coupon->scope_type !== 'all')
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-700">{{ ucfirst($coupon->scope_type) }}</span>
                            @endif
                            @if ($coupon->is_personal)
                                <span class="rounded bg-purple-100 px-1.5 py-0.5 text-purple-700">Personal</span>
                            @endif
                        </td>
                        <td class="hidden border border-slate-200 px-3 py-2 lg:table-cell">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            <span class="rounded px-1.5 py-0.5 text-xs {{ $coupon->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <button wire:click="openEdit({{ $coupon->id }})" class="text-blue-700 hover:underline">Edit</button>
                            <button wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Delete coupon {{ $coupon->code }}?" class="ml-2 text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No coupons found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

        <div class="mt-4">{{ $this->coupons->links() }}</div>
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" wire:click.self="$set('showModal', false)">
            <div class="my-8 w-full max-w-3xl rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Coupon' : 'New Coupon' }}</h3>

                <div class="space-y-4">
                    {{-- Code & Description --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Code</label>
                            <input type="text" wire:model="code" class="w-full rounded border border-slate-300 px-3 py-2 uppercase">
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Description</label>
                            <input type="text" wire:model="description" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Type & Value --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Type</label>
                            <select wire:model="type" class="w-full rounded border border-slate-300 px-3 py-2">
                                @foreach (App\Models\Coupon::supportedTypes() as $typeOption)
                                    <option value="{{ $typeOption }}">{{ ucfirst($typeOption) }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Value</label>
                            <input type="number" step="0.01" min="0.01" wire:model="value" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Min Subtotal, Usage Limit, Used Count --}}
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Minimum Subtotal</label>
                            <input type="number" step="0.01" min="0" wire:model="minimum_subtotal" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('minimum_subtotal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Usage Limit</label>
                            <input type="number" min="1" wire:model="usage_limit" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('usage_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Used Count</label>
                            <input type="number" min="0" wire:model="used_count" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('used_count') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Date Range --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Starts At</label>
                            <input type="datetime-local" wire:model="starts_at" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('starts_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Ends At</label>
                            <input type="datetime-local" wire:model="ends_at" class="w-full rounded border border-slate-300 px-3 py-2">
                            @error('ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Stacking Rules --}}
                    <div class="rounded border border-slate-200 p-4">
                        <h4 class="text-sm font-semibold text-slate-800">Stacking Rules</h4>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="is_stackable">
                                Can be combined with other coupons
                            </label>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Stack Group</label>
                                <input type="text" wire:model="stack_group" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Example: LOYALTY">
                                <p class="mt-1 text-xs text-slate-500">Coupons in the same stack group cannot be combined with each other.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Scope --}}
                    <div class="rounded border border-slate-200 p-4">
                        <h4 class="text-sm font-semibold text-slate-800">Scope</h4>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Applies To</label>
                                <select wire:model.live="scope_type" class="w-full rounded border border-slate-300 px-3 py-2">
                                    @foreach (App\Models\Coupon::supportedScopes() as $scopeOption)
                                        <option value="{{ $scopeOption }}">{{ ucfirst($scopeOption) }}</option>
                                    @endforeach
                                </select>
                                @error('scope_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="is_personal">
                                Personal coupon (must be assigned to users)
                            </label>
                        </div>

                        @if ($scope_type === App\Models\Coupon::SCOPE_PRODUCT)
                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium">Target Product</label>
                                <select wire:model="scope_product_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select a product</option>
                                    @foreach ($this->products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                @error('scope_product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @elseif ($scope_type === App\Models\Coupon::SCOPE_CATEGORY)
                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium">Target Category</label>
                                <select wire:model="scope_category_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select a category</option>
                                    @foreach ($this->categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('scope_category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @elseif ($scope_type === App\Models\Coupon::SCOPE_TAG)
                            <div class="mt-4">
                                <label class="mb-1 block text-sm font-medium">Target Tag</label>
                                <select wire:model="scope_tag_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select a tag</option>
                                    @foreach ($this->tags as $tag)
                                        <option value="{{ $tag->id }}">{{ ucfirst($tag->type) }}: {{ $tag->name }}</option>
                                    @endforeach
                                </select>
                                @error('scope_tag_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    {{-- Assigned Users --}}
                    <div class="rounded border border-slate-200 p-4">
                        <h4 class="text-sm font-semibold text-slate-800">Assigned Users</h4>
                        <p class="mt-1 text-xs text-slate-500">Search by email to find users, then select one or more.</p>

                        <div class="mt-3">
                            <label class="mb-1 block text-sm font-medium">User Search</label>
                            <input type="text" wire:model.live.debounce.300ms="userSearch" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Search by email…">
                        </div>

                        @if ($this->searchableUsers->isNotEmpty())
                            <div class="mt-3">
                                <label class="mb-1 block text-sm font-medium">Users</label>
                                <select wire:model="assigned_user_ids" multiple size="6" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                    @foreach ($this->searchableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->email }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_user_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="mt-3">
                            <label class="mb-1 block text-sm font-medium">Per-user usage limit</label>
                            <input type="number" min="1" wire:model="user_usage_limit" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Leave blank for unlimited">
                            @error('user_usage_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Active --}}
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="is_active">
                        Active
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Cancel</button>
                    <button wire:click="save" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-900">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Coupon' : 'Create Coupon' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
