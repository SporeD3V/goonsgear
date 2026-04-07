@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">Edit Tag: {{ $tag->name }}</h2>

    <form method="POST" action="{{ route('admin.tags.update', $tag) }}" class="space-y-4" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input type="text" name="name" value="{{ old('name', $tag->name) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $tag->slug) }}" class="w-full rounded border border-slate-300 px-3 py-2" required>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Type</label>
                <select name="type" id="tag-type" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <option value="artist" @selected(old('type', $tag->type) === 'artist')>Artist</option>
                    <option value="brand" @selected(old('type', $tag->type) === 'brand')>Brand</option>
                    <option value="custom" @selected(old('type', $tag->type) === 'custom')>Custom</option>
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked((string) old('is_active', $tag->is_active ? '1' : '0') === '1')>
                Active
            </label>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Description</label>
            <textarea name="description" rows="4" class="w-full rounded border border-slate-300 px-3 py-2">{{ old('description', $tag->description) }}</textarea>
        </div>

        {{-- Logo upload: artist & brand only --}}
        <div id="logo-section" class="{{ in_array(old('type', $tag->type), ['artist', 'brand']) ? '' : 'hidden' }} space-y-3 rounded border border-slate-200 p-4">
            <h3 class="text-sm font-semibold">Logo Image (200×200)</h3>

            @if ($tag->logo_path)
                <div class="flex items-center gap-4">
                    <img
                        src="{{ route('media.show', ['path' => $tag->logo_path]) }}"
                        alt="{{ $tag->name }} logo"
                        class="h-16 w-16 rounded border border-slate-200 object-cover"
                    >
                    <label class="inline-flex items-center gap-2 text-sm text-rose-600">
                        <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>
                        Remove current logo
                    </label>
                </div>
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium">Upload new logo</label>
                <input type="file" name="logo" accept="image/*" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP. Will be converted to AVIF 200×200. Max 5 MB.</p>
            </div>

            <label class="inline-flex items-center gap-2 text-sm {{ $tag->logo_path ? '' : 'opacity-50' }}">
                <input
                    type="checkbox"
                    name="show_on_homepage"
                    value="1"
                    @checked(old('show_on_homepage', $tag->show_on_homepage))
                    {{ $tag->logo_path ? '' : 'disabled' }}
                    id="show-on-homepage-checkbox"
                >
                Display in "Shop by Artist" carousel on homepage
                @unless ($tag->logo_path)
                    <span class="text-xs text-slate-400">(requires a logo)</span>
                @endunless
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update</button>
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

    @include('admin.partials.edit-history', ['histories' => $editHistories])
@endsection

