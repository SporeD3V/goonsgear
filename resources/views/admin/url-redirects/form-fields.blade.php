<div>
    <label class="mb-1 block text-sm font-medium">From path</label>
    <input type="text" name="from_path" value="{{ old('from_path', $urlRedirect->from_path) }}" placeholder="/old-product-url" class="w-full rounded border border-slate-300 px-3 py-2" maxlength="255" required>
    <p class="mt-1 text-xs text-slate-500">Use a path from the old site, for example /shop/old-hoodie.</p>
</div>

<div>
    <label class="mb-1 block text-sm font-medium">Destination URL or path</label>
    <input type="text" name="to_url" value="{{ old('to_url', $urlRedirect->to_url) }}" placeholder="/shop/new-hoodie or https://example.com/new" class="w-full rounded border border-slate-300 px-3 py-2" maxlength="2048" required>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium">HTTP Status</label>
        <select name="status_code" class="w-full rounded border border-slate-300 px-3 py-2">
            <option value="301" @selected((int) old('status_code', $urlRedirect->status_code ?? 301) === 301)>301 Permanent</option>
            <option value="302" @selected((int) old('status_code', $urlRedirect->status_code ?? 301) === 302)>302 Temporary</option>
        </select>
    </div>

    <div class="flex items-center gap-2 pt-7">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $urlRedirect->is_active ?? true)) class="h-4 w-4 rounded border-slate-300">
        <label for="is_active" class="text-sm font-medium">Active</label>
    </div>
</div>
