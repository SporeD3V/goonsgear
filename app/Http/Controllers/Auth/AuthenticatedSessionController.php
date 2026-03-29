<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, RecaptchaVerifier $recaptchaVerifier): View
    {
        $emailHint = (string) $request->session()->get('_old_input.email', '');

        return view('auth.login', [
            'recaptchaChallenge' => $recaptchaVerifier->shouldChallenge('login', $request, $emailHint),
            'recaptchaSiteKey' => $recaptchaVerifier->siteKey(),
        ]);
    }

    public function store(Request $request, RecaptchaVerifier $recaptchaVerifier): RedirectResponse
    {
        $email = strtolower($request->string('email')->trim()->toString());
        $requiresChallenge = $recaptchaVerifier->shouldChallenge('login', $request, $email);

        $rules = [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];

        if ($requiresChallenge) {
            $rules['recaptcha_token'][0] = 'required';
        }

        $credentials = $request->validate($rules);

        if ($requiresChallenge && ! $recaptchaVerifier->verifyCheckoutToken((string) ($credentials['recaptcha_token'] ?? ''), $request->ip())) {
            $recaptchaVerifier->registerSignal('login', $request, $email);

            throw ValidationException::withMessages([
                'recaptcha_token' => 'Security verification failed. Please try again.',
            ]);
        }

        unset($credentials['recaptcha_token']);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            $recaptchaVerifier->registerSignal('login', $request, $email);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $recaptchaVerifier->clearSignals('login', $request, $email);

        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.index');
    }
}
