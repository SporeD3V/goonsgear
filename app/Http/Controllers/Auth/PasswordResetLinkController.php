<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(Request $request, RecaptchaVerifier $recaptchaVerifier): View
    {
        $emailHint = (string) $request->session()->get('_old_input.email', '');

        return view('auth.forgot-password', [
            'recaptchaChallenge' => $recaptchaVerifier->shouldChallenge('password-reset-link', $request, $emailHint),
            'recaptchaSiteKey' => $recaptchaVerifier->siteKey(),
        ]);
    }

    public function store(Request $request, RecaptchaVerifier $recaptchaVerifier): RedirectResponse
    {
        $email = strtolower($request->string('email')->trim()->toString());
        $requiresChallenge = $recaptchaVerifier->shouldChallenge('password-reset-link', $request, $email);

        $rules = [
            'email' => ['required', 'email'],
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];

        if ($requiresChallenge) {
            $rules['recaptcha_token'][0] = 'required';
        }

        $validated = $request->validate($rules);

        if ($requiresChallenge && ! $recaptchaVerifier->verifyCheckoutToken((string) ($validated['recaptcha_token'] ?? ''), $request->ip())) {
            $recaptchaVerifier->registerSignal('password-reset-link', $request, $email);

            throw ValidationException::withMessages([
                'recaptcha_token' => 'Security verification failed. Please try again.',
            ]);
        }

        unset($validated['recaptcha_token']);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            $recaptchaVerifier->registerSignal('password-reset-link', $request, $email);

            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        $recaptchaVerifier->clearSignals('password-reset-link', $request, $email);

        return back()->with('status', __($status));
    }
}
