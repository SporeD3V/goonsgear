<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Admin' }} | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl p-6">
            <header class="mb-6 rounded-lg bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-xl font-semibold">GoonsGear Admin</h1>
                    <nav class="flex items-center gap-3 text-sm">
                        <a class="text-blue-700 hover:underline" href="{{ route('admin.categories.index') }}">Categories</a>
                        <a class="text-blue-700 hover:underline" href="{{ route('admin.products.index') }}">Products</a>
                    </nav>
                </div>
            </header>

            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <main class="rounded-lg bg-white p-6 shadow-sm">
                @yield('content')
            </main>
        </div>
    </body>
</html>
