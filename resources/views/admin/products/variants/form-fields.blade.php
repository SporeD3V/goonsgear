<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Variant Name</label>
        <input type="text" name="name" value="{{ old('name', $variant?->name) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $variant?->sku) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Price</label>
        <input type="number" min="0" step="0.01" name="price" value="{{ old('price', $variant?->price) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Compare At Price</label>
        <input type="number" min="0" step="0.01" name="compare_at_price" value="{{ old('compare_at_price', $variant?->compare_at_price) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<div>
    <label class="mb-1 block text-sm font-medium">Option Values (JSON)</label>
    <textarea name="option_values_json" rows="4" class="w-full rounded border border-slate-300 px-3 py-2" placeholder='{"size":"L","color":"Black"}'>{{ old('option_values_json', $variant ? json_encode($variant->option_values, JSON_PRETTY_PRINT) : '') }}</textarea>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Stock Quantity</label>
        <input type="number" min="0" name="stock_quantity" value="{{ old('stock_quantity', $variant?->stock_quantity ?? 0) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Position</label>
        <input type="number" min="0" name="position" value="{{ old('position', $variant?->position ?? 0) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">Preorder Available From</label>
        <input type="datetime-local" name="preorder_available_from" value="{{ old('preorder_available_from', optional($variant?->preorder_available_from)->format('Y-m-d\\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Expected Ship At</label>
        <input type="datetime-local" name="expected_ship_at" value="{{ old('expected_ship_at', optional($variant?->expected_ship_at)->format('Y-m-d\\TH:i')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="track_inventory" value="1" @checked((string) old('track_inventory', $variant?->track_inventory ? '1' : '0') === '1')>
        Track Inventory
    </label>

    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="allow_backorder" value="1" @checked((string) old('allow_backorder', $variant?->allow_backorder ? '1' : '0') === '1')>
        Allow Backorders
    </label>

    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked((string) old('is_active', $variant?->is_active ? '1' : ($variant ? '0' : '1')) === '1')>
        Active
    </label>

    <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_preorder" value="1" @checked((string) old('is_preorder', $variant?->is_preorder ? '1' : '0') === '1')>
        Preorder Enabled
    </label>
</div>
