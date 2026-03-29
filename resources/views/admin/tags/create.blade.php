@extends('admin.layout')

@section('content')
    <h2 class="mb-4 text-lg font-semibold">New Artist / Brand Tag</h2>

    <form method="POST" action="{{ route('admin.tags.store') }}" class="space-y-4" novalidate>
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
                <select name="type" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <option value="artist" @selected(old('type', 'artist') === 'artist')>Artist</option>
                    <option value="brand" @selected(old('type') === 'brand')>Brand</option>
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

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Create</button>
            <a href="{{ route('admin.tags.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
