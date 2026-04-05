@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">New Tag</h2>

    <form method="POST" action="{{ route('admin.tags.store') }}" class="space-y-4" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Type</label>
                <select name="type" id="tag-type" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <option value="artist" @selected(old('type', 'artist') === 'artist')>Artist</option>
                    <option value="brand" @selected(old('type') === 'brand')>Brand</option>
                    <option value="custom" @selected(old('type') === 'custom')>Custom</option>
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                Active
            </label>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="4" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description') }}</textarea>
        </div>

        {{-- Logo upload: artist & brand only --}}
        <div id="logo-section" class="{{ in_array(old('type', 'artist'), ['artist', 'brand']) ? '' : 'hidden' }} space-y-3 rounded border border-slate-200 p-4">
            <h3 class="text-sm font-semibold">Logo Image (200×200)</h3>
            <div>
                <label class="mb-1 block text-sm font-medium">Upload logo</label>
                <input type="file" name="logo" accept="image/*" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP. Will be converted to AVIF 200×200. Max 5 MB.</p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_on_homepage" value="1" @checked(old('show_on_homepage'))>
                Display in "Shop by Artist" carousel on homepage
                <span class="text-xs text-slate-400">(requires a logo to take effect)</span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.tags.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>

    <script>
        document.getElementById('tag-type').addEventListener('change', function () {
            const logoSection = document.getElementById('logo-section');
            if (this.value === 'artist' || this.value === 'brand') {
                logoSection.classList.remove('hidden');
            } else {
                logoSection.classList.add('hidden');
            }
        });
    </script>
@endsection

