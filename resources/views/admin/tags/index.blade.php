@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Artists & Brands</h2>
        <a href="{{ route('admin.tags.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Tag</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Type</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Slug</th>
                    <th class="border border-slate-200 px-3 py-2 text-center">Followers</th>
                    <th class="border border-slate-200 px-3 py-2 text-center">Active Products</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">{{ $tag->name }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ ucfirst($tag->type) }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $tag->slug }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-center">{{ $tag->followers_count }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-center">{{ $tag->active_products_count }}</td>
                        <td class="border border-slate-200 px-3 py-2">
                            @if ($tag->is_active)
                                <span class="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Active</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.tags.edit', $tag) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this artist/brand tag?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No artist/brand tags yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tags->links() }}</div>
@endsection
