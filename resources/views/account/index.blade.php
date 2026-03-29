<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>My Account | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <main class="mx-auto max-w-4xl p-6">
            <header class="mb-6 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">My Account</h1>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('shop.index') }}" class="text-blue-700 hover:underline">Shop</a>
                    <a href="{{ route('cart.index') }}" class="text-blue-700 hover:underline">Cart</a>
                </div>
            </header>

            <section class="rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold">Profile</h2>
                <dl class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt>
                        <dd class="mt-1 font-medium">{{ auth()->user()?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 font-medium">{{ auth()->user()?->email }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Log out</button>
                </form>
            </section>
        </main>
    </body>
</html>
