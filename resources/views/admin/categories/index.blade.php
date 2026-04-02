@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Categories</h2>
        <a href="{{ route('admin.categories.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Category</a>
    </div>

    <p class="mb-4 text-sm text-slate-500">Drag the handle to reorder categories. Changes are saved automatically.</p>

    <div id="category-list" class="space-y-1">
        @forelse ($categories as $category)
            <div class="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 {{ $category->parent_id ? 'ml-8' : '' }}" data-id="{{ $category->id }}" data-parent="{{ $category->parent_id ?? '' }}">
                <span class="drag-handle cursor-grab text-slate-400 hover:text-slate-600 active:cursor-grabbing">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </span>
                <div class="flex-1">
                    <span class="font-medium">{{ $category->name }}</span>
                    <span class="ml-2 text-xs text-slate-400">/{{ $category->slug }}</span>
                    @if ($category->parent)
                        <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">{{ $category->parent->name }}</span>
                    @endif
                </div>
                <span class="text-xs {{ $category->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                </span>
                <a href="{{ route('admin.categories.edit', $category) }}" class="text-sm text-blue-600 hover:underline">Edit</a>
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:underline" onclick="return confirm('Delete this category?')">Delete</button>
                </form>
            </div>
        @empty
            <p class="py-8 text-center text-slate-500">No categories yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>

    <div id="reorder-status" class="fixed bottom-4 right-4 hidden rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white shadow-lg transition"></div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const list = document.getElementById('category-list');
            if (!list || !list.children.length) return;

            Sortable.create(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-30',
                onEnd: function () {
                    const items = list.querySelectorAll('[data-id]');
                    const order = Array.from(items).map(el => parseInt(el.dataset.id));

                    const status = document.getElementById('reorder-status');
                    status.textContent = 'Saving...';
                    status.classList.remove('hidden', 'bg-red-600');
                    status.classList.add('bg-emerald-600');

                    fetch('{{ route("admin.categories.reorder") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ order: order }),
                    })
                    .then(r => {
                        if (!r.ok) throw new Error('Failed');
                        status.textContent = 'Order saved';
                        setTimeout(() => status.classList.add('hidden'), 1500);
                    })
                    .catch(() => {
                        status.textContent = 'Failed to save';
                        status.classList.remove('bg-emerald-600');
                        status.classList.add('bg-red-600');
                        setTimeout(() => status.classList.add('hidden'), 3000);
                    });
                }
            });
        });
    </script>
@endsection
