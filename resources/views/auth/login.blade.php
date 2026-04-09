<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-white text-black">
        @include('partials.header')

        <main class="mx-auto max-w-md p-6">
            <div class="rounded border border-black/10 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold">Welcome back</h1>
                <p class="mt-1 text-sm text-black/60">Log in to manage your account and orders.</p>

                @if (session('status'))
                    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif

                                <form method="POST"
                                        action="{{ route('login.store') }}"
                                        class="mt-5 grid gap-4"
                                        data-recaptcha-protected="{{ $recaptchaChallenge && $recaptchaSiteKey ? '1' : '0' }}"
                                        data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}"
                                        data-recaptcha-action="login"
                                        data-recaptcha-error-id="login-recaptcha-errors">
                    @csrf
                                        <input id="recaptcha_token" name="recaptcha_token" type="hidden" value="{{ old('recaptcha_token') }}">

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-black/70">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded border border-black/20 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-black/70">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded border border-black/20 px-3 py-2 text-sm">
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-black/70">
                        <input type="checkbox" name="remember" value="1" class="rounded border-black/20 text-black focus:ring-black">
                        Remember me
                    </label>

                    <button type="submit" class="rounded bg-black px-4 py-2 text-sm font-medium text-white hover:bg-black/80">Log in</button>
                    @error('recaptcha_token')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <div id="login-recaptcha-errors" class="hidden rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                </form>

                <p class="mt-3 text-sm text-black/60">
                    <a href="{{ route('password.request') }}" class="font-medium text-black underline hover:no-underline">Forgot your password?</a>
                </p>

                <p class="mt-4 text-sm text-black/60">
                    No account yet?
                    <a href="{{ route('register') }}" class="font-medium text-black underline hover:no-underline">Create one</a>
                </p>

                <p class="mt-2 text-sm text-black/60">
                    <a href="{{ route('shop.index') }}" class="font-medium text-black underline hover:no-underline">Back to shop</a>
                </p>
            </div>
        </main>

        @include('partials.footer')

        @if ($recaptchaChallenge && $recaptchaSiteKey)
            <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
        @endif
    </body>
</html>
