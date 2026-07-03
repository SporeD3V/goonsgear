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
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-stone-800">Coupons</h2>
            <p class="text-[13px] text-stone-500">{{ number_format($this->coupons->total()) }} {{ \Illuminate\Support\Str::plural('coupon', $this->coupons->total()) }} in current view</p>
        </div>
        <button wire:click="openCreate" class="inline-flex items-center gap-2 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New Coupon
        </button>
    </div>

    <div class="admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm" data-delay="1">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by code or description…" class="w-full rounded-lg border border-stone-200 py-2.5 pl-9 pr-3 text-sm text-stone-700 placeholder:text-stone-400 focus:border-[#36a2eb] focus:outline-none focus:ring-1 focus:ring-[#36a2eb]">
        </div>
    </div>

    <div class="admin-card overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-delay="2"
         wire:loading.class="pointer-events-none opacity-60"
         wire:target="search">
        <ul class="divide-y divide-stone-100">
            @forelse ($this->coupons as $coupon)
                <li wire:key="coupon-{{ $coupon->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 transition hover:bg-stone-50/60">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <code class="text-sm font-black tracking-widest text-stone-800">{{ $coupon->code }}</code>
                            <span class="text-sm font-bold text-red-600">
                                @if ($coupon->type === App\Models\Coupon::TYPE_PERCENT)
                                    {{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}% off
                                @else
                                    &euro;{{ number_format((float) $coupon->value, 2) }} off
                                @endif
                            </span>
                        </div>
                        @if ($coupon->description)
                            <p class="mt-0.5 truncate text-xs text-stone-400">{{ Str::limit($coupon->description, 80) }}</p>
                        @endif
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                            @if ($coupon->is_stackable)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">Stackable</span>
                            @endif
                            @if ($coupon->stack_group)
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $coupon->stack_group }}</span>
                            @endif
                            @if ($coupon->scope_type !== 'all')
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700">{{ ucfirst($coupon->scope_type) }} only</span>
                            @endif
                            @if ($coupon->is_personal)
                                <span class="rounded-full bg-purple-100 px-2 py-0.5 font-medium text-purple-700">Personal</span>
                            @endif
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }} used</span>
                        </div>
                    </div>

                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $coupon->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                    </span>

                    <div class="flex items-center gap-1">
                        <button wire:click="openEdit({{ $coupon->id }})" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit coupon">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                        </button>
                        <button wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Delete coupon {{ $coupon->code }}?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete coupon">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </li>
            @empty
                <li class="px-6 py-14 text-center">
                    <svg class="mx-auto h-10 w-10 text-stone-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-12-.75h14.25A2.25 2.25 0 0 0 21 15v-1.5a1.5 1.5 0 0 1 0-3V9a2.25 2.25 0 0 0-2.25-2.25H4.5A2.25 2.25 0 0 0 2.25 9v1.5a1.5 1.5 0 0 1 0 3V15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                    <p class="mt-3 text-sm font-medium text-stone-600">No coupons found.</p>
                </li>
            @endforelse
        </ul>

        @if ($this->coupons->hasPages())
            <div class="border-t border-stone-100 px-4 py-3">{{ $this->coupons->links() }}</div>
        @endif
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
                            <input type="text" wire:model="code" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 uppercase">
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Description</label>
                            <input type="text" wire:model="description" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Type & Value --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Type</label>
                            <select wire:model="type" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                                @foreach (App\Models\Coupon::supportedTypes() as $typeOption)
                                    <option value="{{ $typeOption }}">{{ ucfirst($typeOption) }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Value</label>
                            <input type="number" step="0.01" min="0.01" wire:model="value" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Min Subtotal, Usage Limit, Used Count --}}
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Minimum Subtotal</label>
                            <input type="number" step="0.01" min="0" wire:model="minimum_subtotal" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('minimum_subtotal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Usage Limit</label>
                            <input type="number" min="1" wire:model="usage_limit" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('usage_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Used Count</label>
                            <input type="number" min="0" wire:model="used_count" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('used_count') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Date Range --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Starts At</label>
                            <input type="datetime-local" wire:model="starts_at" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('starts_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Ends At</label>
                            <input type="datetime-local" wire:model="ends_at" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
                            @error('ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Stacking Rules --}}
                    <div class="rounded-lg border border-stone-200 p-4">
                        <h4 class="text-sm font-semibold text-stone-800">Stacking Rules</h4>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="is_stackable">
                                Can be combined with other coupons
                            </label>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Stack Group</label>
                                <input type="text" wire:model="stack_group" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2" placeholder="Example: LOYALTY">
                                <p class="mt-1 text-xs text-stone-500">Coupons in the same stack group cannot be combined with each other.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Scope --}}
                    <div class="rounded-lg border border-stone-200 p-4">
                        <h4 class="text-sm font-semibold text-stone-800">Scope</h4>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Applies To</label>
                                <select wire:model.live="scope_type" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2">
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
                                <select wire:model="scope_product_id" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
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
                                <select wire:model="scope_category_id" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
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
                                <select wire:model="scope_tag_id" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
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
                    <div class="rounded-lg border border-stone-200 p-4">
                        <h4 class="text-sm font-semibold text-stone-800">Assigned Users</h4>
                        <p class="mt-1 text-xs text-stone-500">Search by email to find users, then select one or more.</p>

                        <div class="mt-3">
                            <label class="mb-1 block text-sm font-medium">User Search</label>
                            <input type="text" wire:model.live.debounce.300ms="userSearch" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" placeholder="Search by email…">
                        </div>

                        @if ($this->searchableUsers->isNotEmpty())
                            <div class="mt-3">
                                <label class="mb-1 block text-sm font-medium">Users</label>
                                <select wire:model="assigned_user_ids" multiple size="6" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                    @foreach ($this->searchableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->email }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_user_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="mt-3">
                            <label class="mb-1 block text-sm font-medium">Per-user usage limit</label>
                            <input type="number" min="1" wire:model="user_usage_limit" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2" placeholder="Leave blank for unlimited">
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
                    <button wire:click="$set('showModal', false)" class="rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-4 py-2 text-sm text-stone-700 hover:bg-stone-100">Cancel</button>
                    <button wire:click="save" class="rounded bg-stone-800 px-4 py-2 text-sm text-white hover:bg-stone-900">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Coupon' : 'Create Coupon' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

