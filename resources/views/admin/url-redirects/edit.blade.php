@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Edit URL Redirect &mdash; {{ $urlRedirect->from_path }}</h2>
        <a href="{{ route('admin.url-redirects.index') }}" class="text-sm text-blue-700 hover:underline">Back to list</a>
    </div>

    <form method="POST" action="{{ route('admin.url-redirects.update', $urlRedirect) }}" class="grid max-w-2xl gap-4">
        @csrf
        @method('PUT')
        @include('admin.url-redirects.form-fields')

        <div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Save Changes</button>
        </div>
    </form>
@endsection
