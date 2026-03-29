<div>
    <label class="mb-1 block text-sm font-medium">Code</label>
    <input type="text" name="code" value="{{ old('code', $coupon->code) }}" class="w-full rounded border border-slate-300 px-3 py-2 uppercase" required>
</div>

<div>
    <label class="mb-1 block text-sm font-medium">Description</label>
    <input type="text" name="description" value="{{ old('description', $coupon->description) }}" class="w-full rounded border border-slate-300 px-3 py-2">
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Type</label>
        <select name="type" class="w-full rounded border border-slate-300 px-3 py-2">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $coupon->type ?: \App\Models\Coupon::TYPE_FIXED) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Value</label>
        <input type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div>
        <label class="mb-1 block text-sm font-medium">Minimum Subtotal</label>
        <input type="number" step="0.01" min="0" name="minimum_subtotal" value="{{ old('minimum_subtotal', $coupon->minimum_subtotal) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Usage Limit</label>
        <input type="number" min="1" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Used Count</label>
        <input type="number" min="0" name="used_count" value="{{ old('used_count', $coupon->used_count ?? 0) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Starts At</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d\\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Ends At</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($coupon->ends_at)->format('Y-m-d\\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<div class="rounded border border-slate-200 p-4">
    <h3 class="text-sm font-semibold text-slate-800">Stacking Rules</h3>
    <div class="mt-3 grid gap-4 md:grid-cols-2">
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_stackable" value="1" @checked(old('is_stackable', $coupon->exists ? ($coupon->is_stackable ? '1' : '0') : '0') === '1')>
            Can be combined with other coupons
        </label>

        <div>
            <label class="mb-1 block text-sm font-medium">Stack Group</label>
            <input type="text" name="stack_group" value="{{ old('stack_group', $coupon->stack_group) }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Example: LOYALTY">
            <p class="mt-1 text-xs text-slate-500">Coupons in the same stack group cannot be combined with each other.</p>
        </div>
    </div>
</div>

<div class="rounded border border-slate-200 p-4">
    <h3 class="text-sm font-semibold text-slate-800">Scope</h3>

    @php
        $selectedScopeType = old('scope_type', $coupon->scope_type ?: \App\Models\Coupon::SCOPE_ALL);
    @endphp

    <div class="mt-3 grid gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium">Applies To</label>
            <select name="scope_type" class="w-full rounded border border-slate-300 px-3 py-2">
                @foreach ($scopeTypes as $scopeType)
                    <option value="{{ $scopeType }}" @selected($selectedScopeType === $scopeType)>{{ ucfirst($scopeType) }}</option>
                @endforeach
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_personal" value="1" @checked(old('is_personal', $coupon->exists ? ($coupon->is_personal ? '1' : '0') : '0') === '1')>
            Personal coupon (must be assigned to users)
        </label>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium">Target Product</label>
            <select name="scope_product_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any product</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((int) old('scope_product_id', $selectedScopeType === \App\Models\Coupon::SCOPE_PRODUCT ? $coupon->scope_id : null) === $product->id)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Target Category</label>
            <select name="scope_category_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('scope_category_id', $selectedScopeType === \App\Models\Coupon::SCOPE_CATEGORY ? $coupon->scope_id : null) === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Target Artist/Brand Tag</label>
            <select name="scope_tag_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any tag</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected((int) old('scope_tag_id', $selectedScopeType === \App\Models\Coupon::SCOPE_TAG ? $coupon->scope_id : null) === $tag->id)>
                        {{ ucfirst($tag->type) }}: {{ $tag->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="rounded border border-slate-200 p-4">
    <h3 class="text-sm font-semibold text-slate-800">Assigned Users</h3>
    <p class="mt-1 text-xs text-slate-500">Assign coupon ownership to specific users. Search by email first to load up to 100 matching users, then select one or more.</p>

    @php
        $selectedAssignedUsers = collect(old('assigned_user_ids', $assignedUsers ?? []))->map(fn ($id) => (int) $id)->all();
    @endphp

    <div class="mt-3">
        <label class="mb-1 block text-sm font-medium">User Search</label>
        <div class="flex items-center gap-2">
            <input type="text" name="user_search" value="{{ old('user_search', $userSearch ?? '') }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Search by email">
            <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Search</button>
        </div>
        <p class="mt-1 text-xs text-slate-500">Search refreshes this form and does not save coupon changes until you click Create/Update.</p>
    </div>

    <div class="mt-3">
        <label class="mb-1 block text-sm font-medium">Users</label>
        <select name="assigned_user_ids[]" multiple size="8" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            @foreach ($assignableUsers as $user)
                <option value="{{ $user->id }}" @selected(in_array($user->id, $selectedAssignedUsers, true))>
                    {{ $user->email }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mt-3">
        <label class="mb-1 block text-sm font-medium">Per-user usage limit</label>
        <input type="number" min="1" name="user_usage_limit" value="{{ old('user_usage_limit') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Leave blank for unlimited">
    </div>
</div>

<label class="inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->exists ? ($coupon->is_active ? '1' : '0') : '1') === '1')>
    Active
</label>
