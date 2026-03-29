<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <main class="mx-auto max-w-md p-6">
            <div class="rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold">Welcome back</h1>
                <p class="mt-1 text-sm text-slate-600">Log in to manage your account and orders.</p>

                @if (session('status'))
                    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-5 grid gap-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                        Remember me
                    </label>

                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Log in</button>
                </form>

                <p class="mt-3 text-sm text-slate-600">
                    <a href="{{ route('password.request') }}" class="text-blue-700 hover:underline">Forgot your password?</a>
                </p>

                <p class="mt-4 text-sm text-slate-600">
                    No account yet?
                    <a href="{{ route('register') }}" class="text-blue-700 hover:underline">Create one</a>
                </p>

                <p class="mt-2 text-sm text-slate-600">
                    <a href="{{ route('shop.index') }}" class="text-blue-700 hover:underline">Back to shop</a>
                </p>
            </div>
        </main>
    </body>
</html>
