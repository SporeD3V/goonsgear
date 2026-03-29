<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Register | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <main class="mx-auto max-w-md p-6">
            <div class="rounded border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold">Create account</h1>
                <p class="mt-1 text-sm text-slate-600">Save your details for faster checkout.</p>

                                <form method="POST"
                                        action="{{ route('register.store') }}"
                                        class="mt-5 grid gap-4"
                                        data-recaptcha-protected="{{ $recaptchaChallenge && $recaptchaSiteKey ? '1' : '0' }}"
                                        data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}"
                                        data-recaptcha-action="register"
                                        data-recaptcha-error-id="register-recaptcha-errors">
                    @csrf
                                        <input id="recaptcha_token" name="recaptcha_token" type="hidden" value="{{ old('recaptcha_token') }}">

                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Create account</button>
                    @error('recaptcha_token')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <div id="register-recaptcha-errors" class="hidden rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                </form>

                <p class="mt-4 text-sm text-slate-600">
                    Already registered?
                    <a href="{{ route('login') }}" class="text-blue-700 hover:underline">Log in</a>
                </p>

                <p class="mt-2 text-sm text-slate-600">
                    <a href="{{ route('shop.index') }}" class="text-blue-700 hover:underline">Back to shop</a>
                </p>
            </div>
        </main>

        @if ($recaptchaChallenge && $recaptchaSiteKey)
            <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
        @endif
    </body>
</html>
