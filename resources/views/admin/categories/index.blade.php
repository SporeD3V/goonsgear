@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Categories</h2>
        <a href="{{ route('admin.categories.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Category</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">Name</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Slug</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Parent</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2">{{ $category->name }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $category->slug }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $category->parent?->name ?? '-' }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this category?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
@endsection
