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
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Ends At</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($coupon->ends_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<label class="inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->exists ? ($coupon->is_active ? '1' : '0') : '1') === '1')>
    Active
</label>