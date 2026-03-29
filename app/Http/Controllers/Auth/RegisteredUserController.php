<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request, RecaptchaVerifier $recaptchaVerifier): View
    {
        $emailHint = (string) $request->session()->get('_old_input.email', '');

        return view('auth.register', [
            'recaptchaChallenge' => $recaptchaVerifier->shouldChallenge('register', $request, $emailHint),
            'recaptchaSiteKey' => $recaptchaVerifier->siteKey(),
        ]);
    }

    public function store(Request $request, RecaptchaVerifier $recaptchaVerifier): RedirectResponse
    {
        $email = strtolower($request->string('email')->trim()->toString());
        $requiresChallenge = $recaptchaVerifier->shouldChallenge('register', $request, $email);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.(new User)->getTable().',email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];

        if ($requiresChallenge) {
            $rules['recaptcha_token'][0] = 'required';
        }

        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $exception) {
            $recaptchaVerifier->registerSignal('register', $request, $email);

            throw $exception;
        }

        if ($requiresChallenge && ! $recaptchaVerifier->verifyCheckoutToken((string) ($validated['recaptcha_token'] ?? ''), $request->ip())) {
            $recaptchaVerifier->registerSignal('register', $request, $email);

            throw ValidationException::withMessages([
                'recaptcha_token' => 'Security verification failed. Please try again.',
            ]);
        }

        unset($validated['recaptcha_token']);

        $user = User::query()->create($validated);

        $recaptchaVerifier->clearSignals('register', $request, $email);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.index');
    }
}
