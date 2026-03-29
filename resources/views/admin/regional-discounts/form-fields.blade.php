<div>
    <label class="mb-1 block text-sm font-medium">Country</label>
    <select name="country_code" class="w-full rounded border border-slate-300 px-3 py-2">
        <option value="">— Select country —</option>
        @foreach ($countries as $code => $name)
            <option value="{{ $code }}" @selected(old('country_code', $discount->country_code) === $code)>{{ $name }} ({{ $code }})</option>
        @endforeach
    </select>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Discount type</label>
        <select name="discount_type" class="w-full rounded border border-slate-300 px-3 py-2">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('discount_type', $discount->discount_type ?: \App\Models\RegionalDiscount::TYPE_FIXED) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Value</label>
        <input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value', $discount->discount_value) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>
</div>

<div>
    <label class="mb-1 block text-sm font-medium">Reason <span class="text-slate-400 text-xs">(shown to the customer at checkout)</span></label>
    <input type="text" name="reason" value="{{ old('reason', $discount->reason) }}" class="w-full rounded border border-slate-300 px-3 py-2" maxlength="500" required>
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $discount->is_active ?? true)) class="h-4 w-4 rounded border-slate-300">
    <label for="is_active" class="text-sm font-medium">Active</label>
</div>
