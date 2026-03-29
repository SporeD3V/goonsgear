@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">URL Redirects</h2>
        <a href="{{ route('admin.url-redirects.create') }}" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">New Redirect</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="border border-slate-200 px-3 py-2 text-left">From Path</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Destination</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Status</th>
                    <th class="border border-slate-200 px-3 py-2 text-left">Active</th>
                    <th class="border border-slate-200 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redirects as $redirect)
                    <tr>
                        <td class="border border-slate-200 px-3 py-2 font-medium text-slate-900">{{ $redirect->from_path }}</td>
                        <td class="border border-slate-200 px-3 py-2 break-all">{{ $redirect->to_url }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $redirect->status_code }}</td>
                        <td class="border border-slate-200 px-3 py-2">{{ $redirect->is_active ? 'Yes' : 'No' }}</td>
                        <td class="border border-slate-200 px-3 py-2 text-right">
                            <a href="{{ route('admin.url-redirects.edit', $redirect) }}" class="text-blue-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.url-redirects.destroy', $redirect) }}" class="ml-2 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Delete this redirect?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="border border-slate-200 px-3 py-6 text-center text-slate-500">No URL redirects yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $redirects->links() }}</div>
@endsection
