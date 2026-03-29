<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Bundle name</label>
        <input type="text" name="name" value="{{ old('name', $bundleDiscount->name) }}" class="w-full rounded border border-slate-300 px-3 py-2" maxlength="120" required>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Discount type</label>
        <select name="discount_type" class="w-full rounded border border-slate-300 px-3 py-2">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('discount_type', $bundleDiscount->discount_type ?: \App\Models\BundleDiscount::TYPE_FIXED) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Discount value</label>
        <input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value', $bundleDiscount->discount_value) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>

    <div class="flex items-center gap-2 pt-7">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $bundleDiscount->is_active ?? true)) class="h-4 w-4 rounded border-slate-300">
        <label for="is_active" class="text-sm font-medium">Active</label>
    </div>
</div>

<div>
    <label class="mb-1 block text-sm font-medium">Description</label>
    <input type="text" name="description" value="{{ old('description', $bundleDiscount->description) }}" class="w-full rounded border border-slate-300 px-3 py-2" maxlength="500">
</div>

<div class="rounded border border-slate-200 p-4">
    <p class="mb-2 text-sm font-medium text-slate-900">Variant requirements</p>
    <p class="mb-3 text-xs text-slate-500">Select one or more variants and set minimum quantities required for this bundle.</p>

    <div class="max-h-96 overflow-auto border border-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Use</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Variant</th>
                    <th class="border-b border-slate-200 px-3 py-2 text-left">Min Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($variants as $variant)
                    <tr>
                        <td class="border-b border-slate-200 px-3 py-2">
                            <input type="checkbox"
                                   name="variant_ids[]"
                                   value="{{ $variant['id'] }}"
                                @checked(in_array($variant['id'], old('variant_ids', $selectedVariantIds)))
                                   class="h-4 w-4 rounded border-slate-300">
                        </td>
                        <td class="border-b border-slate-200 px-3 py-2">{{ $variant['label'] }}</td>
                        <td class="border-b border-slate-200 px-3 py-2">
                            <input type="number"
                                   min="1"
                                   max="99"
                                   name="quantities[{{ $variant['id'] }}]"
                                   value="{{ old('quantities.'.$variant['id'], $itemQuantities[$variant['id']] ?? 1) }}"
                                   class="w-20 rounded border border-slate-300 px-2 py-1">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @error('variant_ids')
        <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
