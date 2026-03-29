<?php

namespace App\Support;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class RecaptchaVerifier
{
    public function isEnabled(): bool
    {
        $rawSetting = IntegrationSetting::value('recaptcha_enabled');

        if ($rawSetting !== null) {
            return in_array(strtolower($rawSetting), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) config('services.recaptcha.enabled', false);
    }

    public function siteKey(): ?string
    {
        $siteKey = IntegrationSetting::value('recaptcha_site_key', (string) config('services.recaptcha.site_key', ''));

        return $siteKey !== null && trim($siteKey) !== '' ? $siteKey : null;
    }

    public function verifyCheckoutToken(string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $normalizedToken = trim($token);

        if ($normalizedToken === '') {
            return false;
        }

        $secretKey = IntegrationSetting::value('recaptcha_secret_key', (string) config('services.recaptcha.secret_key', ''));

        if ($secretKey === null || trim($secretKey) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(10)
                ->retry([200, 500, 1000])
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secretKey,
                    'response' => $normalizedToken,
                    'remoteip' => $remoteIp,
                ])
                ->throw();
        } catch (Throwable) {
            return false;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! (bool) ($payload['success'] ?? false)) {
            return false;
        }

        $action = strtolower(trim((string) ($payload['action'] ?? '')));

        if ($action !== '' && $action !== 'checkout') {
            return false;
        }

        $minimumScore = $this->minimumScore();
        $score = (float) ($payload['score'] ?? 0);

        return $score >= $minimumScore;
    }

    public function shouldChallenge(string $surface, Request $request, ?string $identifier = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $triggerAfterAttempts = $this->triggerAfterAttempts();

        if ($triggerAfterAttempts === 0) {
            return true;
        }

        return RateLimiter::tooManyAttempts(
            $this->attemptKey($surface, $request, $identifier),
            $triggerAfterAttempts,
        );
    }

    public function registerSignal(string $surface, Request $request, ?string $identifier = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        RateLimiter::increment($this->attemptKey($surface, $request, $identifier));
    }

    public function clearSignals(string $surface, Request $request, ?string $identifier = null): void
    {
        RateLimiter::clear($this->attemptKey($surface, $request, $identifier));
    }

    private function minimumScore(): float
    {
        $rawScore = IntegrationSetting::value('recaptcha_min_score', (string) config('services.recaptcha.min_score', '0.5'));
        $score = is_numeric($rawScore) ? (float) $rawScore : 0.5;

        if ($score < 0) {
            return 0;
        }

        if ($score > 1) {
            return 1;
        }

        return $score;
    }

    private function triggerAfterAttempts(): int
    {
        $rawValue = IntegrationSetting::value('recaptcha_trigger_after_attempts', (string) config('services.recaptcha.trigger_after_attempts', '3'));
        $value = is_numeric($rawValue) ? (int) $rawValue : 3;

        return max(0, $value);
    }

    private function attemptKey(string $surface, Request $request, ?string $identifier = null): string
    {
        $normalizedSurface = trim(strtolower($surface));
        $normalizedIp = trim((string) $request->ip());
        $normalizedIdentifier = trim(strtolower((string) $identifier));

        return 'security:recaptcha:'.$normalizedSurface.':'.sha1($normalizedIp.'|'.$normalizedIdentifier);
    }
}
