<?php

namespace App\Support;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalClient
{
    public function isEnabled(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    public function clientId(): ?string
    {
        $clientId = trim((string) IntegrationSetting::value('paypal_client_id', (string) config('services.paypal.client_id')));

        return $clientId !== '' ? $clientId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(string $total, string $currency = 'EUR'): array
    {
        return $this->paypalRequest(
            'post',
            '/v2/checkout/orders',
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => $total,
                        ],
                    ],
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $paypalOrderId): array
    {
        return $this->paypalRequest('post', "/v2/checkout/orders/{$paypalOrderId}/capture", []);
    }

    private function clientSecret(): ?string
    {
        $clientSecret = trim((string) IntegrationSetting::value('paypal_client_secret', (string) config('services.paypal.client_secret')));

        return $clientSecret !== '' ? $clientSecret : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) IntegrationSetting::value('paypal_base_url', (string) config('services.paypal.base_url')), '/');
    }

    private function accessToken(): string
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('PayPal is not configured.');
        }

        return Cache::remember('payments:paypal:access_token', now()->addMinutes(50), function (): string {
            $response = Http::baseUrl($this->baseUrl())
                ->connectTimeout(3)
                ->timeout(10)
                ->retry([200, 500, 1000])
                ->withBasicAuth((string) $this->clientId(), (string) $this->clientSecret())
                ->asForm()
                ->post('/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            $token = (string) $response->json('access_token');

            if ($token === '') {
                throw new RuntimeException('Unable to obtain PayPal access token.');
            }

            return $token;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function paypalRequest(string $method, string $path, array $payload): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('PayPal is not configured.');
        }

        $response = Http::baseUrl($this->baseUrl())
            ->connectTimeout(3)
            ->timeout(10)
            ->retry([200, 500, 1000])
            ->withToken($this->accessToken())
            ->acceptJson()
            ->send($method, $path, [
                'json' => $payload,
            ])
            ->throw();

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Unexpected PayPal API response.');
        }

        return $body;
    }
}
