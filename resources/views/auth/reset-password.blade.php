<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset Password | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        <main class="mx-auto max-w-md p-6">
            <div class="rounded border border-black/10 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold">Reset password</h1>
                <p class="mt-1 text-sm text-black/60">Choose a new password for your account.</p>

                <form method="POST" action="{{ route('password.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-black/70">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" class="w-full rounded border border-black/20 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-black/70">New password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded border border-black/20 px-3 py-2 text-sm">
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-black/70">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded border border-black/20 px-3 py-2 text-sm">
                    </div>

                    <button type="submit" class="rounded bg-black px-4 py-2 text-sm font-medium text-white hover:bg-black/80">Reset password</button>
                </form>

                <p class="mt-4 text-sm text-black/60">
                    <a href="{{ route('login') }}" class="font-medium text-black underline hover:no-underline">Back to login</a>
                </p>
            </div>
        </main>

        @include('partials.footer')
    </body>
</html>
