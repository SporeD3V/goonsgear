@extends('admin.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Integration Settings</h2>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-700 hover:underline">Back to Products</a>
    </div>

    <p class="mb-4 text-sm text-slate-600">
        Credentials are encrypted before they are stored in the database. Update values below to manage external integrations.
    </p>

    <form method="POST" action="{{ route('admin.maintenance.integrations.update') }}" class="grid gap-6 rounded border border-slate-200 bg-slate-50 p-4">
        @csrf

        <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
            <h3 class="text-base font-semibold text-slate-900">reCAPTCHA (Checkout Protection)</h3>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="recaptcha_enabled" value="1" @checked(old('recaptcha_enabled', $values['recaptcha_enabled']) === '1')>
                Enable reCAPTCHA validation for checkout and PayPal initialization
            </label>

            <div>
                <label for="recaptcha_provider" class="mb-1 block text-sm font-medium text-slate-700">Provider</label>
                <select id="recaptcha_provider" name="recaptcha_provider" class="w-full rounded border border-slate-300 px-3 py-2 text-sm md:max-w-sm">
                    <option value="google" @selected(old('recaptcha_provider', $values['recaptcha_provider']) === 'google')>Google reCAPTCHA v3</option>
                </select>
                @error('recaptcha_provider')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recaptcha_site_key" class="mb-1 block text-sm font-medium text-slate-700">Site key</label>
                <input id="recaptcha_site_key" name="recaptcha_site_key" type="text" value="{{ old('recaptcha_site_key', $values['recaptcha_site_key']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('recaptcha_site_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recaptcha_secret_key" class="mb-1 block text-sm font-medium text-slate-700">Secret key</label>
                <input id="recaptcha_secret_key" name="recaptcha_secret_key" type="password" value="{{ old('recaptcha_secret_key', $values['recaptcha_secret_key']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('recaptcha_secret_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recaptcha_min_score" class="mb-1 block text-sm font-medium text-slate-700">Minimum score (0.0 to 1.0)</label>
                <input id="recaptcha_min_score" name="recaptcha_min_score" type="number" min="0" max="1" step="0.1" value="{{ old('recaptcha_min_score', $values['recaptcha_min_score']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm md:max-w-sm">
                @error('recaptcha_min_score')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recaptcha_trigger_after_attempts" class="mb-1 block text-sm font-medium text-slate-700">Trigger after attempts</label>
                <input id="recaptcha_trigger_after_attempts" name="recaptcha_trigger_after_attempts" type="number" min="0" max="100" step="1" value="{{ old('recaptcha_trigger_after_attempts', $values['recaptcha_trigger_after_attempts']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm md:max-w-sm">
                <p class="mt-1 text-xs text-slate-500">Set how many suspicious attempts are allowed before reCAPTCHA is required. Use 0 to always require.</p>
                @error('recaptcha_trigger_after_attempts')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
            <h3 class="text-base font-semibold text-slate-900">PayPal</h3>

            <div>
                <label for="paypal_client_id" class="mb-1 block text-sm font-medium text-slate-700">Client ID</label>
                <input id="paypal_client_id" name="paypal_client_id" type="text" value="{{ old('paypal_client_id', $values['paypal_client_id']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('paypal_client_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="paypal_client_secret" class="mb-1 block text-sm font-medium text-slate-700">Client secret</label>
                <input id="paypal_client_secret" name="paypal_client_secret" type="password" value="{{ old('paypal_client_secret', $values['paypal_client_secret']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('paypal_client_secret')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="paypal_base_url" class="mb-1 block text-sm font-medium text-slate-700">Base URL</label>
                <input id="paypal_base_url" name="paypal_base_url" type="url" value="{{ old('paypal_base_url', $values['paypal_base_url']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('paypal_base_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
            <h3 class="text-base font-semibold text-slate-900">Brevo</h3>

            <div>
                <label for="brevo_api_key" class="mb-1 block text-sm font-medium text-slate-700">API key</label>
                <input id="brevo_api_key" name="brevo_api_key" type="password" value="{{ old('brevo_api_key', $values['brevo_api_key']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('brevo_api_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="grid gap-3 rounded border border-slate-200 bg-white p-4">
            <h3 class="text-base font-semibold text-slate-900">DHL</h3>

            <div>
                <label for="dhl_tracking_url" class="mb-1 block text-sm font-medium text-slate-700">Tracking URL template</label>
                <input id="dhl_tracking_url" name="dhl_tracking_url" type="text" value="{{ old('dhl_tracking_url', $values['dhl_tracking_url']) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Use %s as the tracking number placeholder.</p>
                @error('dhl_tracking_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Save integration settings</button>
        </div>
    </form>
@endsection
